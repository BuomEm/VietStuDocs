<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Transactions Management - Admin Panel";

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 30;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if($search) {
    $where_clauses[] = "(u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR d.original_name LIKE '%$search%')";
}
if($type_filter !== 'all') {
    $where_clauses[] = "pt.transaction_type='$type_filter'";
}
if($status_filter !== 'all') {
    $where_clauses[] = "pt.status='$status_filter'";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "
    SELECT COUNT(*) as total 
    FROM point_transactions pt
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN documents d ON pt.related_document_id = d.id
    $where_sql
";
$total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
$total_transactions = $total_result['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get transactions
$transactions_query = "
    SELECT pt.*, 
           u.username, u.email,
           d.original_name, d.id as document_id
    FROM point_transactions pt
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN documents d ON pt.related_document_id = d.id
    $where_sql
    ORDER BY pt.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$transactions = mysqli_query($conn, $transactions_query);

// Get statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN transaction_type='earn' THEN 1 ELSE 0 END), 0) as earn_count,
        COALESCE(SUM(CASE WHEN transaction_type='spend' THEN 1 ELSE 0 END), 0) as spend_count,
        COALESCE(SUM(CASE WHEN transaction_type='earn' THEN points ELSE 0 END), 0) as total_earned,
        COALESCE(SUM(CASE WHEN transaction_type='spend' THEN points ELSE 0 END), 0) as total_spent,
        COALESCE(SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END), 0) as completed_count,
        COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) as pending_count
    FROM point_transactions
"));

$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'transactions';
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

        .stat-card.earn {
            border-left-color: #4caf50;
        }

        .stat-card.earn .value {
            color: #4caf50;
        }

        .stat-card.spend {
            border-left-color: #f44336;
        }

        .stat-card.spend .value {
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

        .badge-earn {
            background: #d4edda;
            color: #155724;
        }

        .badge-spend {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
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
                <h1>💰 Transactions Management</h1>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Transactions</h3>
                    <div class="value"><?= number_format($stats['total_transactions'] ?? 0) ?></div>
                </div>
                <div class="stat-card earn">
                    <h3>Earn Transactions</h3>
                    <div class="value"><?= number_format($stats['earn_count'] ?? 0) ?></div>
                </div>
                <div class="stat-card spend">
                    <h3>Spend Transactions</h3>
                    <div class="value"><?= number_format($stats['spend_count'] ?? 0) ?></div>
                </div>
                <!-- <div class="stat-card earn">
                    <h3>Total Points Earned</h3>
                    <div class="value"><?= number_format($stats['total_earned'] ?? 0) ?></div>
                </div> -->
                <!-- <div class="stat-card spend">
                    <h3>Total Points Spent</h3>
                    <div class="value"><?= number_format($stats['total_spent'] ?? 0) ?></div>
                </div> -->
                <!-- <div class="stat-card">
                    <h3>Completed</h3>
                    <div class="value"><?= number_format($stats['completed_count'] ?? 0) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="value"><?= number_format($stats['pending_count'] ?? 0) ?></div>
                </div> -->
            </div>

            <!-- Transactions List -->
            <div class="content-section">
                <h2>All Transactions</h2>

                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="search" name="search" placeholder="Search by user or document..." value="<?= htmlspecialchars($search) ?>">
                    <select name="type">
                        <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="earn" <?= $type_filter === 'earn' ? 'selected' : '' ?>>Earn</option>
                        <option value="spend" <?= $type_filter === 'spend' ? 'selected' : '' ?>>Spend</option>
                    </select>
                    <select name="status">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit">🔍 Filter</button>
                    <?php if($search || $type_filter !== 'all' || $status_filter !== 'all'): ?>
                        <a href="transactions.php" style="padding: 10px 20px; background: #999; color: white; text-decoration: none; border-radius: 4px;">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if(mysqli_num_rows($transactions) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Points</th>
                                <th>Document</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($trans = mysqli_fetch_assoc($transactions)): ?>
                                <tr>
                                    <td><?= $trans['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($trans['username'] ?? 'Unknown') ?></strong>
                                        <br><small style="color: #999;"><?= htmlspecialchars($trans['email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $trans['transaction_type'] === 'earn' ? 'badge-earn' : 'badge-spend' ?>">
                                            <?= ucfirst($trans['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: <?= $trans['transaction_type'] === 'earn' ? '#4caf50' : '#f44336' ?>;">
                                            <?= $trans['transaction_type'] === 'earn' ? '+' : '-' ?><?= number_format($trans['points']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if($trans['document_id']): ?>
                                            <a href="../view.php?id=<?= $trans['document_id'] ?>" target="_blank" style="color: #667eea; text-decoration: none;">
                                                <?= htmlspecialchars(substr($trans['original_name'] ?? 'Unknown', 0, 30)) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(substr($trans['reason'] ?? '-', 0, 40)) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $trans['status'] ?>">
                                            <?= ucfirst($trans['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($trans['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>">« Prev</a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>">Next »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #999;">No transactions found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php mysqli_close($conn); ?>

