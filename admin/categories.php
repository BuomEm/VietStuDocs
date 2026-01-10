<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/categories.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý hệ thống phân loại";

// JSON Files Config
$json_files = [
    'mon-hoc' => ['name' => 'mon-hoc.json', 'desc' => 'Danh mục môn học Phổ thông (Cấp 1-2-3)', 'path' => __DIR__ . '/../API/mon-hoc.json', 'icon' => 'fa-book', 'color' => 'primary'],
    'nganh-hoc' => ['name' => 'nganh-hoc.json', 'desc' => 'Danh mục Ngành & Nhóm ngành Đại học', 'path' => __DIR__ . '/../API/nganh-hoc.json', 'icon' => 'fa-university', 'color' => 'secondary'],
    'loai-tai-lieu' => ['name' => 'loai-tai-lieu.json', 'desc' => 'Phân loại định dạng tài liệu chung', 'path' => __DIR__ . '/../API/loai-tai-lieu.json', 'icon' => 'fa-tags', 'color' => 'accent'],
];

$msg = null;
$edited_file = null;

// Handle JSON Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_json') {
    $file_key = $_POST['file_key'] ?? '';
    $json_content = $_POST['json_content'] ?? '';
    
    if (isset($json_files[$file_key])) {
        // Validate JSON
        $decoded = json_decode($json_content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $msg = ['error', 'JSON không hợp lệ: ' . json_last_error_msg()];
            $edited_file = $file_key;
        } else {
            // Pretty print and save
            $pretty_json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Backup current file
            $backup_dir = __DIR__ . '/../API/backups/';
            if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
            $backup_file = $backup_dir . $file_key . '_' . date('Y-m-d_H-i-s') . '.json';
            copy($json_files[$file_key]['path'], $backup_file);
            
            // Save new content
            if (file_put_contents($json_files[$file_key]['path'], $pretty_json)) {
                $msg = ['success', 'Đã lưu thành công! Backup: ' . basename($backup_file)];
            } else {
                $msg = ['error', 'Không thể ghi file. Kiểm tra quyền thư mục!'];
            }
        }
    }
}

// Data Processing
$education_levels = getEducationLevels();
$category_stats = [];
$stats_result = $VSD->get_list("SELECT education_level, COUNT(*) as count FROM document_categories GROUP BY education_level");
foreach ($stats_result as $row) {
    $category_stats[$row['education_level']] = $row['count'];
}

$admin_active_page = 'categories';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-primary"></i>
                    Hệ thống phân loại
                </h1>
                <p class="text-base-content/60 text-sm mt-1">
                    Cấu trúc phân cấp tài liệu: Cấp học → Lớp/Ngành → Môn học
                </p>
            </div>
            
            <div class="flex gap-2">
                <a href="https://jsonlint.com" target="_blank" class="btn btn-sm btn-ghost border border-base-300 gap-2">
                    <i class="fa-solid fa-check-circle"></i> Validate JSON
                </a>
            </div>
        </div>
        
        <?php if($msg): ?>
            <div role="alert" class="alert alert-<?= $msg[0] ?> shadow-sm">
                <i class="fa-solid <?= $msg[0] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <span><?= $msg[1] ?></span>
            </div>
        <?php endif; ?>

        <!-- JSON Editor Cards -->
        <div class="grid grid-cols-1 gap-6">
            <?php foreach($json_files as $key => $file): 
                $content = file_exists($file['path']) ? file_get_contents($file['path']) : '{}';
                $line_count = substr_count($content, "\n") + 1;
            ?>
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-0">
                    <!-- File Header -->
                    <div class="p-4 border-b border-base-200 bg-gradient-to-r from-<?= $file['color'] ?>/5 to-transparent flex flex-wrap justify-between items-center gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-<?= $file['color'] ?>/10 text-<?= $file['color'] ?> grid place-items-center">
                                <i class="fa-solid <?= $file['icon'] ?> text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-bold flex items-center gap-2">
                                    <code class="text-base"><?= $file['name'] ?></code>
                                </h3>
                                <p class="text-xs text-base-content/60"><?= $file['desc'] ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-ghost badge-sm"><?= $line_count ?> dòng</span>
                            <span class="badge badge-ghost badge-sm"><?= number_format(strlen($content)) ?> bytes</span>
                            <a href="/API/<?= $file['name'] ?>" target="_blank" class="btn btn-xs btn-ghost gap-1">
                                <i class="fa-solid fa-external-link"></i> Xem
                            </a>
                        </div>
                    </div>
                    
                    <!-- Editor Form -->
                    <form method="POST" onsubmit="return validateAndSubmit(this, '<?= $key ?>')">
                        <input type="hidden" name="action" value="save_json">
                        <input type="hidden" name="file_key" value="<?= $key ?>">
                        
                        <div class="relative">
                            <!-- Line Numbers & Editor -->
                            <div class="flex">
                                <div id="lines-<?= $key ?>" class="bg-base-200/50 text-base-content/30 text-xs font-mono py-4 px-3 select-none text-right border-r border-base-200" style="min-width: 40px;">
                                    <?php for($i = 1; $i <= min($line_count, 50); $i++): ?>
                                        <div><?= $i ?></div>
                                    <?php endfor; ?>
                                    <?php if($line_count > 50): ?><div>...</div><?php endif; ?>
                                </div>
                                <textarea 
                                    name="json_content" 
                                    id="editor-<?= $key ?>"
                                    class="flex-1 font-mono text-sm p-4 bg-base-100 border-0 focus:outline-none focus:ring-0 resize-none"
                                    style="min-height: 300px; max-height: 500px;"
                                    spellcheck="false"
                                    oninput="updateLineNumbers('<?= $key ?>')"
                                ><?= htmlspecialchars($content) ?></textarea>
                            </div>
                            
                            <!-- Validation Status -->
                            <div id="status-<?= $key ?>" class="absolute bottom-3 right-3 hidden">
                                <span class="badge badge-success gap-1">
                                    <i class="fa-solid fa-check-circle"></i> JSON hợp lệ
                                </span>
                            </div>
                        </div>
                        
                        <!-- Action Footer -->
                        <div class="p-4 border-t border-base-200 bg-base-200/20 flex justify-between items-center">
                            <div class="flex items-center gap-3 text-xs text-base-content/50">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Tự động backup trước khi lưu</span>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="formatJSON('<?= $key ?>')" class="btn btn-sm btn-ghost gap-2">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Format
                                </button>
                                <button type="button" onclick="validateJSON('<?= $key ?>')" class="btn btn-sm btn-ghost gap-2">
                                    <i class="fa-solid fa-check-double"></i> Validate
                                </button>
                                <button type="submit" class="btn btn-sm btn-primary gap-2">
                                    <i class="fa-solid fa-save"></i> Lưu thay đổi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Info Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Stats Grid -->
            <div class="lg:col-span-2">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php 
                    $levels_cfg = [
                        'tieu_hoc' => ['color' => 'text-success', 'bg' => 'bg-success/10', 'icon' => 'fa-shapes'],
                        'thcs' => ['color' => 'text-info', 'bg' => 'bg-info/10', 'icon' => 'fa-book-open'],
                        'thpt' => ['color' => 'text-secondary', 'bg' => 'bg-secondary/10', 'icon' => 'fa-graduation-cap'],
                        'dai_hoc' => ['color' => 'text-warning', 'bg' => 'bg-warning/10', 'icon' => 'fa-building-columns']
                    ];
                    foreach($education_levels as $level): 
                        $code = $level['code'];
                        $count = $category_stats[$code] ?? 0;
                        $cfg = $levels_cfg[$code] ?? ['color'=>'text-primary', 'bg'=>'bg-primary/10', 'icon'=>'fa-folder'];
                    ?>
                    <div class="card bg-base-100 shadow-sm border border-base-200 hover:-translate-y-1 transition-transform">
                        <div class="card-body p-4 flex flex-row items-center gap-3">
                            <div class="w-10 h-10 rounded-xl <?= $cfg['bg'] ?> <?= $cfg['color'] ?> grid place-items-center text-lg shadow-sm">
                                <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                            </div>
                            <div>
                                <div class="text-xl font-bold tracking-tight"><?= number_format($count) ?></div>
                                <div class="text-xs uppercase font-bold text-base-content/50"><?= $level['name'] ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="card bg-base-100 shadow-sm border border-base-200 border-l-4 border-l-info">
                <div class="card-body p-4">
                    <h3 class="font-bold flex items-center gap-2 text-info">
                        <i class="fa-solid fa-lightbulb"></i> Mẹo nhanh
                    </h3>
                    <ul class="text-sm space-y-2 mt-3 text-base-content/70">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span>Dùng nút <strong>Format</strong> để tự động căn chỉnh JSON</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span>Nút <strong>Validate</strong> kiểm tra cú pháp trước khi lưu</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span>Backup tự động lưu tại <code class="bg-base-200 px-1 rounded">/API/backups/</code></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateLineNumbers(key) {
    const editor = document.getElementById('editor-' + key);
    const linesDiv = document.getElementById('lines-' + key);
    const lineCount = editor.value.split('\n').length;
    let html = '';
    for (let i = 1; i <= Math.min(lineCount, 500); i++) {
        html += '<div>' + i + '</div>';
    }
    if (lineCount > 500) html += '<div>...</div>';
    linesDiv.innerHTML = html;
}

function validateJSON(key) {
    const editor = document.getElementById('editor-' + key);
    const statusDiv = document.getElementById('status-' + key);
    
    try {
        JSON.parse(editor.value);
        statusDiv.innerHTML = '<span class="badge badge-success gap-1"><i class="fa-solid fa-check-circle"></i> JSON hợp lệ</span>';
        statusDiv.classList.remove('hidden');
        setTimeout(() => statusDiv.classList.add('hidden'), 3000);
        return true;
    } catch (e) {
        statusDiv.innerHTML = '<span class="badge badge-error gap-1"><i class="fa-solid fa-times-circle"></i> ' + e.message + '</span>';
        statusDiv.classList.remove('hidden');
        return false;
    }
}

function formatJSON(key) {
    const editor = document.getElementById('editor-' + key);
    const statusDiv = document.getElementById('status-' + key);
    
    try {
        const parsed = JSON.parse(editor.value);
        editor.value = JSON.stringify(parsed, null, 2);
        updateLineNumbers(key);
        statusDiv.innerHTML = '<span class="badge badge-success gap-1"><i class="fa-solid fa-sparkles"></i> Đã format!</span>';
        statusDiv.classList.remove('hidden');
        setTimeout(() => statusDiv.classList.add('hidden'), 2000);
    } catch (e) {
        statusDiv.innerHTML = '<span class="badge badge-error gap-1"><i class="fa-solid fa-times-circle"></i> ' + e.message + '</span>';
        statusDiv.classList.remove('hidden');
    }
}

function validateAndSubmit(form, key) {
    if (!validateJSON(key)) {
        if (!confirm('JSON không hợp lệ! Bạn vẫn muốn lưu?')) {
            return false;
        }
    }
    return confirm('Xác nhận lưu thay đổi cho file này? (Backup sẽ được tạo tự động)');
}

// Tab key support for textarea
document.querySelectorAll('textarea[id^="editor-"]').forEach(editor => {
    editor.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
            this.selectionStart = this.selectionEnd = start + 2;
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>

