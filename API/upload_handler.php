<?php
/**
 * VIETSTUDOCS REMOTE UPLOAD API
 * Place this file at: https://vietstudocs.site/API/upload_handler.php
 */
header('Content-Type: application/json');

// 1. Kiểm tra API Key (Phải khớp với key trong config/file.php của bản Desktop)
$api_key = $_POST['api_key'] ?? '';
$expected_key = 'VSD_SECRET_API_KEY_2024';

if ($api_key !== $expected_key) {
    echo json_encode(['success' => false, 'message' => 'Truy cập bị từ chối: API Key không hợp lệ']);
    exit;
}

// 2. Kiểm tra tệp tải lên
if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Không có tệp nào được gửi lên']);
    exit;
}

$file = $_FILES['file'];
$subdir = $_POST['subdir'] ?? ''; // Ví dụ: 'avatars' hoặc 'thumbnails'
$upload_base = '../uploads/';
$target_dir = $upload_base . ($subdir ? rtrim($subdir, '/') . '/' : '');

// 3. Tạo thư mục nếu chưa có
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// 4. Tạo tên tệp duy nhất để tránh trùng lặp
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$prefix = ($subdir === 'avatars') ? 'avatar_' : (($subdir === 'thumbnails') ? 'thumb_' : 'vsd_');
$unique_name = $prefix . uniqid() . '_' . time() . '.' . $ext;
$target_file = $target_dir . $unique_name;

// 5. Lưu tệp
if (move_uploaded_file($file['tmp_name'], $target_file)) {
    echo json_encode([
        'success' => true,
        'file_name' => ($subdir ? rtrim($subdir, '/') . '/' : '') . $unique_name,
        'original_name' => $file['name']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi server: Không thể lưu tệp vào thư mục uploads']);
}