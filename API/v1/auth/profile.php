<?php
/**
 * Profile API (Session-based)
 */

require_once __DIR__ . '/../../core/BaseAPI.php';

class AuthProfileAPI extends BaseAPI {
    
    protected function getCurrentEndpoint() {
        return 'auth/profile';
    }
    
    public function handle() {
        $this->requireMethod('GET');
        
        global $VSD;
        
        // Check if status column exists
        $has_user_status = false;
        try {
            $check_stmt = $VSD->query("SHOW COLUMNS FROM users LIKE 'status'");
            $has_user_status = ($check_stmt && mysqli_num_rows($check_stmt) > 0);
        } catch (Exception $e) {
            $has_user_status = false;
        }
        
        $status_select = $has_user_status ? ', status' : '';
        
        // Get user profile với prepared statement
        $stmt = $VSD->prepare("
            SELECT id, username, email, avatar, role{$status_select}, 
                   created_at, is_verified_tutor
            FROM users 
            WHERE id = ?
        ");
        
        if (!$stmt) {
            $this->respondError(500, 'Database query failed');
        }
        
        $stmt->execute([$this->user_id]);
        $user = $stmt->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            $this->respondError(404, 'User not found');
        }
        
        // Get user points (check if table and column exist)
        $points = null;
        try {
            // Check if user_points table exists and has user_id column
            $check_table = $VSD->query("SHOW TABLES LIKE 'user_points'");
            if ($check_table && mysqli_num_rows($check_table) > 0) {
                $check_col = $VSD->query("SHOW COLUMNS FROM user_points LIKE 'user_id'");
                if ($check_col && mysqli_num_rows($check_col) > 0) {
                    $points_stmt = $VSD->prepare("
                        SELECT current_points, total_earned, total_spent 
                        FROM user_points 
                        WHERE user_id = ?
                    ");
                    if ($points_stmt) {
                        $points_stmt->execute([$this->user_id]);
                        $points = $points_stmt->fetch_assoc();
                        $points_stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('User points query error: ' . $e->getMessage());
        }
        
        // Get stats - check if documents table has user_id column
        $uploaded = 0;
        try {
            $check_col = $VSD->query("SHOW COLUMNS FROM documents LIKE 'user_id'");
            if ($check_col && mysqli_num_rows($check_col) > 0) {
                $uploaded_stmt = $VSD->prepare("
                    SELECT COUNT(*) as count FROM documents WHERE user_id = ?
                ");
                if ($uploaded_stmt) {
                    $uploaded_stmt->execute([$this->user_id]);
                    $uploaded_row = $uploaded_stmt->fetch_assoc();
                    $uploaded = intval($uploaded_row['count'] ?? 0);
                    $uploaded_stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log('Documents count query error: ' . $e->getMessage());
        }
        
        // Get purchased count - check column name (could be buyer_user_id)
        $purchased = 0;
        try {
            $check_table = $VSD->query("SHOW TABLES LIKE 'document_sales'");
            if ($check_table && mysqli_num_rows($check_table) > 0) {
                // Check for user_id first
                $check_col = $VSD->query("SHOW COLUMNS FROM document_sales LIKE 'user_id'");
                $user_id_col = 'user_id';
                if (!$check_col || mysqli_num_rows($check_col) == 0) {
                    // Try buyer_user_id
                    $check_col2 = $VSD->query("SHOW COLUMNS FROM document_sales LIKE 'buyer_user_id'");
                    if ($check_col2 && mysqli_num_rows($check_col2) > 0) {
                        $user_id_col = 'buyer_user_id';
                    } else {
                        // Skip if neither exists
                        $user_id_col = null;
                    }
                }
                
                if ($user_id_col) {
                    $purchased_stmt = $VSD->prepare("
                        SELECT COUNT(*) as count FROM document_sales WHERE {$user_id_col} = ?
                    ");
                    if ($purchased_stmt) {
                        $purchased_stmt->execute([$this->user_id]);
                        $purchased_row = $purchased_stmt->fetch_assoc();
                        $purchased = intval($purchased_row['count'] ?? 0);
                        $purchased_stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Document sales count query error: ' . $e->getMessage());
        }
        
        $response = [
            'id' => intval($user['id']),
            'username' => $user['username'],
            'email' => $user['email'],
            'avatar_url' => $user['avatar'] ? "/uploads/avatars/{$user['avatar']}" : null,
            'role' => $user['role'],
            'is_verified_tutor' => (bool)($user['is_verified_tutor'] ?? false),
            'created_at' => $user['created_at'],
            'points' => $points ? [
                'current' => intval($points['current_points'] ?? 0),
                'total_earned' => intval($points['total_earned'] ?? 0),
                'total_spent' => intval($points['total_spent'] ?? 0)
            ] : null,
            'stats' => [
                'uploaded_documents' => $uploaded,
                'purchased_documents' => $purchased
            ]
        ];
        
        $this->respondSuccess($response);
    }
}

$api = new AuthProfileAPI();
$api->handle();

