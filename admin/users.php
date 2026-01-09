<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../push/send_push.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý người dùng";

// Handle actions (removed logic for brevity, keeping same logic structure)
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    if($action === 'toggle_role') {
        $user_data = $VSD->get_row("SELECT * FROM users WHERE id=$user_id");
        $new_role = ($user_data && $user_data['role'] === 'admin') ? 'user' : 'admin';
        $VSD->update('users', ['role' => $new_role], "id=$user_id");
        
        // Notify logic...
        $VSD->insert('notifications', [
            'user_id' => $user_id, 'title' => 'Cập nhật vai trò', 'type' => 'role_updated', 'ref_id' => $admin_id,
            'message' => "Admin đã thay đổi vai trò của bạn thành: " . strtoupper($new_role)
        ]);
        sendPushToUser($user_id, ['title' => 'Cập nhật vai trò', 'body' => "Vai trò mới: " . strtoupper($new_role), 'url' => '/history.php?tab=notifications']);
        header("Location: users.php?msg=role_updated"); exit;
    } elseif($action === 'add_points') {
        $points = intval($_POST['points']);
        $reason = $VSD->escape($_POST['reason'] ?? 'Admin adjustment');
        if($points > 0) {
            addPoints($user_id, $points, $reason);
            // Notify...
            $VSD->insert('notifications', ['user_id' => $user_id, 'title' => 'Cộng điểm', 'type' => 'points_added', 'ref_id' => $admin_id, 'message' => "Admin cộng $points điểm. Lý do: $reason"]);
            sendPushToUser($user_id, ['title' => 'Cộng điểm', 'body' => "Bạn được cộng $points điểm", 'url' => '/history.php?tab=notifications']);
            header("Location: users.php?msg=points_added"); exit;
        }
    } elseif($action === 'deduct_points') {
        $points = intval($_POST['points']);
        $reason = $VSD->escape($_POST['reason'] ?? 'Admin adjustment');
        if($points > 0) {
            deductPoints($user_id, $points, $reason);
            // Notify...
             $VSD->insert('notifications', ['user_id' => $user_id, 'title' => 'Trừ điểm', 'type' => 'points_deducted', 'ref_id' => $admin_id, 'message' => "Admin trừ $points điểm. Lý do: $reason"]);
            sendPushToUser($user_id, ['title' => 'Trừ điểm', 'body' => "Bạn bị trừ $points điểm", 'url' => '/history.php?tab=notifications']);
            header("Location: users.php?msg=points_deducted"); exit;
        }
    }
}

// Filter & Pagination
$search = isset($_GET['search']) ? $VSD->escape($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $VSD->escape($_GET['role']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where_clauses = [];
if($search) $where_clauses[] = "(u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
if($role_filter !== 'all') $where_clauses[] = "u.role='$role_filter'";
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$total_query = "SELECT COUNT(*) as total FROM users u $where_sql";
$total_result = $VSD->get_row($total_query);
$total_users = $total_result['total'] ?? 0;
$total_pages = ceil($total_users / $per_page);

$users_query = "
    SELECT u.id, u.username, u.email, u.role, u.avatar, u.created_at, u.last_activity,
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
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week
    FROM users
");

$admin_active_page = 'users';
include __DIR__ . '/../includes/admin-header.php';
?>

<style>
/* Custom Table Styles */
.table-custom tr {
    transition: all 0.2s ease;
}
.table-custom tr:hover td {
    background-color: oklch(var(--b2));
}
.user-card-stat {
    background: oklch(var(--b1));
    border: 1px solid oklch(var(--bc) / 0.08);
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s;
}
.user-card-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -5px oklch(var(--bc) / 0.1);
}
.user-card-stat .icon-box {
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
</style>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-users text-primary"></i>
                    Quản lý người dùng
                </h1>
                <p class="text-base-content/60 text-sm mt-1">Quản lý thành viên, phân quyền và điểm thưởng</p>
            </div>
            
            <button onclick="document.getElementById('createUserModal').showModal()" class="btn btn-primary">
                <i class="fa-solid fa-user-plus"></i>
                Thêm mới
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="user-card-stat">
                <div class="icon-box bg-primary/10 text-primary">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold"><?= number_format($stats['total_users']) ?></div>
                    <div class="text-xs text-base-content/60 font-medium uppercase">Tổng thành viên</div>
                </div>
            </div>

            <div class="user-card-stat">
                <div class="icon-box bg-secondary/10 text-secondary">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold"><?= number_format($stats['admin_count']) ?></div>
                    <div class="text-xs text-base-content/60 font-medium uppercase">Quản trị viên</div>
                </div>
            </div>

            <div class="user-card-stat">
                <div class="icon-box bg-info/10 text-info">
                    <i class="fa-regular fa-user"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold"><?= number_format($stats['user_count']) ?></div>
                    <div class="text-xs text-base-content/60 font-medium uppercase">Người dùng thường</div>
                </div>
            </div>

            <div class="user-card-stat">
                <div class="icon-box bg-accent/10 text-accent">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold">+<?= number_format($stats['new_users_week']) ?></div>
                    <div class="text-xs text-base-content/60 font-medium uppercase">Tuần này</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search -->
        <div class="card bg-base-100 shadow border border-base-200">
            <div class="card-body p-4">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40"></i>
                        <input type="text" name="search" placeholder="Tìm kiếm theo tên, email..." 
                               class="input input-bordered w-full pl-10" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="role" class="select select-bordered w-full md:w-48">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>Tất cả vai trò</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <button type="submit" class="btn btn-neutral">
                        Lọc kết quả
                    </button>
                    <?php if($search || $role_filter !== 'all'): ?>
                        <a href="users.php" class="btn btn-ghost">Xóa lọc</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card bg-base-100 shadow border border-base-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table table-custom w-full">
                    <thead>
                        <tr class="bg-base-200/50 text-base-content/70">
                            <th class="w-16 text-center">ID</th>
                            <th>Thành viên</th>
                            <th>Vai trò</th>
                            <th>Điểm thưởng</th>
                            <th>Đóng góp</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td class="text-center font-mono text-xs opacity-60">#<?= $user['id'] ?></td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary/10 to-secondary/10 grid place-items-center ring-1 ring-base-content/10">
                                                    <?php if(!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])): ?>
                                                        <img src="../uploads/avatars/<?= $user['avatar'] ?>" alt="" class="rounded-xl">
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-user text-primary/60"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-semibold"><?= htmlspecialchars($user['username']) ?></div>
                                                <div class="text-xs text-base-content/60"><?= htmlspecialchars($user['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($user['role'] === 'admin'): ?>
                                            <span class="badge badge-sm badge-secondary font-bold">Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-sm badge-ghost">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-warning flex items-center gap-1">
                                                <i class="fa-solid fa-coins text-xs"></i>
                                                <?= number_format($user['points']) ?>
                                            </span>
                                            <div class="text-[10px] space-x-1 opacity-70">
                                                <span class="text-success" title="Tổng kiếm"> +<?= number_format($user['total_earned']) ?></span>
                                                <span>|</span>
                                                <span class="text-error" title="Tổng tiêu"> -<?= number_format($user['total_spent']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tooltip" data-tip="<?= $user['approved_docs'] ?> đã duyệt / <?= $user['doc_count'] ?> tổng số">
                                            <div class="radial-progress text-primary text-[10px]" 
                                                 style="--value:<?= $user['doc_count'] > 0 ? ($user['approved_docs']/$user['doc_count'])*100 : 0 ?>; --size:2rem;">
                                                <?= $user['doc_count'] ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $is_online = (strtotime($user['last_activity']) > time() - 300);
                                        ?>
                                        <?php if($is_online): ?>
                                            <div class="badge badge-xs badge-success gap-1">
                                                <span class="w-1 h-1 rounded-full bg-white"></span> Online
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-base-content/50">
                                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <div class="dropdown dropdown-end">
                                            <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-square">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </div>
                                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow-lg bg-base-100 rounded-box w-52 border border-base-200">
                                                <li>
                                                    <a onclick="openPointsModal(<?= $user['id'] ?>, '<?= addslashes($user['username']) ?>', <?= $user['points'] ?>)">
                                                        <i class="fa-solid fa-coins text-warning"></i> Quản lý điểm
                                                    </a>
                                                </li>
                                                <li>
                                                    <a onclick="toggleRole(<?= $user['id'] ?>, '<?= $user['role'] ?>')">
                                                        <?php if($user['role'] === 'admin'): ?>
                                                            <i class="fa-solid fa-user-minus text-error"></i> Gỡ quyền Admin
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-user-shield text-success"></i> Cấp quyền Admin
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                                <li class="border-t border-base-200 mt-1 pt-1"></li>
                                                <li>
                                                    <a href="../profile.php?id=<?= $user['id'] ?>" target="_blank">
                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem hồ sơ
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-base-content/50">
                                    <i class="fa-solid fa-user-slash text-4xl mb-3 block"></i>
                                    Không tìm thấy người dùng nào
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="p-4 border-t border-base-200 flex justify-center">
                <div class="join">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>" 
                           class="join-item btn btn-sm <?= $page === $i ? 'btn-active btn-primary' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Points Modal -->
<dialog id="pointsModal" class="modal">
    <div class="modal-box">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
            <i class="fa-solid fa-coins text-warning"></i>
            Điều chỉnh điểm số
        </h3>
        <form method="POST" id="pointsForm">
            <input type="hidden" name="user_id" id="points_user_id">
            <input type="hidden" name="action" id="points_action">
            
            <div class="alert bg-base-200 mb-4 p-3">
                <i class="fa-solid fa-user text-primary"></i>
                <div>
                    <div class="font-bold" id="points_username_display">User</div>
                    <div class="text-xs opacity-70">Hiện có: <span id="points_current_display" class="font-mono font-bold">0</span> điểm</div>
                </div>
            </div>

            <div class="join w-full mb-4">
                <button type="button" class="join-item btn flex-1 btn-active" onclick="setPointsAction('add', this)">
                    <i class="fa-solid fa-plus"></i> Cộng
                </button>
                <button type="button" class="join-item btn flex-1" onclick="setPointsAction('deduct', this)">
                    <i class="fa-solid fa-minus"></i> Trừ
                </button>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Số điểm</span>
                </label>
                <div class="relative">
                    <i class="fa-solid fa-coins absolute left-4 top-1/2 -translate-y-1/2 text-base-content/40"></i>
                    <input type="number" name="points" class="input input-bordered w-full pl-10" placeholder="Nhập số điểm..." required min="1">
                </div>
            </div>

            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Lý do điều chỉnh (bắt buộc)</span>
                </label>
                <textarea name="reason" class="textarea textarea-bordered" rows="2" required placeholder="VD: Thưởng đóng góp, hoàn trả..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary w-full">Thực hiện</button>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Script -->
<script>
    function openPointsModal(id, username, points) {
        document.getElementById('points_user_id').value = id;
        document.getElementById('points_username_display').textContent = username;
        document.getElementById('points_current_display').textContent = points;
        
        // Reset to add
        setPointsAction('add', document.querySelector('.join-item'));
        
        document.getElementById('pointsModal').showModal();
    }

    function setPointsAction(action, btn) {
        // Toggle Buttons
        const btns = btn.parentElement.querySelectorAll('.btn');
        btns.forEach(b => b.classList.remove('btn-active', 'btn-primary', 'btn-error'));
        
        if(action === 'add') {
            btn.classList.add('btn-active', 'btn-primary');
            document.getElementById('points_action').value = 'add_points';
        } else {
            btn.classList.add('btn-active', 'btn-error');
            document.getElementById('points_action').value = 'deduct_points';
        }
    }

    function toggleRole(userId, currentRole) {
        const isPromoting = currentRole !== 'admin';
        const msg = isPromoting 
            ? 'Bạn có chắc chắn muốn cấp quyền Quản trị viên cho người này?' 
            : 'Bạn có chắc chắn muốn gỡ quyền Quản trị viên?';
        
        if(confirm(msg)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="user_id" value="${userId}"><input type="hidden" name="action" value="toggle_role">`;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
