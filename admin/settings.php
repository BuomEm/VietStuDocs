<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';

// Ensure user is admin
redirectIfNotAdmin();

// Handle Settings Save (Ajax)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input) {
        $success = true;
        $errors = [];
        
        foreach ($input as $name => $value) {
            $category = 'general';
            
            // Determine category
            if (strpos($name, 'notify_') === 0 || strpos($name, 'telegram_') === 0) {
                $category = 'notifications';
            } elseif (strpos($name, 'site_') === 0) {
                $category = 'site';
            } elseif (strpos($name, 'limit_') === 0) {
                $category = 'limits';
            } elseif (strpos($name, 'cloudconvert_') === 0) {
                $category = 'apis';
            }
            
            if (!setSetting($name, $value, $category)) {
                $success = false;
                $errors[] = "Failed to save $name";
            }
        }
        
        if ($success) {
            $response['success'] = true;
            $response['message'] = 'Settings saved successfully';
        } else {
            $response['message'] = 'Some settings could not be saved: ' . implode(', ', $errors);
        }
    } else {
        $response['message'] = 'Invalid data format';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle Telegram Test
if (isset($_POST['test_telegram'])) {
    require_once __DIR__ . '/../config/telegram_notifications.php';
    $result = sendTelegramNotification("🔔 <b>TEST NOTIFICATION</b>\n\nThis is a test message from your DocShare Admin Panel.", 'system_alert');
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $result, 'message' => $result ? 'Gửi tin nhắn test thành công!' : 'Gửi thất bại. Vui lòng kiểm tra Token và Chat ID.']);
    exit;
}

$page_title = 'Cài đặt hệ thống - DocShare Admin';
$admin_active_page = 'settings';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent">
                Cài đặt hệ thống
            </h1>
            <p class="text-base-content/70">Quản lý cấu hình toàn bộ website</p>
        </div>
        <div>
            <button onclick="saveSettings()" class="btn btn-primary">
                <i class="fa-solid fa-save mr-2"></i>
                Lưu thay đổi
            </button>
        </div>
    </div>

    <!-- Main Settings Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1">
            <ul class="menu bg-base-100 rounded-box shadow-lg w-full">
                <li><a class="tab-link active" onclick="switchTab('site')"><i class="fa-solid fa-globe w-5"></i> Cài đặt Website</a></li>
                <li><a class="tab-link" onclick="switchTab('notifications')"><i class="fa-solid fa-bell w-5"></i> Thông báo & Telegram</a></li>
                <li><a class="tab-link" onclick="switchTab('limits')"><i class="fa-solid fa-gauge-high w-5"></i> Giới hạn & Tốc độ</a></li>
                <li><a class="tab-link" onclick="switchTab('apis')"><i class="fa-solid fa-code w-5"></i> API & Chuyển đổi</a></li>
            </ul>
        </div>

        <!-- Content Area -->
        <div class="lg:col-span-3">
            <!-- TAB: Site Settings -->
            <div id="tab-site" class="settings-tab">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><i class="fa-solid fa-globe text-primary mr-2"></i>Cài đặt Website</h2>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text font-bold">Tên Website</span></label>
                            <input type="text" id="site_name" class="input input-bordered w-full" 
                                   value="<?= htmlspecialchars(getSetting('site_name', 'DocShare')) ?>">
                        </div>

                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text font-bold">Logo URL</span></label>
                            <input type="text" id="site_logo" class="input input-bordered w-full" 
                                   value="<?= htmlspecialchars(getSetting('site_logo', '')) ?>"
                                   placeholder="/images/logo.png">
                        </div>

                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text font-bold">Mô tả Website (Meta Description)</span></label>
                            <textarea id="site_description" class="textarea textarea-bordered h-24"><?= htmlspecialchars(getSetting('site_description', '')) ?></textarea>
                        </div>

                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text font-bold">Từ khóa (SEO Keywords)</span></label>
                            <input type="text" id="site_keywords" class="input input-bordered w-full" 
                                   value="<?= htmlspecialchars(getSetting('site_keywords', '')) ?>"
                                   placeholder="docshare, tailieu, ebook...">
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text font-bold">Tác giả (Meta Author)</span></label>
                            <input type="text" id="site_author" class="input input-bordered w-full" 
                                   value="<?= htmlspecialchars(getSetting('site_author', '')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Notifications -->
            <div id="tab-notifications" class="settings-tab hidden">
                <div class="card bg-base-100 shadow-lg mb-6">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><i class="fa-brands fa-telegram text-info mr-2"></i>Cấu hình Telegram</h2>
                        
                        <div class="form-control mb-4">
                            <label class="label cursor-pointer justify-start gap-4">
                                <input type="checkbox" class="toggle toggle-primary" id="telegram_enabled" 
                                       <?= isSettingEnabled('telegram_enabled') ? 'checked' : '' ?>>
                                <span class="label-text font-bold">Bật tích hợp Telegram</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="form-control">
                                <label class="label"><span class="label-text">Bot Token</span></label>
                                <input type="text" id="telegram_bot_token" class="input input-bordered" 
                                       value="<?= htmlspecialchars(getSetting('telegram_bot_token', '')) ?>"
                                       placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text">Chat ID (Group/Channel)</span></label>
                                <input type="text" id="telegram_chat_id" class="input input-bordered" 
                                       value="<?= htmlspecialchars(getSetting('telegram_chat_id', '')) ?>"
                                       placeholder="-100123456789">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button onclick="testTelegram()" class="btn btn-info btn-sm">
                                <i class="fa-solid fa-paper-plane mr-2"></i> Test Kết Nối
                            </button>
                        </div>
                        <div id="telegram-test-result" class="mt-4"></div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><i class="fa-solid fa-bell text-warning mr-2"></i>Kênh thông báo</h2>
                        
                        <div class="form-control mb-4">
                            <label class="label cursor-pointer justify-start gap-4">
                                <input type="checkbox" class="toggle toggle-success" id="notify_browser_push_enabled" 
                                       <?= isSettingEnabled('notify_browser_push_enabled') ? 'checked' : '' ?>>
                                <span class="label-text font-bold">Bật thông báo trình duyệt (Browser Push)</span>
                            </label>
                        </div>

                        <div class="divider">Chi tiết sự kiện</div>
                        
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Sự kiện</th>
                                        <th class="text-center">Browser</th>
                                        <th class="text-center">Telegram</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $types = [
                                        'new_document' => 'Tài liệu mới chờ duyệt',
                                        'document_sold' => 'Tài liệu được mua',
                                        'system_alert' => 'Cảnh báo hệ thống',
                                        'report' => 'Báo cáo vi phạm'
                                    ];
                                    foreach($types as $key => $label): 
                                    ?>
                                    <tr>
                                        <td><?= $label ?></td>
                                        <td class="text-center">
                                            <input type="checkbox" class="checkbox checkbox-success" id="notify_<?= $key ?>_browser"
                                                   <?= isSettingEnabled("notify_{$key}_browser") ? 'checked' : '' ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" class="checkbox checkbox-info" id="notify_<?= $key ?>_telegram"
                                                   <?= isSettingEnabled("notify_{$key}_telegram") ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Limits -->
            <div id="tab-limits" class="settings-tab hidden">
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><i class="fa-solid fa-gauge-high text-error mr-2"></i>Giới hạn & Tốc độ tải</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-bold">Tốc độ tải (Thường)</span>
                                    <span class="label-text-alt">KB/s</span>
                                </label>
                                <input type="number" id="limit_download_speed_free" class="input input-bordered" 
                                       value="<?= getSetting('limit_download_speed_free', 100) ?>">
                                <label class="label">
                                    <span class="label-text-alt text-base-content/60">Dành cho khách và thành viên thường</span>
                                </label>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-bold text-warning">Tốc độ tải (Premium)</span>
                                    <span class="label-text-alt">KB/s</span>
                                </label>
                                <input type="number" id="limit_download_speed_premium" class="input input-bordered input-warning" 
                                       value="<?= getSetting('limit_download_speed_premium', 500) ?>">
                                <label class="label">
                                    <span class="label-text-alt text-base-content/60">Dành cho thành viên VIP</span>
                                </label>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-bold">Số trang xem trước tối đa</span>
                                    <span class="label-text-alt">Trang</span>
                                </label>
                                <input type="number" id="limit_preview_pages" class="input input-bordered" 
                                       value="<?= getSetting('limit_preview_pages', 3) ?>">
                                <label class="label">
                                    <span class="label-text-alt text-base-content/60">Số trang người dùng chưa mua có thể xem</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: APIs & Conversion -->
            <div id="tab-apis" class="settings-tab hidden">
                <div class="card bg-base-100 shadow-lg mb-6">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><i class="fa-solid fa-file-pdf text-red-500 mr-2"></i>Adobe PDF Services</h2>
                        <div class="alert alert-info shadow-sm mb-4">
                            <i class="fa-solid fa-info-circle"></i>
                            <div>
                                <h3 class="font-bold">Hướng dẫn cấu hình Adobe</h3>
                                <div class="text-xs">
                                    Hệ thống sử dụng Adobe API để chuyển đổi DOCX sang PDF chất lượng cao.
                                    <br>Yêu cầu file cấu hình: <code>API/pdfservices-api-credentials.json</code>
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                        $adobe_creds_path = __DIR__ . '/../API/pdfservices-api-credentials.json';
                        $has_adobe = file_exists($adobe_creds_path);
                        ?>
                        
                        <div class="flex items-center gap-2 mb-4">
                            <span class="badge <?= $has_adobe ? 'badge-success' : 'badge-error' ?> p-3">
                                <i class="fa-solid <?= $has_adobe ? 'fa-check' : 'fa-xmark' ?> mr-1"></i>
                                <?= $has_adobe ? 'Đã tìm thấy file cấu hình' : 'Chưa tìm thấy file cấu hình' ?>
                            </span>
                        </div>

                        <p class="text-sm text-base-content/70">
                            Nếu bạn host trên cPanel, hãy chắc chắn đã upload file <code>pdfservices-api-credentials.json</code> vào thư mục <code>API/</code> (thường file này bị .gitignore bỏ qua khi deploy).
                        </p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><i class="fa-solid fa-cloud text-info mr-2"></i>CloudConvert API (Dự phòng)</h2>
                        <p class="text-sm mb-4">Sử dụng làm phương án dự phòng khi Adobe API bị lỗi hoặc hết lượt dùng. CloudConvert hỗ trợ chuyển đổi DOCX sang PDF và tạo Thumbnail.</p>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text font-bold">CloudConvert API Key</span></label>
                            <input type="password" id="cloudconvert_api_key" class="input input-bordered w-full" 
                                   value="<?= htmlspecialchars(getSetting('cloudconvert_api_key', '')) ?>"
                                   placeholder="sk-abc123def456...">
                            <label class="label">
                                <span class="label-text-alt">Lấy key tại <a href="https://cloudconvert.com/dashboard/api/v2/keys" target="_blank" class="link link-primary">CloudConvert Dashboard</a></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.settings-tab').forEach(el => el.classList.add('hidden'));
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.remove('hidden');
    
    // Update menu active state
    document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
    // Simple logic to find the clicking link - in real app, might need more robust selector
    event.currentTarget.classList.add('active');
}

function saveSettings() {
    const btn = event.currentTarget || document.querySelector('button[onclick="saveSettings()"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Đang lưu...';

    const settings = {};
    
    // Collect Input fields
    const inputs = document.querySelectorAll('input[type="text"], input[type="number"], textarea');
    inputs.forEach(input => {
        if(input.id) settings[input.id] = input.value;
    });

    // Collect Checkboxes (Toggles)
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(box => {
        if(box.id) settings[box.id] = box.checked ? 'on' : 'off';
    });

    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showAlert(data.message, 'check-circle', 'Thành công');
        } else {
            showAlert(data.message, 'triangle-exclamation', 'Lỗi');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('Có lỗi xảy ra khi lưu cài đặt.', 'triangle-exclamation', 'Lỗi');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function testTelegram() {
    const resultDiv = document.getElementById('telegram-test-result');
    resultDiv.innerHTML = '<div class="loading loading-spinner text-info"></div> Đang gửi tin nhắn test...';
    
    const formData = new FormData();
    formData.append('test_telegram', '1');

    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            resultDiv.innerHTML = `<div class="alert alert-success mt-2"><i class="fa-solid fa-check"></i> ${data.message}</div>`;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-error mt-2"><i class="fa-solid fa-triangle-exclamation"></i> ${data.message}</div>`;
        }
    })
    .catch(err => {
        resultDiv.innerHTML = '<div class="alert alert-error mt-2">Lỗi kết nối!</div>';
    });
}

// Global alert helper (if not already defined in header/footer)
if(typeof showAlert === 'undefined') {
    window.showAlert = function(message, icon, title) {
        // Simple fallback alert
        alert(title + ": " + message);
    }
}
</script>

<?php 
require_once __DIR__ . '/../includes/admin-footer.php'; 
if (isset($conn)) mysqli_close($conn);
?>
