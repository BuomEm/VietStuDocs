<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "All Documents - Admin Panel";

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    
    if($action === 'approve') {
        $points = intval($_POST['points']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        
        if($points > 0) {
            approveDocument($document_id, $admin_id, $points, $notes);
            header("Location: all-documents.php?msg=approved&id=$document_id");
            exit;
        }
    } elseif($action === 'reject') {
        $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        rejectDocument($document_id, $admin_id, $reason);
        header("Location: all-documents.php?msg=rejected&id=$document_id");
        exit;
    } elseif($action === 'delete') {
        $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_name FROM documents WHERE id=$document_id"));
        if($doc) {
            $file_path = "../uploads/" . $doc['file_name'];
            if(file_exists($file_path)) {
                unlink($file_path);
            }
            mysqli_query($conn, "DELETE FROM documents WHERE id=$document_id");
            header("Location: all-documents.php?msg=deleted");
            exit;
        }
    } elseif($action === 'change_status') {
        $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
        if(in_array($new_status, ['pending', 'approved', 'rejected'])) {
            mysqli_query($conn, "UPDATE documents SET status='$new_status' WHERE id=$document_id");
            header("Location: all-documents.php?msg=status_changed&id=$document_id");
            exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if($search) {
    $where_clauses[] = "(d.original_name LIKE '%$search%' OR u.username LIKE '%$search%' OR d.description LIKE '%$search%')";
}
if($status_filter !== 'all') {
    $where_clauses[] = "d.status='$status_filter'";
}
if($user_filter > 0) {
    $where_clauses[] = "d.user_id=$user_filter";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "
    SELECT COUNT(*) as total 
    FROM documents d
    LEFT JOIN users u ON d.user_id = u.id
    $where_sql
";
$total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
$total_documents = $total_result['total'];
$total_pages = ceil($total_documents / $per_page);

// Get documents
$documents_query = "
    SELECT d.*, 
           u.username, u.email,
           dp.admin_points as assigned_points,
           aa.reviewed_by, aa.reviewed_at, aa.rejection_reason,
           (SELECT COUNT(*) FROM document_sales WHERE document_id=d.id) as sales_count,
           (SELECT SUM(points_paid) FROM document_sales WHERE document_id=d.id) as total_earned
    FROM documents d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN docs_points dp ON d.id = dp.document_id
    LEFT JOIN admin_approvals aa ON d.id = aa.document_id
    $where_sql
    ORDER BY d.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$documents = mysqli_query($conn, $documents_query);

// Get statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN is_public=1 THEN 1 ELSE 0 END) as public_count,
        SUM(CASE WHEN is_public=0 THEN 1 ELSE 0 END) as private_count,
        SUM(admin_points) as total_points_assigned,
        (SELECT COUNT(*) FROM document_sales) as total_sales,
        (SELECT SUM(points_paid) FROM document_sales) as total_points_earned
    FROM documents
"));

// Get users for filter
$users_list = mysqli_query($conn, "
    SELECT DISTINCT u.id, u.username
    FROM users u
    JOIN documents d ON u.id = d.user_id
    ORDER BY u.username
");

$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'documents';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .admin-sidebar h2 {
            font-size: 20px;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 15px;
        }

        .admin-sidebar nav a {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .admin-sidebar nav a:hover,
        .admin-sidebar nav a.active {
            background: rgba(255,255,255,0.2);
        }

        .admin-sidebar .logout {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 15px;
        }

        .admin-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .admin-header h1 {
            font-size: 24px;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }

        .stat-card h3 {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-card.pending {
            border-left-color: #ff9800;
        }

        .stat-card.pending .value {
            color: #ff9800;
        }

        .stat-card.approved {
            border-left-color: #4caf50;
        }

        .stat-card.approved .value {
            color: #4caf50;
        }

        .stat-card.rejected {
            border-left-color: #f44336;
        }

        .stat-card.rejected .value {
            color: #f44336;
        }

        .content-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .content-section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-bar input[type="search"] {
            flex: 1;
            min-width: 200px;
        }

        .filter-bar button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-bar button:hover {
            background: #764ba2;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
        }

        .data-table thead {
            background: #f9f9f9;
        }

        .data-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #eee;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .data-table tr:hover {
            background: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3cd;
            color: #ff9800;
        }

        .badge-approved {
            background: #d4edda;
            color: #4caf50;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #f44336;
        }

        .badge-public {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-private {
            background: #f5f5f5;
            color: #666;
        }

        .action-btn {
            padding: 6px 12px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }

        .action-btn-primary {
            background: #667eea;
            color: white;
        }

        .action-btn-primary:hover {
            background: #764ba2;
        }

        .action-btn-success {
            background: #4caf50;
            color: white;
        }

        .action-btn-success:hover {
            background: #45a049;
        }

        .action-btn-warning {
            background: #ff9800;
            color: white;
        }

        .action-btn-warning:hover {
            background: #f57c00;
        }

        .action-btn-danger {
            background: #f44336;
            color: white;
        }

        .action-btn-danger:hover {
            background: #d32f2f;
        }

        .action-btn-secondary {
            background: #999;
            color: white;
        }

        .action-btn-secondary:hover {
            background: #777;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .modal-header h2 {
            font-size: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-secondary {
            background: #999;
            color: white;
        }

        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 200px;
            }

            .admin-content {
                margin-left: 200px;
            }

            .data-table {
                font-size: 11px;
            }

            .data-table th,
            .data-table td {
                padding: 8px;
            }
        }
</style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Header -->
            <div class="admin-header">
                <h1>📚 All Documents Management</h1>
            </div>

            <!-- Status Messages -->
            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] === 'approved'): ?>
                    <div class="alert alert-success">✓ Document approved successfully!</div>
                <?php elseif($_GET['msg'] === 'rejected'): ?>
                    <div class="alert alert-success">✓ Document rejected successfully!</div>
                <?php elseif($_GET['msg'] === 'deleted'): ?>
                    <div class="alert alert-success">✓ Document deleted successfully!</div>
                <?php elseif($_GET['msg'] === 'status_changed'): ?>
                    <div class="alert alert-success">✓ Document status changed successfully!</div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Documents</h3>
                    <div class="value"><?= number_format($stats['total_documents']) ?></div>
                </div>
                <div class="stat-card pending">
                    <h3>Pending</h3>
                    <div class="value"><?= number_format($stats['pending_count']) ?></div>
                </div>
                <div class="stat-card approved">
                    <h3>Approved</h3>
                    <div class="value"><?= number_format($stats['approved_count']) ?></div>
                </div>
                <div class="stat-card rejected">
                    <h3>Rejected</h3>
                    <div class="value"><?= number_format($stats['rejected_count']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Public Documents</h3>
                    <div class="value"><?= number_format($stats['public_count']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Points Assigned</h3>
                    <div class="value"><?= number_format($stats['total_points_assigned']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <div class="value"><?= number_format($stats['total_sales']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Points Earned</h3>
                    <div class="value"><?= number_format($stats['total_points_earned'] ?? 0) ?></div>
                </div>
            </div>

            <!-- Documents List -->
            <div class="content-section">
                <h2>All Documents</h2>

                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="search" name="search" placeholder="Search by document name, user, or description..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <select name="user">
                        <option value="0" <?= $user_filter === 0 ? 'selected' : '' ?>>All Users</option>
                        <?php while($user = mysqli_fetch_assoc($users_list)): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit">🔍 Filter</button>
                    <?php if($search || $status_filter !== 'all' || $user_filter > 0): ?>
                        <a href="all-documents.php" style="padding: 10px 20px; background: #999; color: white; text-decoration: none; border-radius: 4px;">Clear</a>
                    <?php endif; ?>
                    <button type="button" onclick="openBatchThumbnailModal()" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                        🖼️ Generate Missing Thumbnails
                    </button>
                </form>

                <?php if(mysqli_num_rows($documents) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Document</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Points</th>
                                <th>Sales</th>
                                <th>Privacy</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($doc = mysqli_fetch_assoc($documents)): 
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $icon_map = [
                                    'pdf' => '📄', 'doc' => '📄', 'docx' => '📄',
                                    'txt' => '📝', 'xlsx' => '📊', 'ppt' => '🎬',
                                    'jpg' => '🖼️', 'png' => '🖼️', 'jpeg' => '🖼️',
                                    'zip' => '🗂️', 'rar' => '🗂️'
                                ];
                                $icon = $icon_map[$ext] ?? '📁';
                            ?>
                                <tr>
                                    <td><?= $doc['id'] ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-size: 20px;"><?= $icon ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars(substr($doc['original_name'], 0, 40)) ?></strong>
                                                <?php if($doc['description']): ?>
                                                    <br><small style="color: #999;"><?= htmlspecialchars(substr($doc['description'], 0, 50)) ?>...</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($doc['username'] ?? 'Unknown') ?></strong>
                                        <br><small style="color: #999;"><?= htmlspecialchars($doc['email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $doc['status'] ?>">
                                            <?= ucfirst($doc['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($doc['status'] === 'approved'): ?>
                                            <strong><?= number_format($doc['admin_points'] ?? $doc['assigned_points'] ?? 0) ?></strong> pts
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($doc['status'] === 'approved'): ?>
                                            <strong><?= $doc['sales_count'] ?? 0 ?></strong> sales
                                            <br><small style="color: #999;"><?= number_format($doc['total_earned'] ?? 0) ?> pts earned</small>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $doc['is_public'] ? 'badge-public' : 'badge-private' ?>">
                                            <?= $doc['is_public'] ? 'Public' : 'Private' ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <a href="../view.php?id=<?= $doc['id'] ?>" target="_blank" class="action-btn action-btn-secondary">👁️ View</a>
                                            <?php if($doc['status'] === 'pending'): ?>
                                                <button class="action-btn action-btn-success" onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">✓ Approve</button>
                                                <button class="action-btn action-btn-warning" onclick="openRejectModal(<?= $doc['id'] ?>)">✗ Reject</button>
                                            <?php elseif($doc['status'] === 'approved'): ?>
                                                <button class="action-btn action-btn-warning" onclick="openChangeStatusModal(<?= $doc['id'] ?>, '<?= $doc['status'] ?>')">🔄 Change Status</button>
                                            <?php elseif($doc['status'] === 'rejected'): ?>
                                                <button class="action-btn action-btn-primary" onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">✓ Approve</button>
                                                <button class="action-btn action-btn-warning" onclick="openChangeStatusModal(<?= $doc['id'] ?>, '<?= $doc['status'] ?>')">🔄 Change Status</button>
                                            <?php endif; ?>
                                            <button class="action-btn action-btn-danger" onclick="deleteDocument(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">🗑️ Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&user=<?= $user_filter ?>">« Prev</a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&user=<?= $user_filter ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&user=<?= $user_filter ?>">Next »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #999;">No documents found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✓ Approve Document</h2>
                <button class="close-btn" onclick="closeModal('approveModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="document_id" id="approve_doc_id">
                <input type="hidden" name="action" value="approve">

                <div class="form-group">
                    <label for="doc_title">Document</label>
                    <input type="text" id="doc_title" readonly style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label for="points">🎯 Points Value</label>
                    <input type="number" id="points" name="points" min="1" max="1000" value="50" required>
                    <small style="color: #999;">Maximum points users can set for selling this document</small>
                </div>

                <div class="form-group">
                    <label for="notes">📝 Admin Notes</label>
                    <textarea id="notes" name="notes" placeholder="Add any notes about this document..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">✓ Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✗ Reject Document</h2>
                <button class="close-btn" onclick="closeModal('rejectModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="document_id" id="reject_doc_id">
                <input type="hidden" name="action" value="reject">

                <div class="form-group">
                    <label for="reason">❌ Rejection Reason</label>
                    <textarea id="reason" name="rejection_reason" placeholder="Why are you rejecting this document?" required></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">✗ Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div id="changeStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🔄 Change Document Status</h2>
                <button class="close-btn" onclick="closeModal('changeStatusModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="document_id" id="change_status_doc_id">
                <input type="hidden" name="action" value="change_status">

                <div class="form-group">
                    <label for="new_status">New Status</label>
                    <select id="new_status" name="new_status" required>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('changeStatusModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Batch Generate Thumbnails Modal -->
    <div id="batchThumbnailModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>🖼️ Generate Missing Thumbnails</h2>
                <button class="close-btn" onclick="closeModal('batchThumbnailModal')">×</button>
            </div>
            <div class="modal-body">
                <div id="batchThumbnailStatus" style="margin-bottom: 20px;">
                    <p>Click "Start" to begin generating thumbnails for all documents that don't have one.</p>
                </div>
                <div id="batchThumbnailProgress" style="display: none;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress:</strong> <span id="batchProgressText">0/0</span>
                    </div>
                    <div style="width: 100%; height: 30px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 20px;">
                        <div id="batchProgressBar" style="width: 0%; height: 100%; background: #10b981; transition: width 0.3s;"></div>
                    </div>
                    <div id="batchThumbnailLog" style="max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('batchThumbnailModal')" id="batchCloseBtn">Close</button>
                    <button type="button" class="btn btn-primary" onclick="startBatchThumbnailGeneration()" id="batchStartBtn">Start</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF.js for thumbnail generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <!-- Shared PDF functions for page counting and thumbnail generation -->
    <script src="../js/pdf-functions.js"></script>

    <script>
        function openApproveModal(docId, docTitle) {
            document.getElementById('approve_doc_id').value = docId;
            document.getElementById('doc_title').value = docTitle;
            document.getElementById('approveModal').classList.add('show');
        }

        function openRejectModal(docId) {
            document.getElementById('reject_doc_id').value = docId;
            document.getElementById('rejectModal').classList.add('show');
        }

        function openChangeStatusModal(docId, currentStatus) {
            document.getElementById('change_status_doc_id').value = docId;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('changeStatusModal').classList.add('show');
        }

        function deleteDocument(docId, docTitle) {
            if(confirm(`Are you sure you want to delete "${docTitle}"?\n\nThis action cannot be undone!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="document_id" value="${docId}">
                    <input type="hidden" name="action" value="delete">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        window.addEventListener('click', function(event) {
            if(event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });

        function openBatchThumbnailModal() {
            document.getElementById('batchThumbnailModal').classList.add('show');
            document.getElementById('batchThumbnailProgress').style.display = 'none';
            document.getElementById('batchThumbnailStatus').innerHTML = '<p>Click "Start" to begin generating thumbnails for all documents that don\'t have one.</p>';
            document.getElementById('batchThumbnailLog').innerHTML = '';
            document.getElementById('batchStartBtn').disabled = false;
            document.getElementById('batchCloseBtn').disabled = false;
        }

        async function startBatchThumbnailGeneration() {
            const statusDiv = document.getElementById('batchThumbnailStatus');
            const progressDiv = document.getElementById('batchThumbnailProgress');
            const progressBar = document.getElementById('batchProgressBar');
            const progressText = document.getElementById('batchProgressText');
            const logDiv = document.getElementById('batchThumbnailLog');
            const startBtn = document.getElementById('batchStartBtn');
            const closeBtn = document.getElementById('batchCloseBtn');

            startBtn.disabled = true;
            closeBtn.disabled = true;
            progressDiv.style.display = 'block';
            statusDiv.innerHTML = '<p><strong>Fetching documents without thumbnails...</strong></p>';

            try {
                // Fetch list of documents without thumbnails
                const response = await fetch('../handler/batch_generate_thumbnails.php');
                
                // Check if response is OK
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
                }
                
                // Get response text first to check for errors
                const responseText = await response.text();
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response: ' + parseError.message + '. Response: ' + responseText.substring(0, 200));
                }

                if (!data.success) {
                    throw new Error(data.message || 'Failed to fetch documents');
                }

                const documents = data.documents || [];
                const total = documents.length;

                if (total === 0) {
                    statusDiv.innerHTML = '<p style="color: #10b981;"><strong>✓ All documents already have thumbnails!</strong></p>';
                    startBtn.disabled = false;
                    closeBtn.disabled = false;
                    return;
                }

                statusDiv.innerHTML = `<p><strong>Found ${total} documents without thumbnails. Starting generation...</strong></p>`;
                logDiv.innerHTML = '';

                let successCount = 0;
                let failCount = 0;
                let skipCount = 0;
                let existingPdfCount = 0; // Count of DOCX files that used existing PDF
                let convertedCount = 0; // Count of DOCX files that were converted

                // Process each document
                for (let i = 0; i < documents.length; i++) {
                    const doc = documents[i];
                    const current = i + 1;
                    const percent = Math.round((current / total) * 100);

                    progressBar.style.width = percent + '%';
                    progressText.textContent = `${current}/${total} (${percent}%)`;

                    const logEntry = document.createElement('div');
                    logEntry.style.marginBottom = '5px';
                    logEntry.innerHTML = `[${current}/${total}] Processing: ${doc.original_name}...`;
                    logDiv.appendChild(logEntry);
                    logDiv.scrollTop = logDiv.scrollHeight;

                    try {
                        // Skip non-PDF/DOCX files (images already have thumbnails from server-side)
                        if (!['pdf', 'docx', 'doc'].includes(doc.file_ext)) {
                            logEntry.innerHTML = `[${current}/${total}] ⏭️ Skipped: ${doc.original_name} (${doc.file_ext} - not PDF/DOCX)`;
                            logEntry.style.color = '#999';
                            skipCount++;
                            continue;
                        }

                        // Get document info (for DOCX, this will convert to PDF using Adobe API)
                        // Note: For DOCX files, countdown delay is added above before this call
                        const docInfoResponse = await fetch('../handler/batch_generate_thumbnails.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ doc_id: doc.id })
                        });

                        if (!docInfoResponse.ok) {
                            const text = await docInfoResponse.text();
                            throw new Error(`HTTP ${docInfoResponse.status}: ${text.substring(0, 100)}`);
                        }

                        const responseText = await docInfoResponse.text();
                        
                        // Debug: Log response details
                        console.log('Response status:', docInfoResponse.status);
                        console.log('Response headers:', docInfoResponse.headers.get('content-type'));
                        console.log('Response text (first 500 chars):', responseText.substring(0, 500));
                        
                        // Check if response looks like HTML
                        if (responseText.trim().startsWith('<')) {
                            console.error('Server returned HTML instead of JSON. Full response:', responseText);
                            throw new Error('Server returned HTML instead of JSON. Response starts with: ' + responseText.substring(0, 200));
                        }
                        
                        let docInfo;
                        try {
                            docInfo = JSON.parse(responseText);
                        } catch (parseError) {
                            console.error('JSON Parse Error:', parseError);
                            console.error('Full response text:', responseText);
                            throw new Error('Invalid JSON response: ' + parseError.message + '. Response (first 300 chars): ' + responseText.substring(0, 300));
                        }

                        if (!docInfo.success) {
                            throw new Error(docInfo.message || 'Failed to get document info');
                        }

                        // Handle DOCX files: Convert to PDF server-side, then process client-side
                        if (['docx', 'doc'].includes(doc.file_ext)) {
                            // Add countdown relay to avoid rate limiting (5 seconds delay)
                            const delaySeconds = 5;
                            logEntry.innerHTML = `[${current}/${total}] ⏳ Waiting ${delaySeconds}s before conversion (to avoid rate limiting)...`;
                            logEntry.style.color = '#f59e0b';
                            
                            // Countdown display
                            for (let countdown = delaySeconds; countdown > 0; countdown--) {
                                await new Promise(resolve => setTimeout(resolve, 1000));
                                logEntry.innerHTML = `[${current}/${total}] ⏳ Waiting ${countdown}s before conversion...`;
                                logDiv.scrollTop = logDiv.scrollHeight;
                            }
                            
                            if (docInfo.skip === true) {
                                // DOCX conversion failed
                                logEntry.innerHTML = `[${current}/${total}] ⏭️ Skipped: ${doc.original_name} (${docInfo.message || 'Cannot convert DOCX'})`;
                                logEntry.style.color = '#999';
                                skipCount++;
                                continue;
                            }
                            
                            // Conversion succeeded - now process the converted PDF client-side
                            // The handler returns the converted PDF path, so we treat it as a PDF
                            if (docInfo.converted_pdf_path) {
                                // Track if we used existing PDF or converted new one
                                if (docInfo.used_existing_pdf === true) {
                                    existingPdfCount++;
                                } else {
                                    convertedCount++;
                                }
                                
                                // Fall through to PDF processing with converted PDF path
                                docInfo.file_path = docInfo.converted_pdf_path;
                                docInfo.file_ext = 'pdf';
                            } else {
                                // No converted PDF path - skip
                                logEntry.innerHTML = `[${current}/${total}] ⏭️ Skipped: ${doc.original_name} (No converted PDF path)`;
                                logEntry.style.color = '#999';
                                skipCount++;
                                continue;
                            }
                        }

                        // For PDF files (including converted DOCX), use client-side PDF.js generation via shared functions
                        if (docInfo.file_ext === 'pdf') {
                            const pdfPath = docInfo.file_path;
                            
                            if (!pdfPath) {
                                throw new Error('No PDF path available');
                            }

                            // Ensure filePath is a valid URL (relative paths need to be resolved)
                            let pdfUrl = pdfPath;
                            if (!pdfPath.startsWith('http://') && !pdfPath.startsWith('https://') && !pdfPath.startsWith('data:')) {
                                // Relative path - ensure it starts with ../
                                if (!pdfPath.startsWith('../') && !pdfPath.startsWith('/')) {
                                    pdfUrl = '../' + pdfPath;
                                } else if (pdfPath.startsWith('/')) {
                                    pdfUrl = '..' + pdfPath;
                                } else {
                                    pdfUrl = pdfPath;
                                }
                            }

                            console.log('Processing PDF document:', {
                                docId: docInfo.doc_id,
                                pdfPath: pdfUrl,
                                fileExt: docInfo.file_ext,
                                totalPages: docInfo.total_pages
                            });

                            // Use shared function to process PDF (count pages and generate thumbnail)
                            const result = await processPdfDocument(pdfUrl, docInfo.doc_id, {
                                countPages: true,
                                generateThumbnail: true,
                                thumbnailWidth: 400
                            });
                            
                            // Build success message with total pages information
                            let successMessage = `[${current}/${total}] ✓ Success: ${doc.original_name}`;
                            if (result.pages && result.pages > 0) {
                                successMessage += ` (${result.pages} pages)`;
                            } else if (docInfo.total_pages && docInfo.total_pages > 0) {
                                successMessage += ` (${docInfo.total_pages} pages)`;
                            }
                            
                            logEntry.innerHTML = successMessage;
                            logEntry.style.color = '#10b981';
                            successCount++;
                        } else {
                            // Other file types - should not reach here, but handle gracefully
                            logEntry.innerHTML = `[${current}/${total}] ⏭️ Skipped: ${doc.original_name} (unsupported file type)`;
                            logEntry.style.color = '#999';
                            skipCount++;
                        }

                    } catch (error) {
                        logEntry.innerHTML = `[${current}/${total}] ✗ Failed: ${doc.original_name} - ${error.message}`;
                        logEntry.style.color = '#ef4444';
                        failCount++;
                        console.error('Error processing document:', doc.id, error);
                    }

                    // Small delay to prevent browser freezing
                    await new Promise(resolve => setTimeout(resolve, 100));
                }

                // Show summary
                let summaryDetails = [];
                summaryDetails.push(`✓ Success: ${successCount}`);
                if (convertedCount > 0) {
                    summaryDetails.push(`🔄 Converted: ${convertedCount}`);
                }
                if (existingPdfCount > 0) {
                    summaryDetails.push(`📄 Used Existing PDF: ${existingPdfCount}`);
                }
                summaryDetails.push(`✗ Failed: ${failCount}`);
                summaryDetails.push(`⏭️ Skipped: ${skipCount}`);
                
                statusDiv.innerHTML = `
                    <p><strong>Batch processing completed!</strong></p>
                    <p>${summaryDetails.join(' | ')}</p>
                `;
                progressBar.style.width = '100%';
                progressText.textContent = `Complete: ${successCount} success, ${failCount} failed, ${skipCount} skipped`;

                startBtn.disabled = false;
                closeBtn.disabled = false;

            } catch (error) {
                statusDiv.innerHTML = `<p style="color: #ef4444;"><strong>Error:</strong> ${error.message}</p>`;
                logDiv.innerHTML += `<div style="color: #ef4444;">Error: ${error.message}</div>`;
                startBtn.disabled = false;
                closeBtn.disabled = false;
                console.error('Batch thumbnail generation error:', error);
            }
        }

    </script>
</body>
</html>

<?php mysqli_close($conn); ?>

