<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';

// Ensure user is admin
redirectIfNotAdmin();

// [Logic xử lý POST request giữ nguyên như cũ để đảm bảo tính năng hoạt động]
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
    
    if ($input) {
        $success = true;
        foreach ($input as $name => $value) {
            $category = 'site';
            if (strpos($name, 'notify_') === 0 || strpos($name, 'telegram_') === 0) $category = 'notifications';
            elseif (strpos($name, 'limit_') === 0) $category = 'limits';
            elseif (strpos($name, 'cloudconvert_') === 0) $category = 'apis';
            elseif (strpos($name, 'tutor_') === 0) $category = 'tutor';
            elseif (strpos($name, 'shop_') === 0) $category = 'shop';
            setSetting($name, $value, $category);
        }
        $response['success'] = true;
        $response['message'] = 'Đã lưu cài đặt thành công!';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Telegram Test Logic
if (isset($_POST['test_telegram'])) {
    require_once __DIR__ . '/../config/telegram_notifications.php';
    require_once __DIR__ . '/../config/notifications.php';
    $buttons = [['text' => 'Truy cập Admin', 'url' => getBaseUrl() . '/admin/']];
    $result = sendTelegramNotification("🚨 <b>TEST KẾT NỐI</b>\nKết nối thành công!", 'system_alert', $buttons);
    echo json_encode(['success' => $result['success'], 'message' => $result['success'] ? 'Gửi thành công!' : 'Thất bại.']);
    exit;
}

// Webhook Logic
if (isset($_POST['telegram_webhook_action'])) {
    $action = $_POST['telegram_webhook_action'];
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
            </div>
        </div>
    </div>
</div>

<script>
// Tab handling
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

function saveSettings() {
    const btn = document.querySelector('button[onclick="saveSettings()"]');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner"></span> Đang lưu...';

    const formData = new FormData();
    document.querySelectorAll('input, textarea').forEach(el => {
        if (!el.id) return;
        if (el.type === 'checkbox') formData.append(el.id, el.checked ? 'on' : 'off');
        else if (el.type === 'file') { if(el.files[0]) formData.append(el.id, el.files[0]); }
        else formData.append(el.id, el.value);
    });

    fetch('settings.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message); // Replace with custom toast if available
        })
        .catch(() => alert('Lỗi kết nối!'))
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
</script>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
