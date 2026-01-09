<?php
namespace App\Modules\Documents\Services;

use App\Support\Database;

/**
 * Document Service - Thuộc Module Documents
 */
class DocumentService
{
    private $db;
    private $uploadDir;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->uploadDir = D_ROOT . '/storage/uploads/';
    }

    public function upload($file)
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueName = uniqid() . '_' . time() . '.' . $ext;
        $path = $this->uploadDir . $uniqueName;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            return [
                'success' => true,
                'file_name' => $uniqueName,
                'original_name' => $file['name']
            ];
        }
        return ['success' => false, 'message' => 'Failed to save file'];
    }

    public function search($keyword, $options = [])
    {
        $page = intval($options['page'] ?? 1);
        $limit = intval($options['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $keyword = $this->db->escape($keyword);
        $query = "SELECT d.*, u.username 
                  FROM documents d 
                  LEFT JOIN users u ON d.user_id = u.id 
                  WHERE d.status = 'approved' AND (d.original_name LIKE '%$keyword%') 
                  ORDER BY d.created_at DESC 
                  LIMIT $limit OFFSET $offset";

        return $this->db->get_list($query);
    }
}

