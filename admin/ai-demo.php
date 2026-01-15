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
        
        // Cấu hình tăng timeout cho request thí nghiệm (Shared Hosting)
        @set_time_limit(300);
        ignore_user_abort(true);
        
        $handler = new AIReviewHandler($conn);
        $result = $handler->reviewDocument($document_id);
        
        // Fetch newly updated review details
        $review = db_get_row("SELECT * FROM documents WHERE id = $document_id");
        
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'result' => $result,
            'full_review' => $review ? [
                'judge' => json_decode($review['ai_judge_result'], true),
                'moderator' => json_decode($review['ai_moderator_result'], true),
                'score' => $review['ai_score'],
                'decision' => $review['ai_decision'],
                'status' => $review['ai_status'],
                'error' => $review['error_message']
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
    $doc = db_get_row("SELECT * FROM documents WHERE id = $document_id");
    
    if($doc) {
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'has_history' => ($doc['ai_status'] === 'completed' || $doc['ai_status'] === 'failed'),
            'status' => $doc['ai_status'],
            'full_review' => [
                'judge' => json_decode($doc['ai_judge_result'] ?? '', true),
                'moderator' => json_decode($doc['ai_moderator_result'] ?? '', true),
                'score' => $doc['ai_score'],
                'decision' => $doc['ai_decision'],
                'error' => $doc['error_message']
            ]
        ]);
    } else {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Document not found']);
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
                                LIMIT 30"); // Increased limit to fill scroll list

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
                        <div class="card bg-base-100 border border-base-300 shadow-xl overflow-hidden flex flex-col h-full max-h-[700px]">
                            <div class="card-body p-4 overflow-hidden flex flex-col">
                                <h2 class="card-title text-sm uppercase opacity-50 mb-4 border-b border-base-200 pb-2">Danh sách tài liệu gần đây</h2>
                                <div class="space-y-2 overflow-y-auto flex-1 pr-1 custom-scrollbar">
                                    <?php foreach($docs as $doc): 
                                        $status_color = 'badge-ghost';
                                        if($doc['ai_status'] == 'completed') $status_color = 'badge-success';
                                        elseif($doc['ai_status'] == 'processing') $status_color = 'badge-warning';
                                        elseif($doc['ai_status'] == 'pending') $status_color = 'badge-info';
                                        elseif($doc['ai_status'] == 'failed') $status_color = 'badge-error';
                                    ?>
                                    <div class="group p-3 bg-base-200/50 hover:bg-primary/10 rounded-xl border border-transparent hover:border-primary/30 transition-all cursor-pointer"
                                         onclick="selectDocument(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['original_name']) ?>')">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="flex-1">
                                                <div class="font-bold text-sm line-clamp-2"><?= htmlspecialchars($doc['original_name']) ?></div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <div class="text-[10px] font-black opacity-30 tracking-widest uppercase">ID: <?= $doc['id'] ?></div>
                                                    <div class="w-1 h-1 rounded-full bg-base-content/20"></div>
                                                    <div class="text-[10px] font-bold text-primary/70"><i class="fa-solid fa-file-lines mr-1 opacity-50"></i><?= ($doc['total_pages'] ?? 0) ?> trang</div>
                                                </div>
                                                <div class="text-[9px] font-bold opacity-40 italic mt-0.5">@<?= $doc['username'] ?></div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1">
                                                <div class="badge badge-xs <?= $status_color ?> font-black text-[8px] tracking-tight">
                                                    <?= strtoupper($doc['ai_status'] ?: 'None') ?>
                                                </div>
                                                <div class="text-[8px] font-black <?= $doc['ai_decision'] == 'APPROVED' ? 'text-success' : ($doc['ai_decision'] == 'REJECTED' ? 'text-error' : 'text-warning') ?>">
                                                    <?= $doc['ai_decision'] ?>
                                                </div>
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
                        <div class="card bg-base-100 border border-base-300 overflow-hidden">
                            <div class="bg-base-300 px-4 py-2 font-bold text-xs">Moderator RAW JSON</div>
                            <pre id="json-mod" class="bg-base-200 p-4 font-mono text-[10px] overflow-x-auto text-secondary"></pre>
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
                if(data.status === 'completed') {
                    addLog('Phát hiện kết quả thẩm định hoàn tất. Đang hiển thị...', 'success');
                    renderResults(data);
                } else if(data.status === 'failed') {
                    addLog('Thẩm định lần trước bị LỖI: ' + (data.full_review.error || 'Unknown'), 'error');
                    renderResults(data);
                }
            } else if(data.status === 'pending') {
                addLog('Tài liệu đang nằm trong HÀNG ĐỢI (Pending). Cron Job sẽ xử lý sau 1-2 phút.', 'info');
            } else if(data.status === 'processing') {
                addLog('Tài liệu đang được XỬ LÝ ngầm bởi một tiến trình khác...', 'warning');
            } else {
                addLog('Tài liệu chưa được kiểm duyệt bởi AI. Nhấn "Chạy Thẩm Định" để thử nghiệm thủ công.', 'info');
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
    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Đang chạy...';
    prog.classList.remove('hidden');
    document.getElementById('result-area').classList.add('hidden');
    document.getElementById('ai-logs-container').innerHTML = '';

    // Reset Steps
    ['step-upload', 'step-judge', 'step-mod'].forEach(s => {
        document.getElementById(s).classList.remove('bg-primary', 'text-primary-content', 'opacity-100');
        document.getElementById(s).classList.add('opacity-30', 'bg-base-200');
    });

    addLog('Bắt đầu thử nghiệm AI trực tiếp (Manual Trigger)...');
    updateProgress(10, 'Khởi tạo API...');
    
    // Simulate steps locally for UI feedback while waiting for long AJAX
    let step = 1;
    const stepInterval = setInterval(() => {
        if (step === 1) {
            addLog('Đang upload/kiểm tra file trên OpenAI...');
            document.getElementById('step-upload').classList.add('bg-primary', 'text-primary-content', 'opacity-100');
            updateProgress(25, 'Upload file...');
        } else if (step === 2) {
            addLog('Gửi yêu cầu Vòng 1: AI Judge... (Có thể mất 30-60s)');
            document.getElementById('step-judge').classList.add('bg-primary', 'text-primary-content', 'opacity-100');
            updateProgress(50, 'Vòng 1 đang chạy...');
        } else if (step === 3) {
            addLog('Vòng 1 hoàn tất (giả định), đang đợi server phản hồi Vòng 2...');
            document.getElementById('step-mod').classList.add('bg-primary', 'text-primary-content', 'opacity-100');
            updateProgress(75, 'Vòng 2 đang phê duyệt...');
        }
        step++;
        if (step > 3) clearInterval(stepInterval);
    }, 5000);

    fetch(`ai-demo.php?ajax_review=1&document_id=${currentDocId}`)
        .then(r => r.json())
        .then(data => {
            clearInterval(stepInterval);
            if(data.success) {
                const full = data.full_review;
                if(full.status === 'completed') {
                    addLog('Quy trình hoàn tất thành công!', 'success');
                    updateProgress(100, 'Hoàn tất!');
                    renderResults(data);
                } else {
                    addLog('Kết quả không như mong đợi. Status: ' + full.status, 'warning');
                    if(full.error) addLog('Chi tiết lỗi: ' + full.error, 'error');
                    renderResults(data);
                }
            } else {
                addLog('LỖI: ' + (data.error || 'Server error'), 'error');
                updateProgress(0, 'Lỗi!');
            }
        })
        .catch(err => {
            clearInterval(stepInterval);
            addLog('Lỗi kết nối: ' + err.message, 'error');
            updateProgress(0, 'Lỗi hệ thống!');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = btnOld;
        });
}

function updateProgress(val, text) {
    const bar = document.querySelector('#ai-progress progress');
    if (bar) {
        bar.value = val;
        document.getElementById('progress-percent').textContent = val + '%';
        document.getElementById('progress-status').textContent = text;
    }
}

function renderResults(data) {
    if (!data || !data.full_review) {
        addLog('Lỗi: Dữ liệu kết quả không hợp lệ hoặc bị thiếu.', 'error');
        return;
    }
    
    const full = data.full_review || {};
    const judge = full.judge || {};
    const moderator = full.moderator || {};

    // Banner Summary
    document.getElementById('res-decision').textContent = full.decision || 'N/A';
    document.getElementById('res-score').textContent = (full.score !== undefined ? full.score : '---') + ' / 100';
    document.getElementById('res-diff').textContent = judge.difficulty_level || 'N/A';
    
    // Style decision banner
    const dCard = document.getElementById('res-decision').parentElement;
    let colorClass = 'bg-base-200 border-base-300 text-base-content';
    const decision = (full.decision || '').toUpperCase();
    
    if (['APPROVED', 'CHẤP NHẬN'].includes(decision)) colorClass = 'bg-success/10 border-success/20 text-success';
    else if (['CONDITIONAL', 'XEM XÉT'].includes(decision)) colorClass = 'bg-warning/10 border-warning/20 text-warning';
    else if (['REJECTED', 'TỪ CHỐI'].includes(decision)) colorClass = 'bg-error/10 border-error/20 text-error';
    
    dCard.className = 'card p-4 border ' + colorClass;

    // Moderator Notes
    const notesDiv = document.getElementById('res-mod-notes');
    notesDiv.innerHTML = '';
    (moderator.moderator_notes || []).forEach(n => {
        notesDiv.innerHTML += `<div class="p-2 bg-base-200 rounded text-sm"><i class="fa-solid fa-check text-success mr-2"></i> ${n}</div>`;
    });
    
    // Risks
    const risksDiv = document.getElementById('res-risks');
    risksDiv.innerHTML = '';
    (moderator.risk_flags || []).forEach(f => {
        risksDiv.innerHTML += `<span class="badge badge-error badge-outline text-[10px] font-bold">${f.toUpperCase()}</span>`;
    });

    // Error details if failed
    if (full.status === 'failed') {
        notesDiv.innerHTML = `<div class="p-4 bg-error/10 text-error rounded-xl border border-error/20">
            <div class="font-black uppercase text-xs mb-1">Error Log:</div>
            <div class="font-mono text-[10px]">${full.error || 'Unknown fatal error'}</div>
        </div>`;
    }

    // RAW JSON
    document.getElementById('json-judge').textContent = JSON.stringify(judge, null, 2);
    document.getElementById('json-mod').textContent = JSON.stringify(moderator, null, 2);

    // Metadata
    const metaDiv = document.getElementById('res-meta-content');
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
