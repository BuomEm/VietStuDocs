<?php
namespace App\Modules\Documents\Controllers;

use App\Controllers\Controller;
use App\Support\Database;
use App\Modules\Documents\Services\DocumentService;

/**
 * SearchController - Xử lý tìm kiếm tài liệu
 */
class SearchController extends Controller
{
    private $db;
    private $docService;

    public function __construct()
    {
        $this->db = new Database();
        $this->docService = new DocumentService($this->db);
    }

    public function index()
    {
        $keyword = $_GET['q'] ?? '';
        $results = $this->docService->search($keyword);

        return $this->view('documents.search', [
            'page_title' => 'Tìm kiếm: ' . htmlspecialchars($keyword),
            'keyword' => $keyword,
            'results' => $results
        ]);
    }

    public function suggestions()
    {
        header('Content-Type: application/json');
        $keyword = $_GET['q'] ?? '';
        
        // Logic lấy gợi ý (Tạm thời trả về mảng rỗng hoặc lấy từ DB)
        $suggestions = $this->db->get_list("SELECT DISTINCT original_name as keyword FROM documents WHERE original_name LIKE '%$keyword%' LIMIT 10");
        
        echo json_encode([
            'success' => true,
            'suggestions' => $suggestions
        ]);
        exit;
    }
}

