<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../push/send_push.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý người dùng - Admin Panel";

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    if($action === 'toggle_role') {
        $user_data = $VSD->get_row("SELECT * FROM users WHERE id=$user_id");
        $new_role = ($user_data && $user_data['role'] === 'admin') ? 'user' : 'admin';
        $VSD->update('users', ['role' => $new_role], "id=$user_id");
        
        // Notify user
        $VSD->insert('notifications', [
            'user_id' => $user_id,
            'title' => 'Cập nhật vai trò',
            'type' => 'role_updated',
            'ref_id' => $admin_id,
            'message' => "Vai trò của bạn đã được Admin thay đổi thành: " . strtoupper($new_role)
        ]);
        sendPushToUser($user_id, [
            'title' => 'Cập nhật vai trò',
            'body' => "Vai trò của bạn hiện là: " . strtoupper($new_role),
            'url' => '/history.php?tab=notifications'
        ]);

        header("Location: users.php?msg=role_updated");
        exit;
    } elseif($action === 'add_points') {
        $points = intval($_POST['points']);
        $reason = $VSD->escape($_POST['reason'] ?? 'Admin adjustment');
        if($points > 0) {
            addPoints($user_id, $points, $reason);
            
            // Notify user
            $VSD->insert('notifications', [
                'user_id' => $user_id,
                'title' => 'Bạn được cộng điểm',
                'type' => 'points_added',
                'ref_id' => $admin_id,
                'message' => "Admin đã cộng cho bạn $points điểm. Lý do: $reason"
            ]);
            sendPushToUser($user_id, [
                'title' => 'Bạn được cộng điểm',
                'body' => "Admin đã cộng cho bạn $points điểm.",
                'url' => '/history.php?tab=notifications'
            ]);

            header("Location: users.php?msg=points_added");
            exit;
        }
    } elseif($action === 'deduct_points') {
        $points = intval($_POST['points']);
        $reason = $VSD->escape($_POST['reason'] ?? 'Admin adjustment');
        if($points > 0) {
            $result = deductPoints($user_id, $points, $reason);
            if($result) {
                // Notify user
                $VSD->insert('notifications', [
                    'user_id' => $user_id,
                    'title' => 'Bạn bị trừ điểm',
                    'type' => 'points_deducted',
                    'ref_id' => $admin_id,
                    'message' => "Admin đã trừ của bạn $points điểm. Lý do: $reason"
                ]);
                sendPushToUser($user_id, [
                    'title' => 'Bạn bị trừ điểm',
                    'body' => "Admin đã trừ của bạn $points điểm.",
                    'url' => '/history.php?tab=notifications'
                ]);
            }
            header("Location: users.php?msg=points_deducted");
            exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $VSD->escape($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $VSD->escape($_GET['role']) : 'all';
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
$total_result = $VSD->get_row($total_query);
$total_users = $total_result['total'] ?? 0;
$total_pages = ceil($total_users / $per_page);

// Get users with points
$users_query = "
    SELECT u.id, u.username, u.email, u.role, u.avatar, u.created_at,
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
$users = $VSD->get_list($users_query);

$stats = $VSD->get_row("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role='user' THEN 1 ELSE 0 END) as user_count,
        SUM(COALESCE(up.current_points, 0)) as total_points_in_circulation,
        SUM(COALESCE(up.total_earned, 0)) as total_points_earned_all_time,
        SUM(COALESCE(up.total_spent, 0)) as total_points_spent_all_time
    FROM users u
    LEFT JOIN user_points up ON u.id = up.user_id
");

$unread_notifications = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");

// For shared admin sidebar
$admin_active_page = 'users';

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-users"></i>
                    Quản lý người dùng
                </h2>
                <p class="text-base-content/70 mt-1">Tổng cộng <?= number_format($total_users) ?> người dùng</p>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Status Messages -->
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success mb-4">
                <i class="fa-solid fa-check-circle"></i>
                <span>
                    <?php 
                    $messages = [
                        'role_updated' => 'Vai trò người dùng đã được cập nhật!',
                        'points_added' => 'Điểm đã được cộng thành công!',
                        'points_deducted' => 'Điểm đã được trừ thành công!'
                    ];
                    echo $messages[$_GET['msg']] ?? 'Thao tác thành công!';
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tổng người dùng</div>
                    <div class="stat-value text-primary text-3xl font-bold"><?= number_format($stats['total_users']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Quản trị viên</div>
                    <div class="stat-value text-secondary text-3xl font-bold"><?= number_format($stats['admin_count']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Người dùng thường</div>
                    <div class="stat-value text-info text-3xl font-bold"><?= number_format($stats['user_count']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Điểm đang lưu hành</div>
                    <div class="stat-value text-success text-3xl font-bold"><?= number_format($stats['total_points_in_circulation']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tổng điểm đã kiếm</div>
                    <div class="stat-value text-accent text-3xl font-bold"><?= number_format($stats['total_points_earned_all_time']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tổng điểm đã tiêu</div>
                    <div class="stat-value text-warning text-3xl font-bold"><?= number_format($stats['total_points_spent_all_time']) ?></div>
                </div>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card bg-base-100 shadow">
            <div class="card-header bg-base-200">
                <h3 class="card-title">
                    <i class="fa-solid fa-list mr-2"></i>
                    Danh sách người dùng
                </h3>
            </div>
            
            <!-- Filter Bar -->
            <div class="card-body border-b border-base-300">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-5">
                        <input type="text" name="search" class="input input-bordered w-full" placeholder="Tìm theo tên hoặc email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="md:col-span-3">
                        <select name="role" class="select select-bordered w-full">
                            <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>Tất cả vai trò</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fa-solid fa-filter mr-2"></i>Lọc
                        </button>
                    </div>
                    <?php if($search || $role_filter !== 'all'): ?>
                        <div class="md:col-span-2">
                            <a href="users.php" class="btn btn-ghost w-full">
                                <i class="fa-solid fa-xmark mr-2"></i>Xóa bộ lọc
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <?php if(count($users) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Người dùng</th>
                                <th>Vai trò</th>
                                <th>Điểm</th>
                                <th>Tài liệu</th>
                                <th>Ngày tham gia</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td class="text-base-content/70"><?= $user['id'] ?></td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar <?= !empty($user['avatar']) ? '' : 'placeholder' ?>">
                                                <div class="<?= $user['role'] === 'admin' ? 'bg-secondary' : 'bg-info' ?> text-white rounded-full w-10 overflow-hidden ring ring-offset-base-100 ring-offset-2 <?= !empty($user['avatar']) ? 'ring-primary/20' : '' ?>">
                                                    <?php if(!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])): ?>
                                                        <img src="../uploads/avatars/<?= $user['avatar'] ?>" alt="Avatar" />
                                                    <?php else: ?>
                                                        <span class="text-sm font-bold"><?= strtoupper(substr($user['username'], 0, 2)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold"><?= htmlspecialchars($user['username']) ?></div>
                                                <div class="text-base-content/70 text-sm"><?= htmlspecialchars($user['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($user['role'] === 'admin'): ?>
                                            <span class="badge badge-secondary">
                                                <i class="fa-solid fa-shield-halved mr-1"></i>Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-info badge-outline">
                                                <i class="fa-solid fa-user mr-1"></i>User
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="font-bold text-success"><?= number_format($user['points']) ?> điểm</div>
                                        <div class="text-base-content/70 text-sm">
                                            <span class="text-success">+<?= number_format($user['total_earned']) ?></span>
                                            /
                                            <span class="text-error">-<?= number_format($user['total_spent']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-bold"><?= $user['doc_count'] ?> tài liệu</div>
                                        <div class="text-base-content/70 text-sm"><?= $user['approved_docs'] ?> đã duyệt</div>
                                    </td>
                                    <td class="text-base-content/70"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="dropdown dropdown-end">
                                            <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </label>
                                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                <li>
                                                    <a onclick="openPointsModal(<?= $user['id'] ?>, '<?= addslashes($user['username']) ?>', <?= $user['points'] ?>)">
                                                        <i class="fa-solid fa-coins"></i>Quản lý điểm
                                                    </a>
                                                </li>
                                                <li class="menu-title"><span>Quyền hạn</span></li>
                                                <li>
                                                    <a class="<?= $user['role'] === 'admin' ? 'text-warning' : 'text-secondary' ?>" onclick="toggleRole(<?= $user['id'] ?>, '<?= $user['role'] ?>')">
                                                        <?php if($user['role'] === 'admin'): ?>
                                                            <i class="fa-solid fa-user"></i>Gỡ quyền Admin
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-shield-halved"></i>Cấp quyền Admin
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <div class="card-footer flex items-center justify-between border-t border-base-300 p-4">
                        <div class="text-base-content/70 text-sm">
                            Hiển thị <span class="font-bold"><?= $offset + 1 ?></span> đến <span class="font-bold"><?= min($offset + $per_page, $total_users) ?></span> trong <span class="font-bold"><?= $total_users ?></span> kết quả
                        </div>
                        <div class="join">
                            <?php if($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>" class="join-item btn btn-sm">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>" 
                                   class="join-item btn btn-sm <?= $i == $page ? 'btn-primary btn-active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>" class="join-item btn btn-sm">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card-body">
                    <div class="text-center py-12">
                        <i class="fa-solid fa-users-slash text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không tìm thấy người dùng</h3>
                        <p class="text-base-content/70 mb-4">Không có người dùng nào phù hợp với bộ lọc.</p>
                        <a href="users.php" class="btn btn-primary">
                            <i class="fa-solid fa-rotate mr-2"></i>Xóa bộ lọc
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Points Modal -->
<input type="checkbox" id="pointsModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
        <form method="POST" id="pointsForm">
            <input type="hidden" name="user_id" id="points_user_id">
            <input type="hidden" name="action" id="points_action">
            
            <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
                <i class="fa-solid fa-coins text-warning"></i>
                Quản lý điểm
            </h3>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Người dùng</span>
                </label>
                <input type="text" id="points_username" class="input input-bordered" readonly>
            </div>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Điểm hiện tại</span>
                </label>
                <input type="text" id="points_current" class="input input-bordered" readonly>
            </div>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Thao tác</span>
                </label>
                <select id="points_action_select" class="select select-bordered" onchange="updatePointsForm()">
                    <option value="add_points">Cộng điểm</option>
                    <option value="deduct_points">Trừ điểm</option>
                </select>
            </div>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Số điểm <span class="text-error">*</span></span>
                </label>
                <div class="join w-full">
                    <span class="join-item btn btn-disabled"><i class="fa-solid fa-coins"></i></span>
                    <input type="number" id="points_amount" name="points" class="input input-bordered join-item flex-1" min="1" required>
                    <span class="join-item btn btn-disabled">điểm</span>
                </div>
            </div>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Lý do <span class="text-error">*</span></span>
                </label>
                <textarea id="points_reason" name="reason" class="textarea textarea-bordered" rows="3" placeholder="Lý do điều chỉnh điểm..." required></textarea>
            </div>
            
            <div class="modal-action">
                <label for="pointsModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check mr-2"></i>Xác nhận
                </button>
            </div>
        </form>
    </div>
    <label class="modal-backdrop" for="pointsModal">Close</label>
</div>

<script>
    function openPointsModal(userId, username, currentPoints) {
        document.getElementById('points_user_id').value = userId;
        document.getElementById('points_username').value = username;
        document.getElementById('points_current').value = currentPoints + ' điểm';
        document.getElementById('points_action_select').value = 'add_points';
        updatePointsForm();
        document.getElementById('pointsModal').checked = true;
    }

    function updatePointsForm() {
        const action = document.getElementById('points_action_select').value;
        document.getElementById('points_action').value = action;
    }

    function toggleRole(userId, currentRole) {
        const message = currentRole === 'admin' 
            ? 'Bạn có chắc muốn gỡ quyền Admin của người dùng này?' 
            : 'Bạn có chắc muốn cấp quyền Admin cho người dùng này?';
        
        vsdConfirm({
            title: 'Thay đổi quyền hạn',
            message: message,
            type: 'warning',
            onConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="action" value="toggle_role">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
?>
