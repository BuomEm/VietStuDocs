<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';

if (!hasAdminAccess()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    exit;
}

$action = $_POST['action'];

function is_animated_gif($filename) {
    if (!($fh = @fopen($filename, 'rb'))) return false;
    $count = 0;
    while (!feof($fh) && $count < 2) {
        $chunk = fread($fh, 1024 * 100); // 100KB chunks
        $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
    }
    fclose($fh);
    return $count > 1;
}

function is_animated_webp($filename) {
    if (!($fh = @fopen($filename, 'rb'))) return false;
    $header = fread($fh, 100);
    fclose($fh);
    return (strpos($header, 'ANIM') !== false);
}

function process_static_image($srcPath, $destPath, $maxWidth = 128, $maxHeight = 128) {
    $info = getimagesize($srcPath);
    if (!$info) return false;
    list($width, $height, $type) = $info;

    switch ($type) {
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($srcPath); break;
        case IMAGETYPE_PNG: $src = imagecreatefrompng($srcPath); break;
        case IMAGETYPE_WEBP: $src = imagecreatefromwebp($srcPath); break;
        default: return false;
    }

    if (!$src) return false;

    // Maintain aspect ratio
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio < 1) {
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Attempt to save with high quality, then reduce if over 256KB
    $quality = 90;
    do {
        imagewebp($dst, $destPath, $quality);
        if (filesize($destPath) <= 256 * 1024 || $quality <= 20) break;
        $quality -= 10;
    } while ($quality > 10);
    
    imagedestroy($src);
    imagedestroy($dst);
    
    return true;
}

switch ($action) {
    case 'add':
        $name = strtolower(trim($_POST['name']));
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            echo json_encode(['success' => false, 'message' => 'Tên emoji chỉ được phép chứa chữ thường, số và dấu gạch dưới.']);
            exit;
        }

        $shortcode = ':' . $name . ':';

        $exists = $VSD->get_row("SELECT id FROM emojis WHERE name = '$name' OR shortcode = '$shortcode'");
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Emoji đã tồn tại.']);
            exit;
        }

        if (!isset($_FILES['emoji_file']) || $_FILES['emoji_file']['error'] !== 0) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng chọn file.']);
            exit;
        }

        $file = $_FILES['emoji_file'];
        $tempPath = $file['tmp_name'];
        $mime = $file['type'];
        
        $isAnimated = false;
        if ($mime === 'image/gif') {
            $isAnimated = is_animated_gif($tempPath);
        } elseif ($mime === 'image/webp') {
            $isAnimated = is_animated_webp($tempPath);
        }

        // Limit size to 256KB for animated files (since we don't compress them)
        if ($isAnimated && $file['size'] > 256 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ảnh động quá lớn (tối đa 256KB).']);
            exit;
        }

        $uploadDir = __DIR__ . '/../assets/emojis/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $finalPath = '';
        if ($isAnimated) {
            $ext = ($mime === 'image/gif') ? 'gif' : 'webp';
            $finalFileName = $name . '_' . time() . '.' . $ext;
            if (move_uploaded_file($tempPath, $uploadDir . $finalFileName)) {
                $finalPath = '/assets/emojis/' . $finalFileName;
            }
        } else {
            $finalFileName = $name . '_' . time() . '.webp';
            if (process_static_image($tempPath, $uploadDir . $finalFileName)) {
                $finalPath = '/assets/emojis/' . $finalFileName;
            }
        }

        if ($finalPath) {
            $VSD->insert('emojis', [
                'name' => $name,
                'shortcode' => $shortcode,
                'file_path' => $finalPath,
                'is_animated' => $isAnimated ? 1 : 0,
                'is_active' => 1
            ]);
            echo json_encode(['success' => true, 'message' => 'Đã thêm emoji!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi xử lý file.']);
        }
        break;

    case 'toggle':
        $id = (int)$_POST['id'];
        $active = (int)$_POST['active'];
        $VSD->query("UPDATE emojis SET is_active = $active WHERE id = $id");
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id = (int)$_POST['id'];
        $emoji = $VSD->get_row("SELECT file_path FROM emojis WHERE id = $id");
        if ($emoji) {
            $fullPath = __DIR__ . '/../' . ltrim($emoji['file_path'], '/');
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            $VSD->query("DELETE FROM emojis WHERE id = $id");
            echo json_encode(['success' => true, 'message' => 'Đã xóa emoji.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy emoji.']);
        }
        break;
}
