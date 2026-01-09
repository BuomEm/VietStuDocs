<?php
/**
 * Documents API Endpoint
 * RESTful API cho quản lý tài liệu
 */

require_once __DIR__ . '/../../core/BaseAPI.php';

// Load categories if exists
$categories_file = __DIR__ . '/../../../config/categories.php';
if (file_exists($categories_file)) {
    require_once $categories_file;
}

class DocumentsAPI extends BaseAPI {
    private $params = [];
    
    public function setParams($params) {
        $this->params = $params;
    }
    
    protected function getCurrentEndpoint() {
        return 'documents';
    }
    
    public function handle() {
        // REST routing
        $id = $this->params['id'] ?? null;
        
        switch ($this->method) {
            case 'GET':
                if ($id) {
                    $this->getDocument($id);
                } else {
                    $this->listDocuments();
                }
                break;
                
            case 'POST':
                $this->requirePermission('documents', 'write');
                $this->createDocument();
                break;
                
            case 'PUT':
            case 'PATCH':
                if (!$id) {
                    $this->respondError(400, 'Document ID required for update');
                }
                $this->requirePermission('documents', 'write');
                $this->updateDocument($id);
                break;
                
            case 'DELETE':
                if (!$id) {
                    $this->respondError(400, 'Document ID required for delete');
                }
                $this->requirePermission('documents', 'delete');
                $this->requireRole(['admin']); // Only admin can delete via API
                $this->deleteDocument($id);
                break;
                
            default:
                $this->respondError(405, "Method {$this->method} not allowed");
        }
    }
    
    /**
     * List documents với filters và pagination
     */
    private function listDocuments() {
        global $VSD;
        
        // Input validation với whitelist
        $input = array_merge($_GET, $this->getRequestData());
        $validated = $this->validateInput($input, [
            'page' => ['type' => 'int', 'default' => 1, 'min' => 1],
            'limit' => ['type' => 'int', 'default' => 20, 'min' => 1, 'max' => 100],
            'status' => ['type' => 'enum', 'values' => ['approved', 'pending', 'rejected', 'all'], 'default' => 'approved'],
            'search' => ['type' => 'string', 'max_length' => 100, 'required' => false],
            'category' => ['type' => 'string', 'max_length' => 50, 'required' => false],
            'sort' => ['type' => 'enum', 'values' => ['newest', 'popular', 'downloads', 'price_asc', 'price_desc'], 'default' => 'newest']
        ]);
        
        $page = intval($validated['page']);
        $limit = intval($validated['limit']);
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause với prepared statements
        $where_conditions = [];
        $params = [];
        $types = '';
        
        // Status filter
        if ($validated['status'] !== 'all') {
            $where_conditions[] = "d.status = ?";
            $params[] = $validated['status'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($validated['search'])) {
            $where_conditions[] = "(d.original_name LIKE ? OR d.description LIKE ?)";
            $search_term = '%' . $validated['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ss';
        }
        
        // Category filter (nếu có)
        if (!empty($validated['category'])) {
            // Category có thể là major_code hoặc subject_code
            $where_conditions[] = "d.id IN (
                SELECT document_id FROM document_categories 
                WHERE major_code = ? OR subject_code = ?
            )";
            $params[] = $validated['category'];
            $params[] = $validated['category'];
            $types .= 'ss';
        }
        
        // Nếu không phải admin, chỉ show approved & public
        if ($this->user_role !== 'admin' && $this->auth_type === 'api_key') {
            $where_conditions[] = "d.is_public = 1";
            // Không cần param cho boolean
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Order by
        $order_by_map = [
            'newest' => 'd.created_at DESC',
            'popular' => 'd.views DESC',
            'downloads' => 'd.downloads DESC',
            'price_asc' => 'COALESCE(d.user_price, d.admin_points) ASC',
            'price_desc' => 'COALESCE(d.user_price, d.admin_points) DESC'
        ];
        $order_by = $order_by_map[$validated['sort']] ?? 'd.created_at DESC';
        
        // Check if updated_at column exists
        $has_updated_at = false;
        try {
            $check_stmt = $VSD->query("SHOW COLUMNS FROM documents LIKE 'updated_at'");
            $has_updated_at = ($check_stmt && mysqli_num_rows($check_stmt) > 0);
        } catch (Exception $e) {
            $has_updated_at = false;
        }
        
        $updated_at_select = $has_updated_at ? ', d.updated_at' : '';
        
        // Query documents với prepared statement
        $query = "
            SELECT d.id, d.original_name, d.description, d.views, d.downloads, 
                   d.user_price, d.admin_points, d.status, d.is_public,
                   d.created_at{$updated_at_select}, d.thumbnail, d.total_pages,
                   u.id as user_id, u.username, u.avatar
            FROM documents d
            LEFT JOIN users u ON d.user_id = u.id
            {$where_clause}
            ORDER BY {$order_by}
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        // Execute query
        $stmt = $VSD->prepare($query);
        if (!$stmt) {
            $this->respondError(500, 'Database query failed: ' . $VSD->error());
        }
        
        $stmt->execute($params);
        $documents = $stmt->fetch_all();
        
        // Format documents
        foreach ($documents as &$doc) {
            $doc = $this->formatDocument($doc);
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM documents d {$where_clause}";
        $count_stmt = $VSD->prepare($count_query);
        if ($count_stmt) {
            // Remove limit/offset params
            $count_params = array_slice($params, 0, -2);
            $count_stmt->execute($count_params);
            $total_row = $count_stmt->fetch_assoc();
            $total = intval($total_row['total'] ?? 0);
        } else {
            // Fallback: count manually
            $total = count($documents);
        }
        
        $this->respondSuccess([
            'documents' => $documents,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => [
                'status' => $validated['status'],
                'search' => $validated['search'] ?? null,
                'category' => $validated['category'] ?? null,
                'sort' => $validated['sort']
            ]
        ]);
    }
    
    /**
     * Get single document
     */
    private function getDocument($id) {
        global $VSD;
        
        $id = intval($id);
        if ($id <= 0) {
            $this->respondError(400, 'Invalid document ID');
        }
        
        // Get document với prepared statement
        $stmt = $VSD->prepare("
            SELECT d.*, u.username, u.avatar, u.id as uploader_id
            FROM documents d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = ?
        ");
        
        if (!$stmt) {
            $this->respondError(500, 'Database query failed');
        }
        
        $stmt->execute([$id]);
        $doc = $stmt->fetch_assoc();
        
        if (!$doc) {
            $this->respondError(404, 'Document not found');
        }
        
        // Check permissions: Admin có thể xem tất cả, user chỉ xem approved & public
        if ($this->user_role !== 'admin' && $this->auth_type === 'api_key') {
            if ($doc['status'] !== 'approved' || !$doc['is_public']) {
                $this->respondError(403, 'Document not available');
            }
        }
        
        // Check if user can view (purchased or owner or free)
        $can_view = false;
        $can_download = false;
        
        if ($this->user_id == $doc['user_id']) {
            // Owner có thể xem và download
            $can_view = true;
            $can_download = true;
        } elseif ($doc['status'] === 'approved' && $doc['is_public']) {
            // Check if purchased
            $purchased_stmt = $VSD->prepare("
                SELECT id FROM document_sales 
                WHERE document_id = ? AND user_id = ?
            ");
            if ($purchased_stmt) {
                $purchased_stmt->execute([$id, $this->user_id]);
                $purchased = $purchased_stmt->fetch_assoc();
            }
            
            $price = $doc['user_price'] > 0 ? $doc['user_price'] : $doc['admin_points'];
            $is_free = ($price == 0);
            
            $can_view = $is_free || !empty($purchased);
            $can_download = $can_view;
        }
        
        // Get categories (if function exists)
        $categories = [];
        if (function_exists('getDocumentCategoryWithNames')) {
            try {
                $categories = getDocumentCategoryWithNames($id);
            } catch (Throwable $e) {
                // Categories not available, continue without them
                error_log('Categories error: ' . $e->getMessage());
                $categories = [];
            }
        }
        
        // Format response
        $response = $this->formatDocument($doc);
        $response['uploader'] = [
            'id' => intval($doc['uploader_id']),
            'username' => $doc['username'],
            'avatar_url' => $doc['avatar'] ? "/uploads/avatars/{$doc['avatar']}" : null
        ];
        $response['category'] = $categories;
        $response['price'] = $doc['user_price'] > 0 ? $doc['user_price'] : $doc['admin_points'];
        $response['is_free'] = ($response['price'] == 0);
        $response['permissions'] = [
            'can_view' => $can_view,
            'can_download' => $can_download
        ];
        
        if ($can_view) {
            $response['view_url'] = "/view?id={$id}";
        }
        
        if ($can_download) {
            $response['download_url'] = "/view?id={$id}&download=1";
        }
        
        $this->respondSuccess($response);
    }
    
    /**
     * Create document (chỉ dành cho API Key với permission write)
     */
    private function createDocument() {
        $this->respondError(501, 'Document creation via API not yet implemented. Use web upload.');
    }
    
    /**
     * Update document
     */
    private function updateDocument($id) {
        $this->respondError(501, 'Document update via API not yet implemented. Use web interface.');
    }
    
    /**
     * Delete document
     */
    private function deleteDocument($id) {
        global $VSD;
        
        $id = intval($id);
        
        // Verify document exists and user is owner or admin
        $stmt = $VSD->prepare("SELECT user_id FROM documents WHERE id = ?");
        if (!$stmt) {
            $this->respondError(500, 'Database query failed');
        }
        
        $stmt->execute([$id]);
        $doc = $stmt->fetch_assoc();
        
        if (!$doc) {
            $this->respondError(404, 'Document not found');
        }
        
        // Only admin can delete via API (owner should use web interface)
        if ($this->user_role !== 'admin') {
            $this->respondError(403, 'Only admin can delete documents via API');
        }
        
        // Delete file
        $file_stmt = $VSD->prepare("SELECT file_name FROM documents WHERE id = ?");
        $file_stmt->execute([$id]);
        $file_info = $file_stmt->fetch_assoc();
        
        if ($file_info && !empty($file_info['file_name'])) {
            $file_path = __DIR__ . '/../../uploads/' . $file_info['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete from database
        $delete_stmt = $VSD->prepare("DELETE FROM documents WHERE id = ?");
        if (!$delete_stmt || !$delete_stmt->execute([$id])) {
            $this->respondError(500, 'Failed to delete document');
        }
        
        $this->respondSuccess(['deleted' => true, 'document_id' => $id], 'Document deleted successfully');
    }
    
    /**
     * Format document for response
     */
    private function formatDocument($doc) {
        $price = $doc['user_price'] > 0 ? $doc['user_price'] : $doc['admin_points'];
        
        return [
            'id' => intval($doc['id']),
            'title' => $doc['original_name'],
            'description' => $doc['description'] ?? null,
            'uploader' => [
                'id' => intval($doc['user_id'] ?? 0),
                'username' => $doc['username'] ?? null,
                'avatar_url' => (!empty($doc['avatar'])) ? "/uploads/avatars/{$doc['avatar']}" : null
            ],
            'stats' => [
                'views' => intval($doc['views'] ?? 0),
                'downloads' => intval($doc['downloads'] ?? 0),
                'pages' => intval($doc['total_pages'] ?? 0)
            ],
            'price' => $price,
            'is_free' => ($price == 0),
            'status' => $doc['status'] ?? 'pending',
            'thumbnail_url' => (!empty($doc['thumbnail'])) ? "/uploads/thumbnails/{$doc['thumbnail']}" : null,
            'url' => "/view?id={$doc['id']}",
            'created_at' => $doc['created_at'] ?? null,
            'updated_at' => $doc['updated_at'] ?? null
        ];
    }
}

// Initialize and handle request
$api = new DocumentsAPI();
$api->handle();

