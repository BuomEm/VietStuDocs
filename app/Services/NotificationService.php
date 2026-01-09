<?php
namespace App\Services;

use App\Support\Database;

/**
 * Notification Service - Quản lý thông báo hệ thống và Telegram
 */
class NotificationService
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Gửi thông báo cho người dùng
     */
    public function sendToUser($userId, $type, $message, $refId = null)
    {
        $userId = intval($userId);
        $refId = $refId ? intval($refId) : 'NULL';
        
        return $this->db->query("INSERT INTO notifications (user_id, type, ref_id, message, created_at) 
                                 VALUES ($userId, '$type', $refId, '$message', NOW())");
    }

    /**
     * Gửi thông báo Telegram cho Admin
     */
    public function sendTelegram($message, $type = null, $buttons = [])
    {
        // Chức năng này sẽ sử dụng các hàm hiện có hoặc tích hợp thư viện Telegram
        // Để an toàn trong bước refactor này, tôi sẽ wrap lại hàm sendTelegramNotification hiện có
        if (function_exists('sendTelegramNotification')) {
            return sendTelegramNotification($message, $type, $buttons);
        }
        return false;
    }

    /**
     * Gửi thông báo cho tất cả admin
     */
    public function notifyAdmins($type, $message, $documentId = null)
    {
        $admins = $this->db->get_list("SELECT id FROM users WHERE role='admin'");
        foreach ($admins as $admin) {
            $this->sendToAdmin($admin['id'], $type, $message, $documentId);
        }
    }

    private function sendToAdmin($adminId, $type, $message, $documentId)
    {
        $adminId = intval($adminId);
        $documentId = $documentId ? intval($documentId) : 'NULL';
        
        return $this->db->query("INSERT INTO admin_notifications (admin_id, notification_type, document_id, message, created_at) 
                                 VALUES ($adminId, '$type', $documentId, '$message', NOW())");
    }
}

