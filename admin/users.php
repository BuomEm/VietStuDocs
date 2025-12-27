<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Users Management - Admin Panel";

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    if($action === 'toggle_role') {
        $current_role = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id=$user_id"));
        $new_role = $current_role['role'] === 'admin' ? 'user' : 'admin';
        mysqli_query($conn, "UPDATE users SET role='$new_role' WHERE id=$user_id");
        header("Location: users.php?msg=role_updated");
        exit;
    } elseif($action === 'add_points') {
        $points = intval($_POST['points']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Admin adjustment');
        if($points > 0) {
            addPoints($user_id, $points, $reason);
            header("Location: users.php?msg=points_added");
            exit;
        }
    } elseif($action === 'deduct_points') {
        $points = intval($_POST['points']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Admin adjustment');
        if($points > 0) {
            $result = deductPoints($user_id, $points, $reason);
            if(!$result) {
                // Handle error if needed
            }
            header("Location: users.php?msg=points_deducted");
            exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if($search) {
    $where_clauses[] = "(u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
}
if($role_filter !== 'all') {
    $where_clauses[] = "u.role='$role_filter'";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "SELECT COUNT(*) as total FROM users u $where_sql";
$total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
$total_users = $total_result['total'];
$total_pages = ceil($total_users / $per_page);

// Get users with points
$users_query = "
    SELECT u.id, u.username, u.email, u.role, u.created_at,
           COALESCE(up.current_points, 0) as points,
           COALESCE(up.total_earned, 0) as total_earned,
           COALESCE(up.total_spent, 0) as total_spent,
           (SELECT COUNT(*) FROM documents WHERE user_id=u.id) as doc_count,
           (SELECT COUNT(*) FROM documents WHERE user_id=u.id AND status='approved') as approved_docs
    FROM users u
    LEFT JOIN user_points up ON u.id = up.user_id
    $where_sql
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$users = mysqli_query($conn, $users_query);

// Get statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role='user' THEN 1 ELSE 0 END) as user_count,
        SUM(COALESCE(up.current_points, 0)) as total_points_in_circulation,
        SUM(COALESCE(up.total_earned, 0)) as total_points_earned_all_time,
        SUM(COALESCE(up.total_spent, 0)) as total_points_spent_all_time
    FROM users u
    LEFT JOIN user_points up ON u.id = up.user_id
"));

$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'users';
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

        .badge-admin {
            background: #667eea;
            color: white;
        }

        .badge-user {
            background: #e0e0e0;
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
            max-width: 500px;
            width: 90%;
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
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
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
                <h1>👥 Users Management</h1>
            </div>

            <!-- Status Messages -->
            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] === 'role_updated'): ?>
                    <div class="alert alert-success">✓ User role updated successfully!</div>
                <?php elseif($_GET['msg'] === 'points_added'): ?>
                    <div class="alert alert-success">✓ Points added successfully!</div>
                <?php elseif($_GET['msg'] === 'points_deducted'): ?>
                    <div class="alert alert-success">✓ Points deducted successfully!</div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="value"><?= $stats['total_users'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Admin Users</h3>
                    <div class="value"><?= $stats['admin_count'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Regular Users</h3>
                    <div class="value"><?= $stats['user_count'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Points in Circulation</h3>
                    <div class="value"><?= number_format($stats['total_points_in_circulation']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Points Earned (All Time)</h3>
                    <div class="value"><?= number_format($stats['total_points_earned_all_time']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Points Spent (All Time)</h3>
                    <div class="value"><?= number_format($stats['total_points_spent_all_time']) ?></div>
                </div>
            </div>

            <!-- Users List -->
            <div class="content-section">
                <h2>Users List</h2>

                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="search" name="search" placeholder="Search by username or email..." value="<?= htmlspecialchars($search) ?>">
                    <select name="role">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                    </select>
                    <button type="submit">🔍 Filter</button>
                    <?php if($search || $role_filter !== 'all'): ?>
                        <a href="users.php" style="padding: 10px 20px; background: #999; color: white; text-decoration: none; border-radius: 4px;">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if(mysqli_num_rows($users) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Points</th>
                                <th>Documents</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = mysqli_fetch_assoc($users)): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($user['points']) ?></strong> pts
                                        <br><small style="color: #999;">Earned: <?= number_format($user['total_earned']) ?> | Spent: <?= number_format($user['total_spent']) ?></small>
                                    </td>
                                    <td>
                                        <?= $user['doc_count'] ?> total
                                        <br><small style="color: #999;"><?= $user['approved_docs'] ?> approved</small>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <button class="action-btn action-btn-primary" onclick="openPointsModal(<?= $user['id'] ?>, '<?= addslashes($user['username']) ?>', <?= $user['points'] ?>)">💰 Points</button>
                                        <button class="action-btn action-btn-warning" onclick="toggleRole(<?= $user['id'] ?>, '<?= $user['role'] ?>')"><?= $user['role'] === 'admin' ? '👤 Remove Admin' : '👑 Make Admin' ?></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>">« Prev</a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>">Next »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #999;">No users found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Points Modal -->
    <div id="pointsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>💰 Manage Points</h2>
                <button class="close-btn" onclick="closeModal('pointsModal')">×</button>
            </div>
            <form method="POST" id="pointsForm">
                <input type="hidden" name="user_id" id="points_user_id">
                <input type="hidden" name="action" id="points_action">
                
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="points_username" readonly style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>Current Points</label>
                    <input type="text" id="points_current" readonly style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>Action</label>
                    <select id="points_action_select" onchange="updatePointsForm()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="add_points">Add Points</option>
                        <option value="deduct_points">Deduct Points</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="points_amount">Points Amount</label>
                    <input type="number" id="points_amount" name="points" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="points_reason">Reason</label>
                    <textarea id="points_reason" name="reason" placeholder="Reason for this adjustment..." required></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('pointsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPointsModal(userId, username, currentPoints) {
            document.getElementById('points_user_id').value = userId;
            document.getElementById('points_username').value = username;
            document.getElementById('points_current').value = currentPoints + ' points';
            document.getElementById('points_action_select').value = 'add_points';
            updatePointsForm();
            document.getElementById('pointsModal').classList.add('show');
        }

        function updatePointsForm() {
            const action = document.getElementById('points_action_select').value;
            document.getElementById('points_action').value = action;
        }

        function toggleRole(userId, currentRole) {
            if(confirm(`Are you sure you want to ${currentRole === 'admin' ? 'remove admin role from' : 'make'} this user?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="action" value="toggle_role">
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
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>

