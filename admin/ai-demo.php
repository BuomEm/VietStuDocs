<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/categories.php';
require_once __DIR__ . '/../includes/ai_review_handler.php';

// Ensure user is admin
redirectIfNotAdmin();

$page_title = "AI Review Demo Test";
$admin_active_page = "ai-demo";

// Handle AJAX Request for AI Review
if (isset($_GET['ajax_review']) && isset($_GET['document_id'])) {
    header('Content-Type: application/json');
    try {
        $document_id = intval($_GET['document_id']);
        $handler = new AIReviewHandler($VSD);
        $result = $handler->reviewDocument($document_id);
        
        // Fetch the review details from database to show full logs
        $review = $VSD->get_row("SELECT * FROM documents WHERE id = $document_id");
        
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'result' => $result,
            'full_review' => $review ? [
                'judge' => json_decode($review['ai_judge_result'], true),
                'moderator' => json_decode($review['ai_moderator_result'], true),
                'score' => $review['ai_score'],
                'decision' => $review['ai_decision']
            ] : null
        ]);
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX Request for Document Details (Existing JSON)
if (isset($_GET['ajax_get_details']) && isset($_GET['document_id'])) {
    header('Content-Type: application/json');
    $document_id = intval($_GET['document_id']);
    $doc = $VSD->get_row("SELECT * FROM documents WHERE id = $document_id");
    
    if($doc && $doc['ai_status'] === 'completed') {
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'has_history' => true,
            'full_review' => [
                'judge' => json_decode($doc['ai_judge_result'], true),
                'moderator' => json_decode($doc['ai_moderator_result'], true),
                'score' => $doc['ai_score'],
                'decision' => $doc['ai_decision']
            ]
        ]);
    } else {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'has_history' => false]);
    }
    exit;
}

// Get Current Configured Models for Display
require_once __DIR__ . '/../config/settings.php';
$config_judge = getSetting('ai_model_judge', 'gpt-4o');
$config_mod = getSetting('ai_model_moderator', 'gpt-4o-mini');

// Fetch some documents to test
$docs = $VSD->get_list("SELECT d.*, u.username 
                           FROM documents d 
                           JOIN users u ON d.user_id = u.id 
                           ORDER BY d.created_at DESC 
                           LIMIT 10");

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="p-4 lg:p-8 animate-fade-in">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                    AI Review Demo Center
                </h1>
                <p class="text-base-content/60 mt-2">Phòng thử nghiệm và kiểm soát chất lượng AI Judge & Moderator</p>
            </div>
            <div class="badge badge-lg badge-outline badge-primary font-mono pulse">LIVE TESTING</div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left: Document List -->
            <div class="lg:col-span-1 space-y-4">
                <div class="card bg-base-100 border border-base-300 shadow-xl overflow-hidden">
                    <div class="card-body p-4">
                        <h2 class="card-title text-sm uppercase opacity-50 mb-4">Danh sách tài liệu gần đây</h2>
                        <div class="space-y-2">
                            <?php foreach($docs as $doc): ?>
                            <div class="group p-3 bg-base-200/50 hover:bg-primary/10 rounded-xl border border-transparent hover:border-primary/30 transition-all cursor-pointer"
                                 onclick="selectDocument(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['original_name']) ?>')">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex-1">
                                        <div class="font-bold text-sm line-clamp-1"><?= htmlspecialchars($doc['original_name']) ?></div>
                                        <div class="text-[10px] opacity-60">ID: <?= $doc['id'] ?> | @<?= $doc['username'] ?></div>
                                    </div>
                                    <div class="badge badge-xs <?= $doc['ai_status'] == 'completed' ? 'badge-success' : 'badge-ghost' ?>">
                                        <?= $doc['ai_status'] ?: 'None' ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info bg-info/10 border-info/20 text-xs">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>Chọn một tài liệu để bắt đầu quá trình thẩm định AI qua 2 vòng chuyên sâu.</div>
                </div>
            </div>

            <!-- Right: Control & Results -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Main Control Card -->
                <div id="control-card" class="card bg-base-100 border border-base-300 shadow-xl hidden">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="card-title text-xl font-black" id="selected-doc-title">Tài liệu đang chọn</h2>
                            <button id="btn-run-ai" onclick="runAI()" class="btn btn-primary shadow-lg shadow-primary/20">
                                <i class="fa-solid fa-bolt"></i> Chạy Thẩm Định
                            </button>
                        </div>

                        <!-- Progress Steps -->
                        <div class="space-y-6">
                            <div class="grid grid-cols-3 gap-4">
                                <div id="step-upload" class="p-3 rounded-xl border border-base-300 bg-base-200 opacity-30 text-center text-xs font-bold transition-all duration-500">1. UPLOAD</div>
                                <div id="step-judge" class="p-3 rounded-xl border border-base-300 bg-base-200 opacity-30 text-center text-xs font-bold transition-all duration-500">2. JUDGE</div>
                                <div id="step-mod" class="p-3 rounded-xl border border-base-300 bg-base-200 opacity-30 text-center text-xs font-bold transition-all duration-500">3. MODERATOR</div>
                            </div>

                            <!-- Live Progress Bar -->
                            <div id="ai-progress" class="hidden space-y-2">
                                <div class="flex justify-between text-[10px] font-bold uppercase opacity-50">
                                    <span id="progress-status">Khởi tạo...</span>
                                    <span id="progress-percent">0%</span>
                                </div>
                                <progress class="progress progress-primary w-full h-3" value="0" max="100"></progress>
                            </div>

                            <!-- Live Activity Console -->
                            <div class="mt-4 p-3 bg-base-300/50 rounded-xl border border-base-300 font-mono text-[10px] space-y-1 max-h-[500px] overflow-y-auto">
                                <div class="text-white/30 uppercase font-bold mb-2 sticky top-0 bg-base-300/80 backdrop-blur">System Activity Console</div>
                                <div id="ai-logs-container">
                                    <div class="text-success italic">> System ready. Awaiting command...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Initial State -->
                <div id="empty-state" class="card bg-base-100 border-2 border-dashed border-base-300 h-[400px] grid place-items-center">
                    <div class="text-center opacity-40">
                        <i class="fa-solid fa-robot text-6xl mb-4"></i>
                        <p class="font-bold">Chưa có dữ liệu kiểm thử</p>
                        <p class="text-sm">Vui lòng chọn tài liệu từ danh sách bên trái</p>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area" class="space-y-6 hidden">
                    
                    <!-- SUMMARY BANNER -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="card bg-success/10 border border-success/20 text-success p-4">
                            <div class="text-xs uppercase font-bold opacity-70">Quyết định</div>
                            <div id="res-decision" class="text-2xl font-black">---</div>
                        </div>
                        <div class="card bg-primary/10 border border-primary/20 text-primary p-4">
                            <div class="text-xs uppercase font-bold opacity-70">Điểm tổng quát</div>
                            <div id="res-score" class="text-2xl font-black">---</div>
                        </div>
                        <div class="card bg-secondary/10 border border-secondary/20 text-secondary p-4">
                            <div class="text-xs uppercase font-bold opacity-70">Cấp độ khó</div>
                            <div id="res-diff" class="text-2xl font-black">---</div>
                        </div>
                    </div>

                    <!-- TABS FOR LOGS -->
                    <div class="tabs tabs-boxed bg-base-100 p-1">
                        <a class="tab tab-active" onclick="switchResTab('tab-moderator', this)">Moderator Result</a>
                        <a class="tab" onclick="switchResTab('tab-judge', this)">Judge Result (RAW)</a>
                        <a class="tab" onclick="switchResTab('tab-meta', this)">Metadata</a>
                    </div>

                    <!-- Moderator View -->
                    <div id="tab-moderator" class="res-tab space-y-4">
                        <div class="card bg-base-100 border border-base-300 overflow-hidden">
                            <div class="bg-base-300 px-4 py-2 font-bold text-xs">Phân tích hiệu chỉnh từ Moderator</div>
                            <div class="card-body p-4 space-y-4">
                                <div id="res-mod-notes" class="space-y-1"></div>
                                <div class="divider text-[10px] uppercase opacity-30">Risk Flags</div>
                                <div id="res-risks" class="flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Judge View -->
                    <div id="tab-judge" class="res-tab hidden">
                        <pre id="json-judge" class="bg-base-300 p-4 rounded-xl font-mono text-xs overflow-x-auto text-primary"></pre>
                    </div>

                    <!-- Meta View -->
                    <div id="tab-meta" class="res-tab hidden">
                         <div id="res-meta-content" class="grid grid-cols-2 gap-4"></div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

<script>
const CONFIG_JUDGE_MODEL = '<?= $config_judge ?>';
const CONFIG_MOD_MODEL = '<?= $config_mod ?>';
let currentDocId = null;

function selectDocument(id, title) {
    currentDocId = id;
    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('control-card').classList.remove('hidden');
    document.getElementById('selected-doc-title').textContent = title;
    document.getElementById('result-area').classList.add('hidden');
    document.getElementById('ai-progress').classList.add('hidden');
    document.getElementById('ai-logs-container').innerHTML = ''; // Clear previous logs
    
    // Smooth scroll to control
    document.getElementById('control-card').scrollIntoView({ behavior: 'smooth' });

    // Fetch details to see if JSON already exists
    fetch(`ai-demo.php?ajax_get_details=1&document_id=${id}`)
        .then(r => r.json())
        .then(data => {
            if(data.success && data.has_history) {
                addLog('Phát hiện kết quả chấm điểm trước đó trong Database. Đang hiển thị lại...', 'warning');
                renderResults({ success: true, result: { decision: data.full_review.decision, score: data.full_review.score }, full_review: data.full_review });
            } else {
                addLog('Tài liệu chưa được kiểm duyệt bởi AI hoặc chưa có dữ liệu RAW JSON.', 'info');
            }
        });
}

function addLog(msg, type = 'info', raw = null) {
    const container = document.getElementById('ai-logs-container');
    const time = new Date().toLocaleTimeString();
    const colors = {
        'info': 'text-info',
        'success': 'text-success',
        'error': 'text-error',
        'warning': 'text-warning',
        'json': 'text-primary-content bg-primary/20 p-2 rounded block mt-1'
    };
    
    let html = `<div class="${colors[type]}">> [${time}] ${msg}</div>`;
    if (raw) {
        html += `<pre class="bg-black/30 p-2 rounded mt-1 text-[9px] text-accent overflow-x-auto">${JSON.stringify(raw, null, 2)}</pre>`;
    }
    container.innerHTML += html;
    container.parentElement.scrollTop = container.parentElement.scrollHeight;
}

function runAI() {
    if(!currentDocId) return;

    const btn = document.getElementById('btn-run-ai');
    const prog = document.getElementById('ai-progress');
    const btnOld = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Đang xử lý...';
    prog.classList.remove('hidden');
    document.getElementById('result-area').classList.add('hidden');
    document.getElementById('ai-logs-container').innerHTML = '';

    // Reset Steps
    ['step-upload', 'step-judge', 'step-mod'].forEach(s => {
        document.getElementById(s).classList.remove('bg-primary', 'text-primary-content', 'opacity-100');
        document.getElementById(s).classList.add('opacity-30', 'bg-base-200');
    });

    addLog('Bắt đầu quy trình kiểm tra AI cho Document ID: ' + currentDocId);
    updateProgress(5, 'Đang chuẩn bị Metadata...');
    
    setTimeout(() => {
        addLog('Tải File lên bộ nhớ tạm OpenAI Assistants...');
        document.getElementById('step-upload').classList.add('bg-primary', 'text-primary-content', 'opacity-100');
        updateProgress(15, 'Đang upload file...');
    }, 1000);

    setTimeout(() => {
        addLog(`Khởi tạo AI Judge [${CONFIG_JUDGE_MODEL}] - Đang phân tích nội dung chuyên sâu...`);
        document.getElementById('step-judge').classList.add('bg-primary', 'text-primary-content', 'opacity-100');
        updateProgress(45, 'Vòng 1: AI Judge đang chấm điểm...');
    }, 3000);

    setTimeout(() => {
        addLog(`Nhận kết quả Vòng 1. Khởi tạo AI Moderator [${CONFIG_MOD_MODEL}] để hậu kiểm...`);
        document.getElementById('step-mod').classList.add('bg-primary', 'text-primary-content', 'opacity-100');
        updateProgress(80, 'Vòng 2: Moderator đang phê duyệt...');
    }, 8000);

    fetch(`ai-demo.php?ajax_review=1&document_id=${currentDocId}`)
        .then(r => r.json())
        .then(data => {
            if(data.success && data.result.success !== false) {
                const full = data.full_review;
                addLog('Dữ liệu Vòng 1 (Judge RAW):', 'info', full.judge);
                addLog('Dữ liệu Vòng 2 (Moderator RAW):', 'info', full.moderator);
                addLog('Quy trình hoàn tất. Decision: ' + data.result.decision, 'success');
                updateProgress(100, 'Hoàn tất!');
                renderResults(data);
            } else {
                const errorMsg = data.error || (data.result && data.result.error) || 'Lỗi không xác định';
                addLog('Lỗi hệ thống: ' + errorMsg, 'error');
                updateProgress(0, 'Lỗi: ' + errorMsg);
            }
        })
        .catch(err => {
            console.error(err);
            addLog('Lỗi phản hồi hệ thống (Có thể do PHP Error): ' + err.message, 'error');
            updateProgress(0, 'Lỗi hệ thống!');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = btnOld;
        });
}

function updateProgress(val, text) {
    const bar = document.querySelector('#ai-progress progress');
    bar.value = val;
    document.getElementById('progress-percent').textContent = val + '%';
    document.getElementById('progress-status').textContent = text;
}

function renderResults(data) {
    const res = data.result;
    const full = data.full_review;

    // Banner
    document.getElementById('res-decision').textContent = res.decision;
    document.getElementById('res-score').textContent = res.score + ' / 100';
    document.getElementById('res-diff').textContent = full.judge.difficulty_level || 'N/A';
    
    // Style decision - Support both EN and VN terms
    const dCard = document.getElementById('res-decision').parentElement;
    let colorClass = 'bg-base-200 border-base-300 text-base-content';
    if (res.decision === 'APPROVED' || res.decision === 'Chấp Nhận') {
        colorClass = 'bg-success/10 border-success/20 text-success';
    } else if (res.decision === 'CONDITIONAL' || res.decision === 'Xem Xét') {
        colorClass = 'bg-warning/10 border-warning/20 text-warning';
    } else if (res.decision === 'REJECTED' || res.decision === 'Từ Chối') {
        colorClass = 'bg-error/10 border-error/20 text-error';
    }
    dCard.className = 'card p-4 border ' + colorClass;

    // Moderator Notes
    const notesDiv = document.getElementById('res-mod-notes');
    notesDiv.innerHTML = '';
    (full.moderator.moderator_notes || []).forEach(n => {
        notesDiv.innerHTML += `<div class="p-2 bg-base-200 rounded text-sm"><i class="fa-solid fa-check text-success mr-2"></i> ${n}</div>`;
    });

    // Risks
    const risksDiv = document.getElementById('res-risks');
    risksDiv.innerHTML = '';
    (full.moderator.risk_flags || []).forEach(f => {
        risksDiv.innerHTML += `<span class="badge badge-error badge-outline text-[10px] font-bold">${f.toUpperCase()}</span>`;
    });

    // RAW JSON
    document.getElementById('json-judge').textContent = JSON.stringify(full.judge, null, 2);

    // Metadata
    const metaDiv = document.getElementById('res-meta-content');
    metaDiv.innerHTML = '';
    // Since we don't have metadata returned in AJAX for demo, we skip detailed view or re-fetch
    metaDiv.innerHTML = '<div class="col-span-2 text-center py-8 opacity-40">Metadata logs already applied to prompt context.</div>';

    document.getElementById('result-area').classList.remove('hidden');
    document.getElementById('result-area').scrollIntoView({ behavior: 'smooth' });
}

function switchResTab(id, el) {
    document.querySelectorAll('.res-tab').forEach(t => t.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('tab-active'));
    el.classList.add('tab-active');
}
</script>

<?php 
$output = ob_get_clean();
echo $output;
require_once __DIR__ . '/../includes/admin-footer.php'; 
?>
