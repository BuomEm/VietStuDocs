<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
require_once __DIR__ . '/../push/send_push.php';
require_once __DIR__ . '/../config/function.php'; // Ensure VSD and global functions are available

redirectIfNotAdmin();
$admin_id = getCurrentUserId();
$page_title = "Quản lý Gia sư - Admin Panel";
$admin_active_page = 'tutors';

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tutor_id = intval($_POST['tutor_id'] ?? 0);
    
    if ($tutor_id) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE tutors SET status='active', updated_at=NOW() WHERE user_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $tutor_id);
                if ($stmt->execute()) {
                    // Notify user
                    $VSD->insert('notifications', [
                        'user_id' => $tutor_id,
                        'title' => 'Đăng ký Gia sư thành công',
                        'message' => "Chúc mừng! Đăng ký làm Gia sư của bạn đã được Admin phê duyệt. Bây giờ bạn có thể nhận yêu cầu hỗ trợ.",
                        'type' => 'tutor_approved',
                        'ref_id' => $tutor_id
                    ]);
                    sendPushToUser($tutor_id, [
                        'title' => 'Đăng ký Gia sư thành công! 🎓',
                        'body' => "Bạn đã trở thành Gia sư chính thức. Chúc mừng!",
                        'url' => '/history.php?tab=notifications'
                    ]);
                    $_SESSION['flash_message'] = "Đã duyệt gia sư thành công!";
                }
            } else {
                $_SESSION['flash_error'] = "Lỗi hệ thống: " . $conn->error;
            }
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE tutors SET status='rejected', updated_at=NOW() WHERE user_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $tutor_id);
                if ($stmt->execute()) {
                    // Notify user
                    $VSD->insert('notifications', [
                        'user_id' => $tutor_id,
                        'title' => 'Cập nhật trạng thái Gia sư',
                        'message' => "Yêu cầu đăng ký hoặc trạng thái Gia sư của bạn đã bị từ chối/khóa bởi Admin.",
                        'type' => 'tutor_rejected',
                        'ref_id' => $tutor_id
                    ]);
                    sendPushToUser($tutor_id, [
                        'title' => 'Cập nhật trạng thái Gia sư ⚠️',
                        'body' => "Trạng thái Gia sư của bạn đã bị thay đổi. Nhấn để xem.",
                        'url' => '/history.php?tab=notifications'
                    ]);
                    $_SESSION['flash_message'] = "Đã từ chối gia sư!";
                }
            } else {
                $_SESSION['flash_error'] = "Lỗi hệ thống: " . $conn->error;
            }
        } elseif ($action === 'edit_prices') {
            $basic = intval($_POST['price_basic']);
            $standard = intval($_POST['price_standard']);
            $premium = intval($_POST['price_premium']);
            $stmt = $conn->prepare("UPDATE tutors SET price_basic=?, price_standard=?, price_premium=?, updated_at=NOW() WHERE user_id=?");
            if ($stmt) {
                $stmt->bind_param("iiii", $basic, $standard, $premium, $tutor_id);
                if ($stmt->execute()) {
                    // Notify user
                    $VSD->insert('notifications', [
                        'user_id' => $tutor_id,
                        'title' => 'Điều chỉnh bảng giá',
                        'message' => "Admin đã điều chỉnh bảng giá dịch vụ Gia sư của bạn: Basic ($basic), Standard ($standard), Premium ($premium).",
                        'type' => 'tutor_prices_updated',
                        'ref_id' => $tutor_id
                    ]);
                    sendPushToUser($tutor_id, [
                        'title' => 'Cập nhật bảng giá 💰',
                        'body' => "Admin đã điều chỉnh bảng giá dịch vụ của bạn.",
                        'url' => '/history.php?tab=notifications'
                    ]);
                    $_SESSION['flash_message'] = "Đã cập nhật bảng giá gia sư!";
                }
            } else {
                $_SESSION['flash_error'] = "Lỗi hệ thống: " . $conn->error;
            }
        }
    }
    header("Location: tutors.php");
    exit;
}

// Get Tutors List with Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$filter_status = $_GET['status'] ?? 'all';

$where_clause = "WHERE 1=1";
if ($filter_status !== 'all') {
    $where_clause .= " AND t.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

// Total count
$total_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tutors t $where_clause");
$total_rows = mysqli_fetch_assoc($total_result)['count'];
$total_pages = ceil($total_rows / $limit);

// Get data
$query = "
    SELECT t.*, u.username, u.email 
    FROM tutors t 
    JOIN users u ON t.user_id = u.id 
    $where_clause 
    ORDER BY FIELD(t.status, 'pending', 'active', 'rejected'), t.created_at DESC 
    LIMIT $offset, $limit
";
$result = mysqli_query($conn, $query);

include __DIR__ . '/../includes/admin-header.php';
?>

<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Quản lý Gia sư</h1>
            
            <div class="join">
                <a href="?status=all" class="join-item btn btn-sm <?= $filter_status === 'all' ? 'btn-active' : '' ?>">Tất cả</a>
                <a href="?status=pending" class="join-item btn btn-sm <?= $filter_status === 'pending' ? 'btn-active' : '' ?>">Chờ duyệt</a>
                <a href="?status=active" class="join-item btn btn-sm <?= $filter_status === 'active' ? 'btn-active' : '' ?>">Đang hoạt động</a>
            </div>
        </div>

        <?php if(isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-success mb-4">
                <i class="fa-solid fa-check-circle"></i>
                <span><?= $_SESSION['flash_message'] ?></span>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-error mb-4">
                <i class="fa-solid fa-circle-xmark"></i>
                <span><?= $_SESSION['flash_error'] ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="card bg-base-100 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Gia sư</th>
                            <th>Môn học</th>
                            <th>Giá (Basic/Std/Prem)</th>
                            <th>Ngày đăng ký</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="mask mask-squircle w-10 h-10 bg-neutral-focus text-neutral-content">
                                                    <span class="text-xl"><?= strtoupper(substr($row['username'], 0, 1)) ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold"><?= htmlspecialchars($row['username']) ?></div>
                                                <div class="text-xs opacity-50"><?= htmlspecialchars($row['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="max-w-xs truncate" title="<?= htmlspecialchars($row['subjects']) ?>">
                                            <?php 
                                            $subjects = explode(',', $row['subjects']);
                                            foreach(array_slice($subjects, 0, 2) as $subj) {
                                                echo '<span class="badge badge-ghost badge-sm mr-1">' . htmlspecialchars(trim($subj)) . '</span>';
                                            }
                                            if(count($subjects) > 2) echo '<span class="badge badge-ghost badge-sm">+' . (count($subjects) - 2) . '</span>';
                                            ?>
                                        </div>
                                    </td>
                                    <td class="font-mono text-sm">
                                        <?= $row['price_basic'] ?> / <?= $row['price_standard'] ?> / <?= $row['price_premium'] ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <?php if($row['status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Chờ duyệt</span>
                                        <?php elseif($row['status'] === 'active'): ?>
                                            <span class="badge badge-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">Từ chối</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="viewTutorModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn btn-sm btn-ghost btn-square">
                                            <i class="fa-solid fa-eye text-info"></i>
                                        </button>
                                        
                                        <?php if($row['status'] === 'pending' || $row['status'] === 'rejected'): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Xác nhận duyệt gia sư này?');">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="tutor_id" value="<?= $row['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-ghost btn-square text-success" title="Duyệt">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if($row['status'] === 'pending' || $row['status'] === 'active'): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Từ chối/Khóa gia sư này?');">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="tutor_id" value="<?= $row['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-ghost btn-square text-error" title="Từ chối/Khóa">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-base-content/70">Không tìm thấy dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
                <div class="p-4 border-t border-base-200 flex justify-center">
                    <div class="join">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $filter_status ?>" class="join-item btn btn-sm <?= $i == $page ? 'btn-active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View/Edit Modal -->
<dialog id="view_tutor_modal" class="modal">
  <div class="modal-box w-11/12 max-w-3xl">
    <form method="POST">
        <input type="hidden" name="action" value="edit_prices">
        <input type="hidden" name="tutor_id" id="modal_tutor_id">
        
        <h3 class="font-bold text-lg mb-4">Thông tin & Chỉnh sửa Gia sư</h3>
        
        <div id="modal_content" class="space-y-4">
            <!-- Content injected by JS -->
        </div>
        
        <div class="modal-action">
          <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
          <button type="button" onclick="document.getElementById('view_tutor_modal').close()" class="btn">Đóng</button>
        </div>
    </form>
  </div>
</dialog>

<script>
function viewTutorModal(data) {
    document.getElementById('modal_tutor_id').value = data.user_id;
    const content = `
        <div class="flex gap-4 items-center mb-6">
            <div class="avatar placeholder">
                <div class="bg-neutral text-neutral-content rounded-full w-24">
                    <span class="text-4xl">${data.username.charAt(0).toUpperCase()}</span>
                </div>
            </div>
            <div>
                <h2 class="text-2xl font-bold">${data.username}</h2>
                <p class="text-base-content/70">${data.email}</p>
                <div class="badge ${data.status === 'active' ? 'badge-success' : 'badge-warning'} mt-1">${data.status.toUpperCase()}</div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-control">
                <label class="label font-bold">Môn học</label>
                <div class="p-3 bg-base-200 rounded-lg">${data.subjects}</div>
            </div>
            <div class="form-control">
                <label class="label font-bold">Ngày đăng ký</label>
                <div class="p-3 bg-base-200 rounded-lg">${data.created_at}</div>
            </div>
        </div>
        
        <div class="form-control mt-2">
            <label class="label font-bold">Giới thiệu</label>
            <div class="p-4 bg-base-200 rounded-lg max-h-32 overflow-y-auto whitespace-pre-wrap">${data.bio}</div>
        </div>
        
        <div class="divider font-bold">Chỉnh sửa Bảng giá (Points)</div>
        <div class="grid grid-cols-3 gap-6">
            <div class="form-control">
                <label class="label justify-center bg-success/10 rounded-t-lg border-b border-success/20">
                    <span class="label-text-alt font-bold text-success">BASIC</span>
                </label>
                <input type="number" name="price_basic" value="${data.price_basic}" class="input input-bordered text-center font-bold" required>
            </div>
            <div class="form-control">
                <label class="label justify-center bg-info/10 rounded-t-lg border-b border-info/20">
                    <span class="label-text-alt font-bold text-info">STANDARD</span>
                </label>
                <input type="number" name="price_standard" value="${data.price_standard}" class="input input-bordered text-center font-bold" required>
            </div>
            <div class="form-control">
                <label class="label justify-center bg-warning/10 rounded-t-lg border-b border-warning/20">
                    <span class="label-text-alt font-bold text-warning">PREMIUM</span>
                </label>
                <input type="number" name="price_premium" value="${data.price_premium}" class="input input-bordered text-center font-bold" required>
            </div>
        </div>
    `;
    
    document.getElementById('modal_content').innerHTML = content;
    document.getElementById('view_tutor_modal').showModal();
}
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn);
?>
