<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';

// Ensure user is admin
redirectIfNotAdmin();

// [Logic xử lý POST request]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    // Handle File Upload logic
    if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $filename = $_FILES['site_logo_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/settings';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_name = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $upload_dir . '/' . $new_name)) {
                $input['site_logo'] = '/uploads/settings/' . $new_name;
            }
        }
    }
    
    // Handle Open Graph Image Upload
    if (isset($_FILES['og_image_file']) && $_FILES['og_image_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['og_image_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/settings';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_name = 'og_image_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['og_image_file']['tmp_name'], $upload_dir . '/' . $new_name)) {
                $input['og_image'] = '/uploads/settings/' . $new_name;
            }
        }
    }
    // 1.2 Detailed Usage Logic
    if (isset($input['get_detailed_ai_usage'])) {
        require_once __DIR__ . '/../includes/ai_review_handler.php';
        $handler = new AIReviewHandler($VSD);
        echo json_encode($handler->getDetailedUsage());
        exit;
    }

    // 1. AI Balance Logic (uses Organization Costs API with Admin Key)
    if (isset($input['check_ai_balance'])) {
        require_once __DIR__ . '/../includes/ai_review_handler.php';
        $handler = new AIReviewHandler($VSD);
        
        $billing = $handler->getBillingUsage();
        
        // Mask keys for display
        $api_key = $handler->getApiKey();
        $admin_key = $handler->getAdminKey();
        $key_masked = $api_key ? (substr($api_key, 0, 8) . '...' . substr($api_key, -4)) : 'Not configured';
        $admin_key_masked = $admin_key ? (substr($admin_key, 0, 8) . '...' . substr($admin_key, -4)) : 'Not configured';

        echo json_encode([
            'success' => $billing['success'] ?? false,
            'total_usage' => $billing['total_usage'] ?? 0, // Already USD, no division
            'source' => $billing['source'] ?? 'unknown',
            'error_billing' => $billing['error'] ?? null,
            'api_key_used' => $key_masked,
            'admin_key_used' => $admin_key_masked,
            'has_admin_key' => !empty($admin_key)
        ]);
        exit;
    }

    // 2. AI Models Refresh Logic
    if (isset($input['refresh_ai_models'])) {
        require_once __DIR__ . '/../includes/ai_review_handler.php';
        $handler = new AIReviewHandler($VSD);
        echo json_encode($handler->refreshModelsList());
        exit;
    }

    // 3. Telegram Test Logic
    if (isset($input['test_telegram'])) {
        require_once __DIR__ . '/../config/telegram_notifications.php';
        require_once __DIR__ . '/../config/notifications.php';
        $buttons = [['text' => 'Truy cập Admin', 'url' => getBaseUrl() . '/admin/']];
        $result = sendTelegramNotification("🚨 <b>TEST KẾT NỐI</b>\nKết nối thành công!", 'system_alert', $buttons);
        echo json_encode(['success' => $result['success'], 'message' => $result['success'] ? 'Gửi thành công!' : 'Thất bại.']);
        exit;
    }

    // 4. Webhook Logic
    if (isset($input['telegram_webhook_action'])) {
        $action = $input['telegram_webhook_action'];
        $bot_token = getTelegramBotToken();
        $response = ['success' => false, 'message' => ''];
        
        if (empty($bot_token)) {
            $response['message'] = 'Chưa cấu hình Token.';
        } else {
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $webhook_url = $base_url . "/api/telegram_webhook.php?token=vsd_secure_callback_2026";
            $api_url = "https://api.telegram.org/bot{$bot_token}/" . ($action === 'delete' ? 'deleteWebhook' : 'setWebhook');
            $params = $action === 'delete' ? ['drop_pending_updates' => true] : ['url' => $webhook_url];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = json_decode(curl_exec($ch), true);
            curl_close($ch);
            
            if (isset($result['ok']) && $result['ok']) {
                $response['success'] = true;
                $response['message'] = $action === 'delete' ? 'Đã gỡ Webhook.' : 'Đã cài Webhook.';
                $response['webhook_url'] = $webhook_url;
            } else {
                $response['message'] = 'Lỗi API Telegram: ' . ($result['description'] ?? 'Unknown');
            }
        }
        echo json_encode($response);
        exit;
    }

    // Default: General settings save
    if ($input) {
        $success = true;
        foreach ($input as $name => $value) {
            $category = 'site';
            if (strpos($name, 'notify_') === 0 || strpos($name, 'telegram_') === 0) $category = 'notifications';
            elseif (strpos($name, 'limit_') === 0) $category = 'limits';
            elseif (strpos($name, 'cloudconvert_') === 0) $category = 'apis';
            elseif (strpos($name, 'tutor_') === 0) $category = 'tutor';
            elseif (strpos($name, 'shop_') === 0) $category = 'shop';
            elseif (strpos($name, 'ai_') === 0) $category = 'ai';
            elseif (strpos($name, 'streak_') === 0) $category = 'streak';
            setSetting($name, $value, null, $category);
        }
        $response['success'] = true;
        $response['message'] = 'Đã lưu cài đặt thành công!';
        $response['debug_keys'] = array_keys($input);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$models_json_path = __DIR__ . '/../API/openai_models.json';
$ai_models = [];
if (file_exists($models_json_path)) {
    $data = json_decode(file_get_contents($models_json_path), true);
    $ai_models = $data['data'] ?? [];
}

$page_title = 'Cài đặt hệ thống';
$admin_active_page = 'settings';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-primary"></i>
                    Cài đặt hệ thống
                </h1>
                <p class="text-base-content/60 text-sm mt-1">Cấu hình toàn bộ thông số vận hành của website</p>
            </div>
            <button onclick="saveSettings()" class="btn btn-primary shadow-lg shadow-primary/20">
                <i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi
            </button>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Sidebar Menu -->
            <div class="w-full lg:w-64 flex-shrink-0">
                <div class="sticky top-24">
                    <ul class="menu bg-base-100 rounded-xl shadow-sm border border-base-200 w-full p-2 gap-1 text-sm font-medium">
                        <li>
                            <a class="active" onclick="switchTab('site', this)">
                                <i class="fa-solid fa-globe w-5"></i> Chung
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('notifications', this)">
                                <i class="fa-solid fa-bell w-5"></i> Thông báo & Bot
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('limits', this)">
                                <i class="fa-solid fa-gauge-high w-5"></i> Giới hạn
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('tutor', this)">
                                <i class="fa-solid fa-graduation-cap w-5"></i> Gia sư
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('shop', this)">
                                <i class="fa-solid fa-store w-5"></i> Cửa hàng
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('apis', this)">
                                <i class="fa-solid fa-plug w-5"></i> API & Tích hợp
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('ai', this)">
                                <i class="fa-solid fa-robot w-5"></i> Cấu hình AI
                            </a>
                        </li>
                        <li>
                            <a onclick="switchTab('streak', this)">
                                <i class="fa-solid fa-fire w-5"></i> Chuỗi điểm danh
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 space-y-6">
                <!-- TAB: Site Settings -->
                <div id="tab-site" class="settings-tab animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary grid place-items-center"><i class="fa-solid fa-circle-info"></i></span>
                                Thông tin cơ bản
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Tên Website</span></label>
                                    <input type="text" id="site_name" class="input input-bordered" value="<?= htmlspecialchars(getSetting('site_name', 'DocShare')) ?>">
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Logo</span></label>
                                    <div class="flex gap-4 items-center">
                                        <div class="w-12 h-12 rounded-lg bg-base-200 border border-base-300 grid place-items-center overflow-hidden">
                                            <img id="logo_preview" src="<?= htmlspecialchars(getSetting('site_logo', '/favicon.ico')) ?>" class="w-full h-full object-contain">
                                        </div>
                                        <input type="file" id="site_logo_file" class="file-input file-input-bordered file-input-sm w-full" onchange="previewLogo(this)">
                                        <input type="hidden" id="site_logo" value="<?= htmlspecialchars(getSetting('site_logo', '')) ?>">
                                    </div>
                                </div>
                                
                                <div class="form-control md:col-span-2">
                                    <label class="label">
                                        <span class="label-text font-medium">Ảnh Thumbnail/Preview (Open Graph)</span>
                                        <span class="badge badge-sm badge-info">1200x630px khuyến nghị</span>
                                    </label>
                                    <div class="flex gap-4 items-start">
                                        <div class="w-32 h-16 rounded-lg bg-base-200 border border-base-300 grid place-items-center overflow-hidden flex-shrink-0">
                                            <img id="og_image_preview" src="<?= htmlspecialchars(getSetting('og_image', getSetting('site_logo', '/favicon.ico'))) ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="flex-1">
                                            <input type="file" id="og_image_file" class="file-input file-input-bordered file-input-sm w-full" accept="image/jpeg,image/png,image/webp" onchange="previewOGImage(this)">
                                            <input type="hidden" id="og_image" value="<?= htmlspecialchars(getSetting('og_image', '')) ?>">
                                            <label class="label"><span class="label-text-alt opacity-60">Ảnh này sẽ hiển thị khi chia sẻ link lên <strong>tất cả</strong> social media: Facebook, Zalo, Telegram, Twitter, LinkedIn, Discord, WhatsApp...</span></label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-control md:col-span-2">
                                    <label class="label"><span class="label-text font-medium">Mô tả (SEO)</span></label>
                                    <textarea id="site_description" class="textarea textarea-bordered h-24"><?= htmlspecialchars(getSetting('site_description', '')) ?></textarea>
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Từ khóa (Keywords)</span></label>
                                    <input type="text" id="site_keywords" class="input input-bordered" placeholder="tài liệu, ebook, đồ án..." value="<?= htmlspecialchars(getSetting('site_keywords', '')) ?>">
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Tác giả (Author)</span></label>
                                    <input type="text" id="site_author" class="input input-bordered" value="<?= htmlspecialchars(getSetting('site_author', '')) ?>">
                                </div>
                                
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text font-medium">Facebook App ID</span>
                                        <span class="badge badge-sm badge-ghost">Tùy chọn</span>
                                    </label>
                                    <input type="text" id="fb_app_id" class="input input-bordered font-mono text-sm" placeholder="123456789012345" value="<?= htmlspecialchars(getSetting('fb_app_id', '')) ?>">
                                    <label class="label"><span class="label-text-alt opacity-60">Để trống nếu chưa có Facebook App</span></label>
                                </div>
                                
                                <div class="form-control md:col-span-2">
                                    <label class="label">
                                        <span class="label-text font-medium">Facebook Admin IDs</span>
                                        <span class="badge badge-sm badge-ghost">Tùy chọn</span>
                                    </label>
                                    <input type="text" id="fb_admins" class="input input-bordered font-mono text-sm" placeholder="100001234567890,100009876543210" value="<?= htmlspecialchars(getSetting('fb_admins', '')) ?>">
                                    <label class="label"><span class="label-text-alt opacity-60">ID Facebook của admin, cách nhau bằng dấu phẩy. Tìm ID tại: <a href="https://findmyfbid.com" target="_blank" class="link link-primary">findmyfbid.com</a></span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Notifications -->
                <div id="tab-notifications" class="settings-tab hidden animate-fade-in">
                    <!-- Telegram Config -->
                    <div class="card bg-base-100 shadow-sm border border-base-200 mb-6">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-bold flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-info/10 text-info grid place-items-center"><i class="fa-brands fa-telegram"></i></span>
                                    Telegram Bot
                                </h2>
                                <input type="checkbox" class="toggle toggle-info" id="telegram_enabled" <?= isSettingEnabled('telegram_enabled') ? 'checked' : '' ?>>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Bot Token</span></label>
                                    <input type="password" id="telegram_bot_token" class="input input-bordered font-mono text-sm" value="<?= htmlspecialchars(getSetting('telegram_bot_token', '')) ?>">
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Chat ID (Admin Group)</span></label>
                                    <input type="text" id="telegram_chat_id" class="input input-bordered font-mono text-sm" value="<?= htmlspecialchars(getSetting('telegram_chat_id', '')) ?>">
                                </div>
                            </div>
                            
                            <div class="form-control mb-6">
                                <label class="label"><span class="label-text">Admin Telegram IDs (Whitelist)</span></label>
                                <input type="text" id="telegram_admin_ids" class="input input-bordered font-mono text-sm" placeholder="ID1, ID2, ID3..." value="<?= htmlspecialchars(getSetting('telegram_admin_ids', '')) ?>">
                            </div>

                            <div class="bg-base-200/50 rounded-xl p-4 flex flex-wrap items-center gap-3">
                                <div class="font-medium text-sm mr-auto">Webhook Actions:</div>
                                <button onclick="setupWebhook('set')" class="btn btn-sm btn-primary">Cài Webhook</button>
                                <button onclick="setupWebhook('delete')" class="btn btn-sm btn-ghost text-error">Gỡ Webhook</button>
                                <button onclick="testTelegram()" class="btn btn-sm btn-ghost">Test Gửi Tin</button>
                            </div>
                            <div id="telegram-result" class="mt-2 text-sm empty:hidden"></div>
                        </div>
                    </div>

                    <!-- Push Settings -->
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-bold flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-warning/10 text-warning grid place-items-center"><i class="fa-solid fa-bell"></i></span>
                                    Kênh thông báo
                                </h2>
                                <label class="label cursor-pointer gap-2">
                                    <span class="label-text text-xs uppercase font-bold">Browser Push</span>
                                    <input type="checkbox" class="toggle toggle-success toggle-sm" id="notify_browser_push_enabled" <?= isSettingEnabled('notify_browser_push_enabled') ? 'checked' : '' ?>>
                                </label>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <thead>
                                        <tr class="bg-base-200/50">
                                            <th>Loại sự kiện</th>
                                            <th class="text-center w-24">Browser</th>
                                            <th class="text-center w-24">Telegram</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $events = [
                                            'new_document' => 'Tài liệu mới chờ duyệt',
                                            'report' => 'Báo cáo vi phạm mới',
                                            'system_alert' => 'Cảnh báo hệ thống',
                                            'new_tutor' => 'Đăng ký gia sư mới'
                                        ];
                                        foreach($events as $key => $label): ?>
                                        <tr>
                                            <td class="font-medium"><?= $label ?></td>
                                            <td class="text-center"><input type="checkbox" class="checkbox checkbox-xs checkbox-success" id="notify_<?= $key ?>_browser" <?= isSettingEnabled("notify_{$key}_browser") ? 'checked' : '' ?>></td>
                                            <td class="text-center"><input type="checkbox" class="checkbox checkbox-xs checkbox-info" id="notify_<?= $key ?>_telegram" <?= isSettingEnabled("notify_{$key}_telegram") ? 'checked' : '' ?>></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Limits -->
                <div id="tab-limits" class="settings-tab hidden animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-error/10 text-error grid place-items-center"><i class="fa-solid fa-gauge-simple-high"></i></span>
                                Giới hạn hệ thống
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Download (Thường)</span> <span class="badge badge-sm">KB/s</span></label>
                                    <input type="number" id="limit_download_speed_free" class="input input-bordered" value="<?= getSetting('limit_download_speed_free', 100) ?>">
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text text-warning">Download (Premium)</span> <span class="badge badge-warning badge-sm">VIP</span></label>
                                    <input type="number" id="limit_download_speed_premium" class="input input-bordered input-warning" value="<?= getSetting('limit_download_speed_premium', 500) ?>">
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Xem trước (Preview)</span> <span class="badge badge-sm">Trang</span></label>
                                    <input type="number" id="limit_preview_pages" class="input input-bordered" value="<?= getSetting('limit_preview_pages', 3) ?>">
                                    <label class="label"><span class="label-text-alt opacity-60">Số trang hiện thị cho người chưa mua</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Tutor Settings -->
                <div id="tab-tutor" class="settings-tab hidden animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-bold flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary grid place-items-center"><i class="fa-solid fa-graduation-cap"></i></span>
                                    Cấu hình Gia sư
                                </h2>
                                <label class="label cursor-pointer gap-2">
                                    <span class="label-text font-bold text-xs uppercase">Chống gian lận</span>
                                    <input type="checkbox" class="toggle toggle-primary" id="tutor_anti_abuse" <?= isSettingEnabled('tutor_anti_abuse') ? 'checked' : '' ?>>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                                <!-- BASIC -->
                                <div class="p-4 bg-base-200/50 rounded-xl space-y-3">
                                    <h3 class="font-bold text-sm uppercase opacity-50">Gói Cơ Bản (Basic)</h3>
                                    <div class="form-control">
                                        <label class="label"><span class="label-text">Chiết khấu (%)</span></label>
                                        <input type="number" id="tutor_commission_basic" class="input input-bordered input-sm" value="<?= getSetting('tutor_commission_basic', 25) ?>">
                                    </div>
                                    <div class="form-control">
                                        <label class="label"><span class="label-text">SLA (Giờ)</span></label>
                                        <input type="number" step="0.5" id="tutor_sla_basic" class="input input-bordered input-sm" value="<?= getSetting('tutor_sla_basic', 0.5) ?>">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="form-control">
                                            <label class="label"><span class="label-text">Min Price</span></label>
                                            <input type="number" id="tutor_min_basic" class="input input-bordered input-sm" value="<?= getSetting('tutor_min_basic', 25) ?>">
                                        </div>
                                        <div class="form-control">
                                            <label class="label"><span class="label-text">Max Price</span></label>
                                            <input type="number" id="tutor_max_basic" class="input input-bordered input-sm" value="<?= getSetting('tutor_max_basic', 35) ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- STANDARD -->
                                <div class="p-4 bg-base-200/50 rounded-xl space-y-3">
                                    <h3 class="font-bold text-sm uppercase opacity-50">Gói Tiêu Chuẩn (Standard)</h3>
                                    <div class="form-control">
                                        <label class="label"><span class="label-text">Chiết khấu (%)</span></label>
                                        <input type="number" id="tutor_commission_standard" class="input input-bordered input-sm" value="<?= getSetting('tutor_commission_standard', 20) ?>">
                                    </div>
                                    <div class="form-control">
                                        <label class="label"><span class="label-text">SLA (Giờ)</span></label>
                                        <input type="number" step="0.5" id="tutor_sla_standard" class="input input-bordered input-sm" value="<?= getSetting('tutor_sla_standard', 1) ?>">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="form-control">
                                            <label class="label"><span class="label-text">Min Price</span></label>
                                            <input type="number" id="tutor_min_standard" class="input input-bordered input-sm" value="<?= getSetting('tutor_min_standard', 35) ?>">
                                        </div>
                                        <div class="form-control">
                                            <label class="label"><span class="label-text">Max Price</span></label>
                                            <input type="number" id="tutor_max_standard" class="input input-bordered input-sm" value="<?= getSetting('tutor_max_standard', 60) ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- PREMIUM -->
                                <div class="p-4 bg-base-200/50 rounded-xl space-y-3">
                                    <h3 class="font-bold text-sm uppercase opacity-50 text-warning">Gói VIP (Premium)</h3>
                                    <div class="form-control">
                                        <label class="label"><span class="label-text">Chiết khấu (%)</span></label>
                                        <input type="number" id="tutor_commission_premium" class="input input-bordered input-sm" value="<?= getSetting('tutor_commission_premium', 15) ?>">
                                    </div>
                                    <div class="form-control">
                                        <label class="label"><span class="label-text">SLA (Giờ)</span></label>
                                        <input type="number" step="0.5" id="tutor_sla_premium" class="input input-bordered input-sm" value="<?= getSetting('tutor_sla_premium', 6) ?>">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="form-control">
                                            <label class="label"><span class="label-text">Min Price</span></label>
                                            <input type="number" id="tutor_min_premium" class="input input-bordered input-sm" value="<?= getSetting('tutor_min_premium', 60) ?>">
                                        </div>
                                        <div class="form-control">
                                            <label class="label"><span class="label-text">Max Price</span></label>
                                            <input type="number" id="tutor_max_premium" class="input input-bordered input-sm" value="<?= getSetting('tutor_max_premium', 120) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Shop Settings -->
                <div id="tab-shop" class="settings-tab hidden animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200 mb-6">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-success/10 text-success grid place-items-center"><i class="fa-solid fa-building-columns"></i></span>
                                Thông tin Thanh toán & Tỷ giá
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Tỷ giá VSD (VNĐ / 1 VSD)</span></label>
                                    <div class="join">
                                        <span class="join-item btn btn-disabled bg-base-200 border-base-300 min-h-[3rem]">1 VSD =</span>
                                        <input type="number" id="shop_exchange_rate" class="input input-bordered join-item w-full font-bold text-success" value="<?= getSetting('shop_exchange_rate', 1000) ?>">
                                        <span class="join-item btn btn-disabled bg-base-200 border-base-300 min-h-[3rem]">VNĐ</span>
                                    </div>
                                    <label class="label"><span class="label-text-alt opacity-60">Dùng để tính toán khi người dùng nạp tiền</span></label>
                                </div>
                            </div>
                            
                            <div class="divider"></div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Ngân hàng</span></label>
                                    <input type="text" id="shop_bank_name" class="input input-bordered" value="<?= htmlspecialchars(getSetting('shop_bank_name', 'MB BANK')) ?>">
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Số tài khoản</span></label>
                                    <input type="text" id="shop_bank_number" class="input input-bordered" value="<?= htmlspecialchars(getSetting('shop_bank_number', '999999999')) ?>">
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Chủ tài khoản</span></label>
                                    <input type="text" id="shop_bank_owner" class="input input-bordered" value="<?= htmlspecialchars(getSetting('shop_bank_owner', 'VietStuDocs Admin')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary grid place-items-center"><i class="fa-solid fa-box-archive"></i></span>
                                Gói nạp VSD
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <thead>
                                        <tr class="bg-base-200/50">
                                            <th>Gói</th>
                                            <th>Giá (VNĐ)</th>
                                            <th>Topup VSD</th>
                                            <th>Bonus VSD</th>
                                            <th>Phổ biến</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for($i=1; $i<=5; $i++): ?>
                                        <tr>
                                            <td class="font-bold">Gói #<?= $i ?></td>
                                            <td><input type="number" id="shop_pkg<?= $i ?>_price" class="input input-ghost input-xs w-24 bg-base-100" value="<?= getSetting("shop_pkg{$i}_price") ?>"></td>
                                            <td><input type="number" id="shop_pkg<?= $i ?>_topup" class="input input-ghost input-xs w-20 bg-base-100" value="<?= getSetting("shop_pkg{$i}_topup") ?>"></td>
                                            <td><input type="number" id="shop_pkg<?= $i ?>_bonus" class="input input-ghost input-xs w-20 bg-base-100" value="<?= getSetting("shop_pkg{$i}_bonus") ?>"></td>
                                            <td><input type="checkbox" id="shop_pkg<?= $i ?>_popular" class="checkbox checkbox-xs checkbox-primary" <?= isSettingEnabled("shop_pkg{$i}_popular") ? 'checked' : '' ?>></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: APIs -->
                <div id="tab-apis" class="settings-tab hidden animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-secondary/10 text-secondary grid place-items-center"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                Dịch vụ chuyển đổi
                            </h2>
                            
                            <!-- CloudConvert -->
                            <div class="form-control mb-6">
                                <label class="label"><span class="label-text font-medium">CloudConvert API Key</span></label>
                                <div class="join">
                                    <span class="join-item btn btn-disabled bg-base-200 border-base-300">KEY</span>
                                    <input type="password" id="cloudconvert_api_key" class="input input-bordered join-item w-full font-mono text-sm" value="<?= htmlspecialchars(getSetting('cloudconvert_api_key', '')) ?>">
                                </div>
                                <label class="label"><span class="label-text-alt opacity-60">Dùng để tạo thumbnail và convert file docx</span></label>
                            </div>
                            
                            <!-- Adobe Status -->
                            <?php $has_adobe = file_exists(__DIR__ . '/../API/pdfservices-api-credentials.json'); ?>
                            <div class="alert <?= $has_adobe ? 'alert-success bg-success/10 text-success border-success/20' : 'alert-error bg-error/10 text-error border-error/20' ?> items-start">
                                <i class="fa-solid <?= $has_adobe ? 'fa-file-circle-check' : 'fa-file-circle-xmark' ?> text-xl mt-1"></i>
                                <div>
                                    <h3 class="font-bold">Adobe PDF Services</h3>
                                    <div class="text-xs mt-1">
                                        Trạng thái: <b><?= $has_adobe ? 'Đã kích hoạt' : 'Chưa cấu hình' ?></b>
                                        <br>File config: <code>API/pdfservices-api-credentials.json</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: AI Settings -->
                <div id="tab-ai" class="settings-tab hidden animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary grid place-items-center"><i class="fa-solid fa-robot"></i></span>
                                Cấu hình AI Review
                            </h2>
                            
                            <!-- Auth Config -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 p-4 bg-base-200/30 rounded-2xl border border-base-300/50">
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">API Key (Chat/Assistants)</span></label>
                                    <input type="password" id="ai_openai_key" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_openai_key', getenv('GPT_KEY')) ?>" placeholder="sk-proj-...">
                                    <label class="label"><span class="label-text-alt opacity-60">Dùng cho AI Review (Chat, File, Assistants)</span></label>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Admin Key (Billing/Usage)</span></label>
                                    <input type="password" id="ai_openai_admin_key" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_openai_admin_key', getenv('ADMIN_GPT_KEY')) ?>" placeholder="sk-admin-...">
                                    <label class="label"><span class="label-text-alt opacity-60">Bắt buộc để xem chi phí (theo docs OpenAI)</span></label>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Organization ID</span></label>
                                    <input type="text" id="ai_openai_org_id" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_openai_org_id', getenv('GPT_ORG')) ?>" placeholder="org-...">
                                    <label class="label"><span class="label-text-alt opacity-60">Lấy từ Settings > Organization</span></label>
                                </div>
                            </div>

                            <!-- Model Config -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Model AI Judge (Vòng 1)</span></label>
                                    <div class="join">
                                        <select id="ai_model_judge" class="select select-bordered join-item w-full">
                                            <?php $m1 = getSetting('ai_model_judge', 'gpt-4o'); ?>
                                            <?php if(empty($ai_models)): ?>
                                                <option value="gpt-4o" <?= $m1 == 'gpt-4o' ? 'selected' : '' ?>>GPT-4o (Default)</option>
                                                <option value="gpt-4o-mini" <?= $m1 == 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o-mini</option>
                                            <?php else: ?>
                                                <?php foreach($ai_models as $model): ?>
                                                    <option value="<?= $model['id'] ?>" <?= $m1 == $model['id'] ? 'selected' : '' ?>><?= $model['id'] ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <button onclick="refreshAIModels()" id="btn-refresh-models" class="btn btn-square join-item" title="Cập nhật danh sách Model">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </div>
                                    <label class="label"><span class="label-text-alt opacity-60">Model dùng để phân tích tài liệu chuyên sâu</span></label>
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Model AI Moderator (Vòng 2)</span></label>
                                    <select id="ai_model_moderator" class="select select-bordered w-full">
                                        <?php $m2 = getSetting('ai_model_moderator', 'gpt-4o-mini'); ?>
                                        <?php if(empty($ai_models)): ?>
                                            <option value="gpt-4o-mini" <?= $m2 == 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o-mini (Default)</option>
                                            <option value="gpt-4o" <?= $m2 == 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                        <?php else: ?>
                                            <?php foreach($ai_models as $model): ?>
                                                <option value="<?= $model['id'] ?>" <?= $m2 == $model['id'] ? 'selected' : '' ?>><?= $model['id'] ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <label class="label"><span class="label-text-alt opacity-60">Model dùng để hậu kiểm và chấm điểm cuối</span></label>
                                </div>
                            </div>

                            <!-- Balance Check -->
                            <div class="bg-base-200/50 rounded-2xl p-6 border border-base-300/50">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="font-bold text-base">Tài khoản & Hạn ngạch</h3>
                                        <p class="text-xs opacity-60 mt-0.5" id="ai-key-display">Kiểm tra số tiền còn lại của OpenAI API Key</p>
                                    </div>
                                    <button onclick="checkAIBalance()" id="btn-check-balance" class="btn btn-sm btn-outline btn-primary">
                                        <i class="fa-solid fa-rotate mr-1"></i> Cập nhật
                                    </button>
                                </div>
                                
                                <div id="ai-balance-results" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <div class="bg-base-100 p-3 rounded-xl border border-base-200">
                                        <div class="text-[10px] uppercase opacity-50 font-bold mb-1">Admin Key</div>
                                        <div class="font-bold text-sm" id="token-status">---</div>
                                    </div>
                                    <div class="bg-base-100 p-3 rounded-xl border border-base-200">
                                        <div class="text-[10px] uppercase opacity-50 font-bold mb-1">Đã dùng (Tháng này)</div>
                                        <div class="font-bold text-sm text-primary" id="token-usage">---</div>
                                    </div>
                                    <div class="bg-base-100 p-3 rounded-xl border border-base-200">
                                        <div class="text-[10px] uppercase opacity-50 font-bold mb-1">Nguồn dữ liệu</div>
                                        <div class="font-bold text-sm" id="token-source">---</div>
                                    </div>
                                </div>

                                <!-- Detailed Usage Log (Hidden by default) -->
                                <div id="detailed-ai-usage" class="mt-6 hidden animate-fade-in">
                                    <div class="divider text-[10px] uppercase opacity-30">Official Usage Logs (Admin Key Required)</div>
                                    <div class="bg-[#1a1c23] rounded-xl p-4 overflow-hidden border border-white/5 shadow-inner">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-[10px] font-mono text-white/40">GET /v1/organization/usage/completions?start_time=...</span>
                                            <span class="badge badge-ghost badge-xs opacity-50">Official API</span>
                                        </div>
                                        <pre id="ai-usage-raw" class="text-[10px] font-mono text-emerald-400 overflow-y-auto max-h-[300px] leading-relaxed">Awaiting data...</pre>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between">
                                    <div class="text-[10px] opacity-40 italic">
                                        * Dữ liệu từ /v1/organization/costs (yêu cầu Admin API Key)
                                    </div>
                                    <button onclick="viewDetailedAIUsage()" class="btn btn-xs btn-ghost gap-1 opacity-50 hover:opacity-100 transition-all">
                                        <i class="fa-solid fa-bug text-[8px]"></i> Xem log chi tiết
                                    </button>
                                </div>
                            </div>

                            <!-- AI Pricing Configuration -->
                            <div class="bg-base-200/50 rounded-2xl p-6 border border-base-300/50 mt-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="font-bold text-base">Cấu hình Giá VSD theo Điểm AI</h3>
                                        <p class="text-xs opacity-60 mt-0.5">Quy đổi điểm AI (0-100) thành giá VSD tự động</p>
                                    </div>
                                    <div class="badge badge-ghost text-xs">1 VSD = <?= getSetting('shop_vsd_rate', 500) ?> VNĐ</div>
                                </div>

                                <!-- Thresholds -->
                                <div class="mb-4">
                                    <div class="text-xs font-bold uppercase opacity-50 mb-2">Ngưỡng điểm</div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div class="form-control">
                                            <label class="label py-0"><span class="label-text text-xs">Rejected (dưới)</span></label>
                                            <input type="number" id="ai_price_threshold_reject" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_price_threshold_reject', 45) ?>" min="0" max="100">
                                        </div>
                                        <div class="form-control">
                                            <label class="label py-0"><span class="label-text text-xs">Conditional (dưới)</span></label>
                                            <input type="number" id="ai_price_threshold_conditional" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_price_threshold_conditional', 60) ?>" min="0" max="100">
                                        </div>
                                        <div class="form-control">
                                            <label class="label py-0"><span class="label-text text-xs">Good (từ)</span></label>
                                            <input type="number" id="ai_price_threshold_good" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_price_threshold_good', 75) ?>" min="0" max="100">
                                        </div>
                                        <div class="form-control">
                                            <label class="label py-0"><span class="label-text text-xs">Excellent (từ)</span></label>
                                            <input type="number" id="ai_price_threshold_excellent" class="input input-bordered input-sm w-full" value="<?= getSetting('ai_price_threshold_excellent', 90) ?>" min="0" max="100">
                                        </div>
                                    </div>
                                </div>

                                <!-- Price Ranges -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Conditional Price -->
                                    <div class="bg-warning/10 p-3 rounded-xl border border-warning/20">
                                        <div class="text-xs font-bold text-warning mb-2">Xem Xét (45-59 điểm)</div>
                                        <div class="flex gap-2">
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Min VSD</span></label>
                                                <input type="number" id="ai_price_conditional_min" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_conditional_min', 1) ?>">
                                            </div>
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Max VSD</span></label>
                                                <input type="number" id="ai_price_conditional_max" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_conditional_max', 3) ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Standard Price -->
                                    <div class="bg-info/10 p-3 rounded-xl border border-info/20">
                                        <div class="text-xs font-bold text-info mb-2">Chấp Nhận (60-74 điểm)</div>
                                        <div class="flex gap-2">
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Min VSD</span></label>
                                                <input type="number" id="ai_price_standard_min" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_standard_min', 5) ?>">
                                            </div>
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Max VSD</span></label>
                                                <input type="number" id="ai_price_standard_max" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_standard_max', 10) ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Good Price -->
                                    <div class="bg-success/10 p-3 rounded-xl border border-success/20">
                                        <div class="text-xs font-bold text-success mb-2">Tốt (75-89 điểm)</div>
                                        <div class="flex gap-2">
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Min VSD</span></label>
                                                <input type="number" id="ai_price_good_min" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_good_min', 12) ?>">
                                            </div>
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Max VSD</span></label>
                                                <input type="number" id="ai_price_good_max" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_good_max', 20) ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Excellent Price -->
                                    <div class="bg-primary/10 p-3 rounded-xl border border-primary/20">
                                        <div class="text-xs font-bold text-primary mb-2">Xuất Sắc (90-100 điểm)</div>
                                        <div class="flex gap-2">
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Min VSD</span></label>
                                                <input type="number" id="ai_price_excellent_min" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_excellent_min', 25) ?>">
                                            </div>
                                            <div class="form-control flex-1">
                                                <label class="label py-0"><span class="label-text text-[10px]">Max VSD</span></label>
                                                <input type="number" id="ai_price_excellent_max" class="input input-bordered input-xs w-full" value="<?= getSetting('ai_price_excellent_max', 50) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 text-[10px] opacity-50 italic">
                                    * Giá VSD được tính tuyến tính trong mỗi khoảng điểm. Thay đổi sẽ áp dụng cho tài liệu mới được AI duyệt.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Streak Settings -->
                <div id="tab-streak" class="settings-tab hidden animate-fade-in">
                    <div class="card bg-base-100 shadow-sm border border-base-200">
                        <div class="card-body">
                            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-orange-100 text-orange-500 grid place-items-center"><i class="fa-solid fa-fire"></i></span>
                                Cấu hình Phần thưởng Chuỗi
                            </h2>
                            <p class="text-xs opacity-60 mb-6">Thiết lập số VSD người dùng nhận được khi điểm danh hàng ngày theo chu kỳ 7 ngày.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Ngày 1, 2, 3 (Mỗi ngày)</span></label>
                                    <div class="join">
                                        <input type="number" id="streak_reward_1_3" class="input input-bordered join-item w-full" value="<?= htmlspecialchars(getSetting('streak_reward_1_3', 1)) ?>">
                                        <span class="join-item btn btn-disabled bg-base-200">VSD</span>
                                    </div>
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium text-primary">Ngày 4 (Mốc giữa)</span></label>
                                    <div class="join">
                                        <input type="number" id="streak_reward_4" class="input input-bordered join-item w-full border-primary/30" value="<?= htmlspecialchars(getSetting('streak_reward_4', 2)) ?>">
                                        <span class="join-item btn btn-disabled bg-primary/10 text-primary border-primary/30">VSD</span>
                                    </div>
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium">Ngày 5, 6 (Mỗi ngày)</span></label>
                                    <div class="join">
                                        <input type="number" id="streak_reward_5_6" class="input input-bordered join-item w-full" value="<?= htmlspecialchars(getSetting('streak_reward_5_6', 1)) ?>">
                                        <span class="join-item btn btn-disabled bg-base-200">VSD</span>
                                    </div>
                                </div>
                                
                                <div class="form-control">
                                    <label class="label"><span class="label-text font-medium text-orange-500">Ngày 7 (Mốc cuối tuần)</span></label>
                                    <div class="join">
                                        <input type="number" id="streak_reward_7" class="input input-bordered join-item w-full border-orange-500/30" value="<?= htmlspecialchars(getSetting('streak_reward_7', 3)) ?>">
                                        <span class="join-item btn btn-disabled bg-orange-500/10 text-orange-500 border-orange-500/30">VSD</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab handling
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-top toast-end z-[9999] p-4';
    
    const icon = type === 'success' ? 'fa-check' : 'fa-triangle-exclamation';
    const accentClass = type === 'success' ? 'success' : 'error';
    const gradient = type === 'success' ? 'from-emerald-400 to-teal-500' : 'from-rose-500 to-orange-600';

    toast.innerHTML = `
        <div class="alert bg-[#1a1c23]/95 backdrop-blur-xl border border-white/5 shadow-[0_20px_50px_rgba(0,0,0,0.3)] animate-fade-in min-w-[350px] p-0 overflow-hidden rounded-2xl">
            <div class="flex items-center gap-4 p-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br ${gradient} text-white flex items-center justify-center shadow-lg shadow-${accentClass}/20 shrink-0">
                    <i class="fa-solid ${icon} text-xl"></i>
                </div>
                <div class="flex-1">
                    <div class="font-black text-xs tracking-widest text-white/40 uppercase">${type === 'success' ? 'Success Execution' : 'System Error'}</div>
                    <div class="text-sm text-white/90 font-bold leading-tight mt-0.5">${message}</div>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r ${gradient}" id="toast-progress" style="width: 100%; transition: width 1.5s linear;"></div>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        const bar = toast.querySelector('#toast-progress');
        if(bar) bar.style.width = '0%';
    }, 50);

    setTimeout(() => {
        const alert = toast.querySelector('.alert');
        if(alert) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(30px) scale(0.9)';
            alert.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
        }
        setTimeout(() => toast.remove(), 500);
    }, 2000);
}

function switchTab(tabId, el = null) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.add('hidden'));
    document.getElementById('tab-' + tabId).classList.remove('hidden');
    
    document.querySelectorAll('.menu a').forEach(a => a.classList.remove('active'));
    if(el) {
        el.classList.add('active');
    } else {
        // Find link by onclick attribute
        const link = document.querySelector(`.menu a[onclick*="'${tabId}'"]`);
        if(link) link.classList.add('active');
    }
    
    // Update hash without scrolling
    history.replaceState(null, null, '#tab-' + tabId);
}

// Init tab from hash
document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if(hash && hash.startsWith('#tab-')) {
        const tabId = hash.substring(5); // remove #tab-
        const tabEl = document.getElementById('tab-' + tabId);
        if(tabEl) {
            switchTab(tabId);
        }
    }
});

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('logo_preview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function previewOGImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('og_image_preview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function saveSettings() {
    const btn = document.querySelector('button[onclick="saveSettings()"]');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner"></span> Đang lưu...';

    const formData = new FormData();
    // Collect all unique IDs to avoid duplicates if any
    const processedIds = new Set();
    
    document.querySelectorAll('input, textarea, select').forEach(el => {
        if (!el.id || processedIds.has(el.id)) return;
        processedIds.add(el.id);

        if (el.type === 'checkbox') {
            formData.append(el.id, el.checked ? 'on' : 'off');
        } else if (el.type === 'file') {
            if(el.files[0]) formData.append(el.id, el.files[0]);
        } else {
            formData.append(el.id, el.value);
        }
    });

    fetch('settings.php', { 
        method: 'POST', 
        body: formData 
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 2100);
            } else {
                showToast(data.message || 'Lỗi lưu cài đặt', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Lỗi kết nối server!', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
}

function setupWebhook(action) {
    const formData = new FormData();
    formData.append('telegram_webhook_action', action);
    callApi(formData);
}

function testTelegram() {
    const formData = new FormData();
    formData.append('test_telegram', '1');
    callApi(formData);
}

function callApi(formData) {
    const resDiv = document.getElementById('telegram-result');
    resDiv.innerHTML = '<span class="loading loading-dots loading-xs"></span> Đang xử lý...';
    resDiv.classList.remove('hidden');
    
    fetch('settings.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            resDiv.innerHTML = `<span class="${data.success ? 'text-success' : 'text-error'} font-semibold text-xs">${data.message}</span>`;
        });
}

function checkAIBalance() {
    const btn = document.getElementById('btn-check-balance');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';

    fetch('settings.php', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ check_ai_balance: '1' }) 
    })
        .then(r => r.json())
        .then(data => {
            // Display API keys info
            if(data.api_key_used) {
                document.getElementById('ai-key-display').innerHTML = `API: <span class="font-mono text-[9px] opacity-70">${data.api_key_used}</span> | Admin: <span class="font-mono text-[9px] opacity-70">${data.admin_key_used || 'N/A'}</span>`;
            }

            // Admin Key Status
            if (data.has_admin_key) {
                document.getElementById('token-status').innerHTML = '<span class="text-success font-bold">Configured</span>';
            } else {
                document.getElementById('token-status').innerHTML = '<span class="text-warning font-bold cursor-help" title="Cần cấu hình ADMIN_GPT_KEY để xem chi phí">Not Set</span>';
            }

            // Usage
            if (data.success) {
                const usage = parseFloat(data.total_usage) || 0;
                let usageHtml = '$' + usage.toFixed(4);
                if (data.source === 'organization/costs') {
                    usageHtml += ' <span class="badge badge-xs badge-success">Live</span>';
                }
                document.getElementById('token-usage').innerHTML = usageHtml;
            } else if (data.error_billing) {
                document.getElementById('token-usage').innerHTML = `<span class="text-error cursor-help" title="${data.error_billing}">Error</span>`;
            }

            // Source
            document.getElementById('token-source').innerHTML = data.source === 'organization/costs' 
                ? '<span class="text-success">/organization/costs</span>' 
                : `<span class="text-warning">${data.source || 'unknown'}</span>`;
        })
        .catch(err => {
            document.getElementById('token-status').innerHTML = '<span class="text-error">Connection Error</span>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
}

function refreshAIModels() {
    const btn = document.getElementById('btn-refresh-models');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';

    const formData = new FormData();
    formData.append('refresh_ai_models', '1');

    fetch('settings.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Đã cập nhật ' + data.count + ' models.', 'success');
                setTimeout(() => location.reload(), 1550);
            } else {
                showToast('Lỗi: ' + data.error, 'error');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
}

function viewDetailedAIUsage() {
    const section = document.getElementById('detailed-ai-usage');
    const display = document.getElementById('ai-usage-raw');
    section.classList.toggle('hidden');
    if (section.classList.contains('hidden')) return;

    display.textContent = '// Đang truy vấn dữ liệu tiêu thụ thực tế từ OpenAI...';

    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ get_detailed_ai_usage: '1' })
    })
    .then(r => r.json())
    .then(data => {
        if(data.data && data.data.length === 0) {
            display.textContent = "// [INFO] Không tìm thấy dữ liệu sử dụng cho ngày hôm nay (" + new Date().toLocaleDateString() + ").\n// Điều này có nghĩa là bạn chưa chạy yêu cầu AI nào hoặc OpenAI chưa cập nhật log.\n\n" + JSON.stringify(data, null, 2);
        } else {
            display.textContent = JSON.stringify(data, null, 2);
        }
    })
    .catch(err => {
        display.textContent = '// [ERROR] Không thể kết nối tới OpenAI Billing API:\n' + err.message;
    });
}
</script>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
