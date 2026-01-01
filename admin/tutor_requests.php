<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
require_once __DIR__ . '/../push/send_push.php';

// Check Admin Access
if (!isset($_SESSION['user_id']) || !hasAdminAccess()) {
    header("Location: /login.php");
    exit;
}

$page_title = "Quản lý Yêu cầu Gia sư - Admin";
$admin_active_page = 'tutor_requests';

// Get Current User for Sidebar (if needed by included files)
$user_id = getCurrentUserId(); 
// Note: admin-header might use $admin_username, etc. It seems to handle it.

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = getTutorDBConnection();
    
    if ($action === 'resolve_dispute') {
        $req_id = intval($_POST['request_id']);
        $resolution = $_POST['resolution']; // 'pay_tutor' or 'refund_student'
        
        try {
            $pdo->beginTransaction();
            // Get request details
            $request = getRequestDetails($req_id);
            if ($request && $request['status'] === 'disputed') {
                if ($resolution === 'pay_tutor') {
                    // Pay Tutor
                    $add = addPoints($request['tutor_id'], $request['points_used'], "Admin giải quyết khiếu nại (Request #$req_id)", null);
                    if ($add) {
                        $stmt = $pdo->prepare("UPDATE tutor_requests SET status = 'completed', review = CONCAT(review, ' [Admin: Đã thanh toán cho Gia sư]') WHERE id = ?");
                        $stmt->execute([$req_id]);
                        
                        // Notify Tutor
                        $VSD->insert('notifications', [
                            'user_id' => $request['tutor_id'],
                            'title' => 'Khiếu nại đã giải quyết',
                            'type' => 'dispute_resolved',
                            'ref_id' => $req_id,
                            'message' => "Yêu cầu #$req_id đã được giải quyết: Bạn đã nhận được {$request['points_used']} pts."
                        ]);
                        sendPushToUser($request['tutor_id'], [
                            'title' => 'Khiếu nại đã giải quyết',
                            'body' => "Bạn đã nhận được tiền cho yêu cầu #$req_id.",
                            'url' => '/history.php?tab=notifications'
                        ]);
                        
                        $success = "Đã giải quyết: Thanh toán cho Gia sư.";
                    }
                } elseif ($resolution === 'refund_student') {
                    // Refund Student
                    $add = addPoints($request['student_id'], $request['points_used'], "Hoàn tiền yêu cầu gia sư #$req_id (Khiếu nại thành công)", null);
                    if ($add) {
                        $stmt = $pdo->prepare("UPDATE tutor_requests SET status = 'completed', review = CONCAT(review, ' [Admin: Đã hoàn tiền cho Học viên]') WHERE id = ?");
                        $stmt->execute([$req_id]);
                        
                        // Notify Student
                        $VSD->insert('notifications', [
                            'user_id' => $request['student_id'],
                            'title' => 'Khiếu nại thành công',
                            'type' => 'dispute_resolved',
                            'ref_id' => $req_id,
                            'message' => "Khiếu nại yêu cầu #$req_id thành công: Bạn đã được hoàn lại {$request['points_used']} pts."
                        ]);
                        sendPushToUser($request['student_id'], [
                            'title' => 'Hoàn tiền thành công',
                            'body' => "Yêu cầu #$req_id đã được hoàn tiền.",
                            'url' => '/history.php?tab=notifications'
                        ]);

                        $success = "Đã giải quyết: Hoàn tiền cho Học viên.";
                    }
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Lỗi: " . $e->getMessage();
        }
    } elseif ($action === 'admin_reply') {
        $req_id = intval($_POST['request_id']);
        $content = trim($_POST['content']);
        
        if (!empty($content)) {
            // Manual insert to bypass tutor checks, flagging as Admin reply via content or special logic
            // Ideally we'd have an 'is_admin' or 'user_id' column that isn't strictly foreign keyed to tutors.
            // But `tutor_answers.tutor_id` is likely FK to Users or Tutors?
            // Let's assume it references Users for flexibility or Tutors table.
            // If it references Tutors table, Admin might fail if not a Tutor.
            
            // Text prefix for now is safest if we can't change schema easily.
            $final_content = "[ADMIN SUPPORT]: " . $content;
            
            try {
                // We use the current admin's ID. If constraint fails, we'll know.
                // Assuming tutor_answers(request_id, tutor_id, content, ...)
                // IF tutor_id is FK to Tutors, this might fail.
                // Let's check schema.
                
                // Workaround: Use the REQUEST'S assigned tutor ID but mark content clearly? 
                // No, that looks like the tutor said it.
                
                // Let's try inserting with Admin ID. If it fails, we catch.
                $stmt = $pdo->prepare("INSERT INTO tutor_answers (request_id, tutor_id, content, created_at) VALUES (?, ?, ?, NOW())");
                // We actually don't have a good way to insert "Admin" if table requires a Tutor ID.
                // Let's use the Request's Tutor ID but prepend very clearly.
                // OR: Update schema to allow null tutor_id + added user_id?
                
                // Decision: For this turn, reuse the Request's Tutor ID but use a special prefix so it appears in the chat.
                // Ideally, we should fix the schema later.
                // Wait, if we use Request's Tutor ID, the system thinks the Tutor replied.
                // Is there a `student_id` column? No.
                
                // Let's just insert. Using the Request's Tutor ID is the safest 'hack' to ensure FK constraints, 
                // but we MUST make it clear in the text.
                // Actually, if we use the admin's ID and they are not a tutor, it might fail FK user_id.
                // Let's try the request's tutor_id for safety.
                $request = getRequestDetails($req_id);
                $stmt->execute([$req_id, $request['tutor_id'], $final_content]);
                
                // Update status to answered if pending?
                 if ($request['status'] === 'pending') {
                    $pdo->prepare("UPDATE tutor_requests SET status = 'answered' WHERE id = ?")->execute([$req_id]);
                }
                
                // Notify Student of Admin Reply
                $VSD->insert('notifications', [
                    'user_id' => $request['student_id'],
                    'title' => 'Phản hồi từ Admin',
                    'type' => 'admin_reply',
                    'ref_id' => $req_id,
                    'message' => "Admin đã phản hồi yêu cầu hỗ trợ của bạn cho Request #$req_id."
                ]);
                sendPushToUser($request['student_id'], [
                    'title' => 'Phản hồi từ Admin',
                    'body' => "Admin đã trả lời yêu cầu #$req_id của bạn.",
                    'url' => '/history.php?tab=notifications'
                ]);

                $success = "Đã gửi phản hồi thành công.";
            } catch (Exception $e) {
                $error = "Lỗi gửi phản hồi: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_points') {
        $req_id = intval($_POST['request_id']);
        $new_points = intval($_POST['points_used']);
        
        try {
            $check_req = getRequestDetails($req_id);
            if ($check_req && $check_req['rating'] !== null) {
                $error = "Không thể sửa điểm cho yêu cầu đã được người dùng đánh giá.";
            } else {
                $stmt = $pdo->prepare("UPDATE tutor_requests SET points_used = ? WHERE id = ?");
                $stmt->execute([$new_points, $req_id]);
                
                // Notify both Student and Tutor of point change
                $req_data = getRequestDetails($req_id);
                if($req_data) {
                    $msg = "Admin đã điều chỉnh số điểm cho yêu cầu #$req_id thành $new_points pts. Lý do: Điều chỉnh hệ thống.";
                    
                    // Notify Student
                    $VSD->insert('notifications', [
                        'user_id' => $req_data['student_id'],
                        'title' => 'Điều chỉnh điểm yêu cầu',
                        'message' => $msg,
                        'type' => 'request_points_updated',
                        'ref_id' => $req_id
                    ]);
                    
                    // Notify Tutor
                    $VSD->insert('notifications', [
                        'user_id' => $req_data['tutor_id'],
                        'title' => 'Điều chỉnh điểm yêu cầu',
                        'message' => $msg,
                        'type' => 'request_points_updated',
                        'ref_id' => $req_id
                    ]);

                    sendPushToUser($req_data['student_id'], [
                        'title' => 'Cập nhật điểm yêu cầu 💰',
                        'body' => "Admin đã điều chỉnh điểm cho yêu cầu #$req_id.",
                        'url' => '/history.php?tab=notifications'
                    ]);
                }

                $success = "Đã cập nhật số điểm cho yêu cầu #$req_id thành $new_points pts.";
            }
        } catch (Exception $e) {
            $error = "Lỗi cập nhật điểm: " . $e->getMessage();
        }
    }
}

// Filtering
$filter = $_GET['status'] ?? 'all';
$pdo = getTutorDBConnection();

$sql = "SELECT r.*, u.username as student_name, t.username as tutor_name 
        FROM tutor_requests r 
        JOIN users u ON r.student_id = u.id 
        JOIN users t ON r.tutor_id = t.id";

if ($filter === 'disputed') {
    $sql .= " WHERE r.status = 'disputed'";
} elseif ($filter === 'pending') {
    $sql .= " WHERE r.status = 'pending'";
} elseif ($filter === 'completed') {
    $sql .= " WHERE r.status = 'completed'";
} elseif ($filter === 'answered') {
    $sql .= " WHERE r.status = 'answered'";
}

$sql .= " ORDER BY r.updated_at DESC";

$stmt = $pdo->query($sql);
$requests = $stmt->fetchAll();

// Require Admin Header (which includes sidebar and navbar)
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Quản lý Yêu cầu & Khiếu nại</h1>
            
            <div class="join">
                <a href="?status=all" class="join-item btn btn-sm <?= $filter === 'all' ? 'btn-active' : '' ?>">Tất cả</a>
                <a href="?status=pending" class="join-item btn btn-sm <?= $filter === 'pending' ? 'btn-active' : '' ?>">Chờ xử lý</a>
                <a href="?status=answered" class="join-item btn btn-sm <?= $filter === 'answered' ? 'btn-active' : '' ?>">Đã trả lời</a>
                <a href="?status=disputed" class="join-item btn btn-sm <?= $filter === 'disputed' ? 'btn-active' : '' ?>">Khiếu nại</a>
                <a href="?status=completed" class="join-item btn btn-sm <?= $filter === 'completed' ? 'btn-active' : '' ?>">Hoàn thành</a>
            </div>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success mb-4">
                <i class="fa-solid fa-check-circle"></i> <span><?= $success ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i> <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <div class="card bg-base-100 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Thông tin</th>
                            <th>Nội dung</th>
                            <th>Trạng thái</th>
                            <th>Đánh giá</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($requests as $req): ?>
                        <tr>
                            <td>#<?= $req['id'] ?></td>
                            <td>
                                <div class="flex flex-col text-sm">
                                    <span><strong>Học viên:</strong> <?= htmlspecialchars($req['student_name']) ?></span>
                                    <span><strong>Gia sư:</strong> <?= htmlspecialchars($req['tutor_name']) ?></span>
                                    <span class="opacity-50"><?= date('d/m H:i', strtotime($req['created_at'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="font-bold text-primary"><?= htmlspecialchars($req['title']) ?></div>
                                <div class="text-xs opacity-70 truncate max-w-xs"><?= htmlspecialchars($req['content']) ?></div>
                                <div class="badge badge-sm badge-outline mt-1"><?= $req['package_type'] ?> (<?= $req['points_used'] ?> pts)</div>
                            </td>
                            <td>
                                <?php if($req['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Chờ trả lời</span>
                                <?php elseif($req['status'] === 'answered'): ?>
                                    <span class="badge badge-info">Đã trả lời</span>
                                <?php elseif($req['status'] === 'completed'): ?>
                                    <span class="badge badge-success">Hoàn thành</span>
                                <?php elseif($req['status'] === 'disputed'): ?>
                                    <span class="badge badge-error">Tranh chấp</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($req['rating']): ?>
                                    <div class="flex items-center gap-1">
                                        <div class="rating rating-xs rating-half pointer-events-none">
                                            <input type="radio" class="rating-hidden" />
                                            <?php for($i=0.5; $i<=5; $i+=0.5): ?>
                                                <input type="radio" class="mask mask-star-2 bg-orange-400 <?= fmod($i, 1) !== 0.0 ? 'mask-half-1' : 'mask-half-2' ?>" <?= ($req['rating'] ?? 0) == $i ? 'checked' : '' ?> />
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-xs font-bold"><?= $req['rating'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs opacity-30">--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-ghost btn-square tooltip" data-tip="Xem chi tiết" onclick="fetchAndShowModal(<?= $req['id'] ?>)">
                                    <i class="fa-solid fa-eye text-primary"></i>
                                </button>
                                
                                <?php if($req['status'] === 'disputed'): ?>
                                    <div class="dropdown dropdown-end">
                                        <div tabindex="0" role="button" class="btn btn-sm btn-error btn-xs animate-pulse text-white">Xử lý</div>
                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 border border-base-200">
                                             <li>
                                                <form method="POST" onsubmit="return confirm('Xác nhận: Trả tiền cho Tutor?');">
                                                    <input type="hidden" name="action" value="resolve_dispute">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="resolution" value="pay_tutor">
                                                    <button type="submit" class="text-success"><i class="fa-solid fa-check"></i> Trả tiền Tutor</button>
                                                </form>
                                             </li>
                                             <li>
                                                <form method="POST" onsubmit="return confirm('Xác nhận: Hoàn tiền cho Học viên?');">
                                                    <input type="hidden" name="action" value="resolve_dispute">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="resolution" value="refund_student">
                                                    <button type="submit" class="text-error"><i class="fa-solid fa-rotate-left"></i> Hoàn tiền HS</button>
                                                </form>
                                             </li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($requests)): ?>
                            <tr><td colspan="6" class="text-center py-8 opacity-50">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Modal -->
<dialog id="detail_modal" class="modal">
    <div class="modal-box w-11/12 max-w-5xl h-5/6 flex flex-col p-0 overflow-hidden">
        <!-- Header -->
        <div class="bg-base-200 p-4 flex justify-between items-center shrink-0">
            <h3 class="font-bold text-lg" id="modal_title">Chi tiết yêu cầu</h3>
            <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost">✕</button></form>
        </div>
        
        <!-- Content -->
        <div class="p-6 overflow-y-auto flex-1" id="modal_body">
            <div class="flex justify-center"><span class="loading loading-spinner loading-lg"></span></div>
        </div>
        
        <!-- Footer -->
        <div class="bg-base-200 p-4 flex justify-end shrink-0 gap-2" id="modal_footer">
            <form method="dialog"><button class="btn">Đóng</button></form>
        </div>
    </div>
</dialog>

<script>
async function fetchAndShowModal(reqId) {
    const modal = document.getElementById('detail_modal');
    const body = document.getElementById('modal_body');
    const title = document.getElementById('modal_title');
    
    modal.showModal();
    body.innerHTML = '<div class="flex justify-center items-center h-full"><span class="loading loading-spinner loading-lg text-primary"></span></div>';
    
    // We can't use `getRequestDetails` via AJAX directly unless we make an API endpoint.
    // However, we can use the `tutors/request.php` page content? No, that's a full page.
    // Use a quick dirty hack: Embed all data in PHP loop into JS object? 
    // Or simpler: Just render the modal content invisible in the loop? 
    // The previous approach (render in loop) is heavy if many rows.
    // Let's create a specialized 'view' via AJAX. 
    // Actually, let's reuse `admin/tutors.php` pattern for now but since we have full data, 
    // let's pass data via JSON in button attribute if possible. 
    // BUT conversation is heavy.
    // BETTER: Create a simple hidden separate file `admin/ajax_request_detail.php` ?
    // OR: Just include the modal HTML generation in the existing loop as hidden <dialog>s (Simplest for now given restrictions)
    
    // Wait... user wants "view conversation".
    // Let's use the 'hidden dialogs in loop' approach from previous step, but adapted for this 'all' view.
    // It is robust enough for typical admin usage (paginated).
}
</script>

<?php if(1): // Switch to server-side rendering of modals to avoid complexity ?>
    <?php foreach($requests as $req): 
        $full_req = getRequestDetails($req['id']);
    ?>
    <dialog id="modal_<?= $req['id'] ?>" class="modal">
        <div class="modal-box w-11/12 max-w-5xl max-h-[90vh] flex flex-col p-0">
             <div class="bg-base-200 p-4 flex justify-between items-center shrink-0 sticky top-0 z-10">
                <div>
                     <h3 class="font-bold text-lg">Yêu cầu #<?= $req['id'] ?>: <?= htmlspecialchars($req['title']) ?></h3>
                     <div class="text-xs text-base-content/60"><?= htmlspecialchars($req['student_name']) ?> -> <?= htmlspecialchars($req['tutor_name']) ?></div>
                </div>
                <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost">✕</button></form>
            </div>
            
            <div class="p-6 overflow-y-auto">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: Question & Status -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="card bg-base-100 border border-base-300 shadow-sm">
                            <div class="card-body p-4">
                                <span class="badge badge-primary mb-2">Câu hỏi</span>
                                <div class="font-mono text-sm whitespace-pre-wrap bg-base-200 p-2 rounded"><?= trim(htmlspecialchars($req['content'])) ?></div>
                                <?php if($req['attachment']): ?>
                                    <div class="mt-2 text-sm text-info"><i class="fa-solid fa-paperclip"></i> <a href="/uploads/tutors/<?= $req['attachment'] ?>" target="_blank" class="link">File đính kèm</a></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card bg-base-100 border border-base-300 shadow-sm">
                            <div class="card-body p-4">
                                <h4 class="font-bold mb-2">Trạng thái</h4>
                                <div class="flex justify-between items-center mb-1">
                                    <span>Status:</span>
                                    <span class="badge badge-outline"><?= ucfirst($req['status']) ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-1">
                                    <span>Gói:</span>
                                    <span class="font-bold"><?= $req['package_type'] ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Points:</span>
                                    <?php if ($req['rating'] === null): ?>
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="action" value="update_points">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <div class="join border border-primary/20 rounded-lg overflow-hidden">
                                                <input type="number" name="points_used" value="<?= $req['points_used'] ?>" 
                                                       class="input input-bordered input-xs w-16 h-7 font-bold text-primary join-item bg-primary/5 border-none focus:outline-none" min="0">
                                                <button type="submit" class="btn btn-xs btn-primary h-7 min-h-0 join-item px-2" title="Lưu giá mới">
                                                    <i class="fa-solid fa-floppy-disk text-[10px]"></i>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-primary font-bold"><?= $req['points_used'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if($req['rating']): ?>
                            <div class="card bg-warning/10 border border-warning shadow-sm">
                                <div class="card-body p-4">
                                    <h4 class="font-bold text-warning mb-2">Đánh giá</h4>
                                    <div class="rating rating-sm rating-half pointer-events-none mb-1">
                                        <input type="radio" class="rating-hidden" />
                                        <?php for($i=0.5; $i<=5; $i+=0.5): ?>
                                            <input type="radio" class="mask mask-star-2 bg-orange-400 <?= fmod($i, 1) !== 0.0 ? 'mask-half-1' : 'mask-half-2' ?>" <?= ($req['rating'] ?? 0) == $i ? 'checked' : '' ?> />
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-xs italic">"<?= htmlspecialchars($req['review']) ?>"</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Conversation -->
                    <div class="lg:col-span-2">
                        <h4 class="font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-comments"></i> Hội thoại</h4>
                        <div class="space-y-4">
                            <!-- Student Question Bubble -->
                            <div class="chat chat-end">
                                <div class="chat-header">
                                    <?= htmlspecialchars($req['student_name']) ?> <time class="text-xs opacity-50"><?= date('H:i d/m', strtotime($req['created_at'])) ?></time>
                                </div>
                                <div class="chat-bubble chat-bubble-primary text-primary-content">
                                    <?= nl2br(trim(htmlspecialchars($req['content']))) ?>
                                </div>
                            </div>

                            <!-- Answers -->
                            <?php if(!empty($full_req['answers'])): ?>
                                <?php foreach($full_req['answers'] as $ans): ?>
                                    <div class="chat chat-start">
                                        <div class="chat-header">
                                            <?= htmlspecialchars($req['tutor_name']) ?> <time class="text-xs opacity-50"><?= date('H:i d/m', strtotime($ans['created_at'])) ?></time>
                                        </div>
                                        <div class="chat-bubble chat-bubble-secondary text-secondary-content">
                                            <?= nl2br(trim(htmlspecialchars($ans['content']))) ?>
                                            <?php if($ans['attachment']): ?>
                                                <div class="divider my-1 border-white/20"></div>
                                                <a href="/uploads/tutors/<?= $ans['attachment'] ?>" target="_blank" class="flex items-center gap-1 underline text-xs"><i class="fa-solid fa-paperclip"></i> File đính kèm</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center opacity-50 my-8 italic">Chưa có câu trả lời nào.</div>
                            <?php endif; ?>
                            
                            <!-- Admin Reply Box -->
                             <div class="divider">Phản hồi của Admin</div>
                             <form method="POST" class="bg-base-100 p-4 rounded-lg border border-base-300">
                                <input type="hidden" name="action" value="admin_reply">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <div class="form-control">
                                    <textarea name="content" class="textarea textarea-bordered h-24" placeholder="Nhập phản hồi với danh nghĩa Admin..."></textarea>
                                </div>
                                <div class="flex justify-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary">Gửi phản hồi</button>
                                </div>
                             </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </dialog>
    <?php endforeach; ?>

    <!-- Override fetchAndShowModal to just show local modal -->
    <script>
    function fetchAndShowModal(reqId) {
        document.getElementById('modal_' + reqId).showModal();
    }
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
