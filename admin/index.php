<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Admin Dashboard - DocShare";

// Get statistics
$pending_docs = getPendingDocumentsCount();

$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT d.id) as total_documents,
        COUNT(DISTINCT d.user_id) as total_users,
        SUM(CASE WHEN d.status='approved' THEN 1 ELSE 0 END) as approved_documents,
        SUM(CASE WHEN d.status='rejected' THEN 1 ELSE 0 END) as rejected_documents,
        SUM(CASE WHEN d.status='pending' THEN 1 ELSE 0 END) as pending_documents
    FROM documents d
"));

// Get recent activities
$recent_activities = mysqli_query($conn, "
    SELECT 
        'document_approved' as activity_type,
        aa.reviewed_at as timestamp,
        d.original_name,
        u.username,
        aa.admin_points as value
    FROM admin_approvals aa
    JOIN documents d ON aa.document_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE aa.status = 'approved'
    ORDER BY aa.reviewed_at DESC
    LIMIT 10
");

// Get top documents by sales
$top_documents = mysqli_query($conn, "
    SELECT 
        d.id,
        d.original_name,
        d.admin_points,
        u.username,
        COUNT(ds.id) as sales_count,
        SUM(ds.points_paid) as total_points_earned
    FROM documents d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN document_sales ds ON d.id = ds.document_id
    WHERE d.status = 'approved'
    GROUP BY d.id
    ORDER BY sales_count DESC
    LIMIT 5
");

// Get user statistics
$user_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admin_count
    FROM users
"));

$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'dashboard';
$admin_pending_count = $stats['pending_documents'];
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

        /* Sidebar */
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

        /* Main Content */
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

        .admin-header .user-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .notification-badge {
            background: #ff4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* Stats Grid */
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

        /* Content Section */
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

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
            font-size: 13px;
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

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 200px;
            }

            .admin-content {
                margin-left: 200px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                <h1>📊 Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?= htmlspecialchars(getCurrentUsername()) ?></strong></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Documents</h3>
                    <div class="value"><?= $stats['total_documents'] ?></div>
                </div>

                <div class="stat-card pending">
                    <h3>Pending Approval</h3>
                    <div class="value"><?= $stats['pending_documents'] ?></div>
                </div>

                <div class="stat-card approved">
                    <h3>Approved</h3>
                    <div class="value"><?= $stats['approved_documents'] ?></div>
                </div>

                <div class="stat-card rejected">
                    <h3>Rejected</h3>
                    <div class="value"><?= $stats['rejected_documents'] ?></div>
                </div>

                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="value"><?= $user_stats['total_users'] ?></div>
                </div>

                <div class="stat-card">
                    <h3>Admin Users</h3>
                    <div class="value"><?= $user_stats['admin_count'] ?></div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="content-section">
                <h2>📈 Recent Activities</h2>
                <?php if(mysqli_num_rows($recent_activities) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>User</th>
                                <th>Points Assigned</th>
                                <th>Approved At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($activity = mysqli_fetch_assoc($recent_activities)): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(substr($activity['original_name'], 0, 40)) ?></strong></td>
                                    <td><?= htmlspecialchars($activity['username']) ?></td>
                                    <td><strong><?= $activity['value'] ?> pts</strong></td>
                                    <td><?= date('M d, H:i', strtotime($activity['timestamp'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-message">No recent activities</div>
                <?php endif; ?>
            </div>

            <!-- Top Documents -->
            <div class="content-section">
                <h2>⭐ Top Documents by Sales</h2>
                <?php if(mysqli_num_rows($top_documents) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Author</th>
                                <th>Points Value</th>
                                <th>Sales</th>
                                <th>Total Earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($doc = mysqli_fetch_assoc($top_documents)): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(substr($doc['original_name'], 0, 40)) ?></strong></td>
                                    <td><?= htmlspecialchars($doc['username']) ?></td>
                                    <td><?= $doc['admin_points'] ?> pts</td>
                                    <td><?= $doc['sales_count'] ?></td>
                                    <td><strong><?= $doc['total_points_earned'] ?? 0 ?> pts</strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-message">No sales data yet</div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>⚡ Quick Actions</h2>
                <p>
                    <a href="pending-docs.php" class="action-btn action-btn-primary">Review Pending Documents</a>
                    <a href="users.php" class="action-btn action-btn-primary">Manage Users</a>
                    <a href="transactions.php" class="action-btn action-btn-primary">View Transactions</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>

<?php mysqli_close($conn); ?>
