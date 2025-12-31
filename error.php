<?php
// error_page.php - Chrome style error page
$error_code = $_GET['code'] ?? 404;
$valid_codes = [
    400 => ['code' => '400', 'title' => 'Yêu cầu không hợp lệ', 'desc' => 'Máy chủ không thể xử lý yêu cầu.'],
    401 => ['code' => '401', 'title' => 'Chưa xác thực', 'desc' => 'Vui lòng đăng nhập để tiếp tục.'],
    403 => ['code' => '403', 'title' => 'Quyền truy cập bị từ chối', 'desc' => 'Bạn không có quyền truy cập trang này.'],
    404 => ['code' => '404', 'title' => 'Không tìm thấy trang', 'desc' => 'URL được yêu cầu không tồn tại trên máy chủ này.'],
    500 => ['code' => '500', 'title' => 'Lỗi máy chủ nội bộ', 'desc' => 'Đã xảy ra lỗi trên máy chủ.'],
    'session_expired' => ['code' => 'ERR_SESSION_EXPIRED', 'title' => 'Trang này hiện không hoạt động', 'desc' => 'Phiên đăng nhập của bạn đã hết hạn hoặc không hợp lệ.']
];

$info = $valid_codes[$error_code] ?? $valid_codes[404];

// If custom message passed
if (isset($_GET['msg'])) {
    $info['desc'] = htmlspecialchars($_GET['msg']);
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $info['title'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #202124;
            color: #bdc1c6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 600px;
            padding: 24px;
            text-align: center;
        }
        .icon {
            font-size: 72px;
            margin-bottom: 24px;
            color: #9aa0a6;
            display: flex;
            justify-content: center;
        }
        h1 {
            font-size: 24px;
            font-weight: 500;
            color: #e8eaed;
            margin-bottom: 16px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .error-code {
            color: #5f6368;
            font-size: 13px;
            margin-top: 32px;
            text-transform: uppercase;
        }
        .btn {
            background-color: #8ab4f8;
            color: #202124;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
            display: inline-block;
        }
        .btn:hover {
            background-color: #a6c5fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <h1><?= $info['title'] ?></h1>
        <p>
            <?= $_SERVER['HTTP_HOST'] ?? 'Website' ?> <?= strtolower($info['desc']) ?>
            <br>
            <?php if($error_code == 'session_expired'): ?>
                Hãy thử đăng nhập lại.
            <?php else: ?>
                Hãy thử kiểm tra lại URL hoặc kết nối mạng.
            <?php endif; ?>
        </p>
        
        <div style="margin-top: 32px;">
            <a href="javascript:location.reload()" class="btn">Tải lại</a>
            <?php if($error_code == 'session_expired' || $error_code == 403 || $error_code == 401): ?>
                <a href="/login.php" class="btn" style="background-color: transparent; border: 1px solid #5f6368; color: #8ab4f8; margin-left: 10px;">Đăng nhập</a>
            <?php else: ?>
                <a href="/" class="btn" style="background-color: transparent; border: 1px solid #5f6368; color: #8ab4f8; margin-left: 10px;">Trang chủ</a>
            <?php endif; ?>
        </div>

        <div class="error-code">
            <?= $info['code'] ?>
        </div>
    </div>
</body>
</html>
