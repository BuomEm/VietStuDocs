<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
require_once __DIR__ . '/../push/send_push.php';

if (!isset($_SESSION['user_id']) || !hasAdminAccess()) {
    header("Location: /login.php"); exit;
}

$page_title = "Yêu cầu & Khiếu nại Gia sư";
$admin_active_page = 'tutor_requests';
$user_id = getCurrentUserId();
$pdo = getTutorDBConnection();

// --- Helper Functions ---
function isImageFile($filename) {
    if (!$filename) return false;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

// --- Handle Actions ---
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $rid = intval($_POST['request_id'] ?? 0);
    
    // Resolve Dispute
    if ($act === 'resolve_dispute') {
        $res = $_POST['resolution'];
        try {
            $pdo->beginTransaction();
            $req = getRequestDetails($rid);
            if ($req && $req['status'] === 'disputed') {
                if ($res === 'pay_tutor') {
                    if (addPoints($req['tutor_id'], $req['points_used'], "Admin giải quyết yêu cầu #$rid", null)) {
                        $pdo->prepare("UPDATE tutor_requests SET status='completed', review=CONCAT(review, ' [Admin: Đã thanh toán cho Tutor]') WHERE id=?")->execute([$rid]);
                         // Notify Tutor
                         $VSD->insert('notifications', ['user_id'=>$req['tutor_id'], 'title'=>'Khiếu nại được giải quyết', 'message'=>"Yêu cầu #$rid: Đã thanh toán {$req['points_used']} pts.", 'type'=>'dispute_resolved']);
                         sendPushToUser($req['tutor_id'], ['title'=>'Tiền về! 💰', 'body'=>"Yêu cầu #$rid đã được thanh toán.", 'url'=>'/history.php']);
                        $msg = ['success', 'Đã xử lý: Thanh toán cho Tutor.'];
                    }
                } elseif ($res === 'refund_student') {
                    if (addPoints($req['student_id'], $req['points_used'], "Hoàn tiền yêu cầu #$rid", null)) {
                        $pdo->prepare("UPDATE tutor_requests SET status='completed', review=CONCAT(review, ' [Admin: Đã hoàn tiền cho SV]') WHERE id=?")->execute([$rid]);
                        // Notify Student
                        $VSD->insert('notifications', ['user_id'=>$req['student_id'], 'title'=>'Khiếu nại thành công', 'message'=>"Yêu cầu #$rid: Đã hoàn tiền {$req['points_used']} pts.", 'type'=>'dispute_resolved']);
                        sendPushToUser($req['student_id'], ['title'=>'Hoàn tiền thành công ↩️', 'body'=>"Yêu cầu #$rid đã được hoàn tiền.", 'url'=>'/history.php']);
                        $msg = ['success', 'Đã xử lý: Hoàn tiền cho Học viên.'];
                    }
                }
            }
            $pdo->commit();
        } catch (Exception $e) { $pdo->rollBack(); $msg = ['error', $e->getMessage()]; }
    }
    
    // Admin Reply
    elseif ($act === 'admin_reply') {
        $content = trim($_POST['content']);
        $attachment = null;
        
        // Handle file upload
        if (!empty($_FILES['attachment']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/tutors/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
            $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $max_size = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($file_ext, $allowed_types)) {
                $msg = ['error', 'Định dạng file không được hỗ trợ!'];
            } elseif ($_FILES['attachment']['size'] > $max_size) {
                $msg = ['error', 'File quá lớn! Tối đa 10MB.'];
            } elseif ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $filename = 'admin_' . time() . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $filename)) {
                    $attachment = $filename;
                }
            }
        }
        
        if($content || $attachment) {
            $req = getRequestDetails($rid);
            $pdo->prepare("INSERT INTO tutor_answers (request_id, tutor_id, sender_id, content, attachment) VALUES (?, ?, ?, ?, ?)")
                ->execute([$rid, $req['tutor_id'], $user_id, "[ADMIN]: $content", $attachment]);
            if($req['status'] === 'pending') $pdo->prepare("UPDATE tutor_requests SET status='answered' WHERE id=?")->execute([$rid]);
             
             // Notify Student
             $VSD->insert('notifications', ['user_id'=>$req['student_id'], 'type'=>'admin_reply', 'title'=>'Phản hồi hỗ trợ', 'message'=>"Admin đã trả lời yêu cầu #$rid", 'ref_id'=>$rid]);
             sendPushToUser($req['student_id'], ['title'=>'Hỗ trợ mới 💬', 'body'=>"Admin vừa phản hồi yêu cầu của bạn.", 'url'=>'/tutor/requests.php']);
             $msg = ['success', 'Đã gửi phản hồi.' . ($attachment ? ' (có đính kèm file)' : '')];
        }
    }
    
    // Update Points
    elseif ($act === 'update_points') {
        $pts = intval($_POST['points_used']);
        if ($pts >= 0) {
            $pdo->prepare("UPDATE tutor_requests SET points_used=? WHERE id=?")->execute([$pts, $rid]);
            $msg = ['success', "Đã cập nhật giá trị: $pts pts"];
        }
    }
}

// --- Fetch Data ---
$filter = $_GET['status'] ?? 'all';
$sql = "SELECT r.*, u.username as student_name, u.avatar as student_avatar, t.username as tutor_name 
        FROM tutor_requests r 
        JOIN users u ON r.student_id = u.id 
        JOIN users t ON r.tutor_id = t.id";
if ($filter !== 'all') $sql .= " WHERE r.status = '$filter'";
$sql .= " ORDER BY CASE WHEN r.status='disputed' THEN 1 WHEN r.status='pending' THEN 2 ELSE 3 END, r.updated_at DESC";
$requests = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Page Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <div class="p-2 bg-primary/10 rounded-lg text-primary"><i class="fa-solid fa-comments-dollar"></i></div>
                    Yêu cầu & Khiếu nại
                </h1>
                <p class="text-base-content/60 text-sm mt-1">
                    Quản lý giao dịch thuê gia sư, hỗ trợ và giải quyết tranh chấp
                </p>
            </div>
            
            <div class="tabs tabs-boxed bg-base-100 p-1 shadow-sm border border-base-200">
                <?php 
                $tabs = [
                    'all' => 'Tất cả', 
                    'disputed' => 'Khiếu nại', 
                    'pending' => 'Chờ xử lý', 
                    'completed' => 'Hoàn thành'
                ];
                foreach($tabs as $k=>$v): 
                    $active = $filter === $k ? 'tab-active bg-primary text-white shadow-sm' : 'hover:bg-base-200';
                    $icon = $k==='disputed' ? '<i class="fa-solid fa-triangle-exclamation mr-1 text-warning"></i>' : '';
                ?>
                    <a href="?status=<?= $k ?>" class="tab tab-sm transition-all h-8 px-4 rounded-btn <?= $active ?>"><?= $icon ?><?= $v ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if($msg): ?>
            <div role="alert" class="alert alert-<?= $msg[0] ?> shadow-sm rounded-lg border border-<?= $msg[0] ?>/20">
                <i class="fa-solid fa-circle-info"></i>
                <span><?= $msg[1] ?></span>
            </div>
        <?php endif; ?>

        <!-- Requests Table -->
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead class="bg-base-200/50">
                        <tr>
                            <th class="w-16">ID</th>
                            <th>Thông tin Giao dịch</th>
                            <th>Nội dung Yêu cầu</th>
                            <th>Giá trị</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($requests)): ?>
                            <tr><td colspan="6" class="text-center py-12 text-base-content/40 italic flex-col gap-2">
                                <i class="fa-solid fa-clipboard-list text-3xl mb-2 opacity-50"></i><br>
                                Không có dữ liệu phù hợp
                            </td></tr>
                        <?php else: foreach($requests as $r): 
                             $st_cfg = match($r['status']){
                                 'disputed' => ['badge-error', 'fa-gavel'],
                                 'pending' => ['badge-warning', 'fa-clock'],
                                 'completed' => ['badge-success', 'fa-check'],
                                 'answered' => ['badge-info', 'fa-reply'],
                                 default => ['badge-ghost', 'fa-circle']
                             };
                        ?>
                        <tr class="hover group transition-colors">
                            <td class="font-mono opacity-50 text-xs">#<?= $r['id'] ?></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="avatar placeholder">
                                        <div class="w-9 h-9 rounded-full bg-base-200 text-xs border border-base-300">
                                            <span><?= strtoupper(substr($r['student_name'],0,1)) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-sm">
                                        <div class="font-bold flex items-center gap-1.5">
                                            <?= htmlspecialchars($r['student_name']) ?>
                                            <i class="fa-solid fa-arrow-right-long text-base-content/30 text-[10px]"></i>
                                            <?= htmlspecialchars($r['tutor_name']) ?>
                                        </div>
                                        <div class="text-xs opacity-50 font-mono"><?= date('d/m/y H:i', strtotime($r['created_at'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="max-w-[200px] lg:max-w-xs">
                                <div class="font-bold text-sm truncate"><?= htmlspecialchars($r['title']) ?></div>
                                <div class="text-xs opacity-60 truncate"><?= htmlspecialchars($r['content']) ?></div>
                            </td>
                            <td>
                                <div class="font-mono font-bold text-primary flex items-center gap-1">
                                    <?= number_format($r['points_used']) ?> <span class="text-[10px] opacity-70">pts</span>
                                </div>
                                <div class="badge badge-ghost badge-xs text-[9px] uppercase tracking-wider opacity-60"><?= $r['package_type'] ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $st_cfg[0] ?> badge-sm gap-1 pl-1.5 pr-2.5 py-2.5 shadow-sm">
                                    <i class="fa-solid <?= $st_cfg[1] ?> text-[10px]"></i> <?= ucfirst($r['status']) ?>
                                </span>
                                <?php if($r['rating']): ?>
                                    <div class="flex items-center gap-0.5 mt-1.5 text-warning text-xs tooltip tooltip-bottom" data-tip="Rating: <?= $r['rating'] ?>">
                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $r['rating']) ? '<i class="fa-solid fa-star text-[10px]"></i>' : '<i class="fa-regular fa-star text-[10px] opacity-30"></i>'; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <button onclick="document.getElementById('modal_<?= $r['id'] ?>').showModal()" class="btn btn-sm btn-ghost btn-square text-primary hover:bg-primary/10">
                                    <i class="fa-solid fa-up-right-from-square"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Details Modals -->
<?php foreach($requests as $req): 
    $full = getRequestDetails($req['id']);
?>
<dialog id="modal_<?= $req['id'] ?>" class="modal">
    <div class="modal-box w-11/12 max-w-6xl h-[85vh] p-0 flex flex-col bg-base-100 rounded-2xl overflow-hidden shadow-2xl">
        
        <!-- Modal Header -->
        <div class="bg-base-100 border-b border-base-200 p-4 flex justify-between items-center shrink-0 z-10 shadow-sm relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary grid place-items-center text-lg">
                    <i class="fa-solid fa-hashtag"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg flex items-center gap-2">
                        Yêu cầu hỗ trợ #<?= $req['id'] ?>
                        <?php if($req['status']==='disputed'): ?>
                            <span class="badge badge-error gap-1 animate-pulse"><i class="fa-solid fa-gavel"></i> Tranh chấp</span>
                        <?php endif; ?>
                    </h3>
                    <div class="text-xs text-base-content/60 flex items-center gap-2">
                        <i class="fa-regular fa-clock"></i> <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
                        <span>•</span>
                        Tạo bởi <span class="font-bold text-base-content/80"><?= htmlspecialchars($req['student_name']) ?></span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="this.closest('dialog').close()" class="btn btn-sm btn-circle btn-ghost hover:bg-base-200 text-base-content/60"><i class="fa-solid fa-times"></i></button>
        </div>

        <!-- Modal Body Container -->
        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden bg-base-200/30">
            
            <!-- LEFT PANEL: Info & Context (30%) -->
            <div class="lg:w-[320px] xl:w-[360px] border-r border-base-200 overflow-y-auto p-5 bg-base-100 space-y-5 hidden lg:block">
                
                <!-- Participants Card -->
                <div class="space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider opacity-40">Các bên tham gia</div>
                    
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 border border-base-200">
                        <div class="avatar placeholder">
                            <div class="w-10 h-10 rounded-full bg-primary text-primary-content">
                                <span><?= strtoupper(substr($req['student_name'],0,1)) ?></span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs opacity-60">Học viên</div>
                            <div class="font-bold text-sm truncate"><?= htmlspecialchars($req['student_name']) ?></div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 border border-base-200">
                        <div class="avatar placeholder">
                            <div class="w-10 h-10 rounded-full bg-secondary text-secondary-content">
                                <span><?= strtoupper(substr($req['tutor_name'],0,1)) ?></span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs opacity-60">Gia sư</div>
                            <div class="font-bold text-sm truncate"><?= htmlspecialchars($req['tutor_name']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details -->
                <div class="space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider opacity-40">Chi tiết giao dịch</div>
                    <div class="p-4 rounded-xl border border-primary/20 bg-primary/5 space-y-3">
                        <div class="flex justify-between items-end">
                            <span class="text-sm opacity-70">Giá trị:</span>
                            <?php if($req['rating']===null && hasAdminAccess()): ?>
                                <form method="POST" class="join h-7">
                                    <input type="hidden" name="action" value="update_points">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <input type="number" name="points_used" value="<?= $req['points_used'] ?>" class="join-item input input-xs input-bordered w-16 text-center focus:outline-none border-primary/30">
                                    <button class="join-item btn btn-xs btn-primary"><i class="fa-solid fa-check"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="font-bold text-lg text-primary"><?= number_format($req['points_used']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="w-full bg-primary/10 h-[1px]"></div>
                        <div class="flex justify-between text-xs">
                            <span class="opacity-70">Gói dịch vụ:</span>
                            <span class="font-bold opacity-90"><?= $req['package_type'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Dispute Management Box -->
                <?php if($req['status'] === 'disputed'): ?>
                <div class="divider text-error text-xs font-bold">XỬ LÝ TRANH CHẤP</div>
                <div class="card bg-base-100 border-2 border-error shadow-sm overflow-hidden">
                    <div class="bg-error/10 p-3 text-error text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-gavel"></i> Quyết định của Admin
                    </div>
                    <div class="p-3 grid gap-2">
                        <form method="POST" onsubmit="return confirm('Xác nhận hoàn tiền cho Học viên? Tiền sẽ được cộng lại ví người học.')">
                            <input type="hidden" name="action" value="resolve_dispute">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <button name="resolution" value="refund_student" class="btn btn-sm w-full btn-outline btn-error flex justify-between group">
                                <span>Hoàn tiền HS</span>
                                <i class="fa-solid fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                            </button>
                        </form> 
                        <div class="text-center text-[10px] opacity-40 font-bold">- HOẶC -</div>
                        <form method="POST" onsubmit="return confirm('Xác nhận thanh toán cho Gia sư? Tiền sẽ được chuyển cho Tutor.')">
                            <input type="hidden" name="action" value="resolve_dispute">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <button name="resolution" value="pay_tutor" class="btn btn-sm w-full btn-success text-white flex justify-between group">
                                <span>Trả tiền Tutor</span>
                                <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- RIGHT PANEL: Chat History (70%) -->
            <div class="flex-1 flex flex-col h-full relative gradient-bg">
                
                <!-- Chat View -->
                <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6" id="chat-container-<?= $req['id'] ?>">
                    
                    <!-- Original Request Bubble (Student) -->
                    <div class="chat chat-start">
                        <div class="chat-image avatar placeholder">
                            <div class="w-8 h-8 rounded-full bg-primary text-primary-content text-xs shadow-sm">
                                <span><?= strtoupper(substr($req['student_name'],0,1)) ?></span>
                            </div>
                        </div>
                        <div class="chat-header text-xs opacity-50 mb-1 ml-1">
                            <?= htmlspecialchars($req['student_name']) ?> <span class="opacity-50">• Hỏi lúc <?= date('H:i d/m', strtotime($req['created_at'])) ?></span>
                        </div>
                        <div class="chat-bubble chat-bubble-primary shadow-sm text-sm">
                            <div class="font-bold text-xs opacity-70 mb-1 border-b border-white/20 pb-1 uppercase tracking-wide">
                                <i class="fa-solid fa-circle-question mr-1"></i> <?= htmlspecialchars($req['title']) ?>
                            </div>
                            <?= nl2br(htmlspecialchars($req['content'])) ?>
                            <?php if($req['attachment']): ?>
                                <div class="mt-2 pt-2 border-t border-white/20">
                                    <?php if(isImageFile($req['attachment'])): ?>
                                        <div class="rounded-lg overflow-hidden border border-white/20">
                                            <img src="/uploads/tutors/<?= $req['attachment'] ?>" class="max-w-[200px] max-h-[200px] object-cover hover:scale-105 transition-transform cursor-pointer" onclick="window.open(this.src)">
                                        </div>
                                    <?php else: ?>
                                        <a href="/uploads/tutors/<?= $req['attachment'] ?>" target="_blank" class="btn btn-xs btn-ghost btn-active bg-white/20 text-white border-0 gap-2">
                                            <i class="fa-solid fa-paperclip"></i> Tải file đính kèm
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Answers Loop -->
                    <?php if(!empty($full['answers'])): ?>
                        <?php foreach($full['answers'] as $ans): 
                            $is_stu = ($ans['sender_id'] == $req['student_id']);
                            $is_admin = strpos($ans['content'], '[ADMIN]') !== false || (!$is_stu && $ans['sender_id'] != $req['tutor_id']);
                            
                            // Align: Student Left, Tutor/Admin Right (Like standard messaging where "They" are left, "You" are right. But here we are observer)
                            // Let's stick to: Student Left, Tutor Right to distinguish roles clearly.
                            // Actually, let's allow "Student" be Left (Start) and "Tutor" be Right (End) for visual separation.
                            $align = $is_stu ? 'chat-start' : 'chat-end';
                            
                            // Visual Config
                            if($is_stu) {
                                $bubble_cls = 'chat-bubble-primary';
                                $avatar_bg = 'bg-primary text-primary-content';
                                $name_tag = htmlspecialchars($ans['sender_name']);
                            } elseif($is_admin) {
                                $align = 'chat-end'; // Admin on right
                                $bubble_cls = 'bg-error text-white';
                                $avatar_bg = 'bg-error text-white';
                                $name_tag = 'ADMIN SUPPORT';
                            } else { // Tutor
                                $bubble_cls = 'chat-bubble-secondary';
                                $avatar_bg = 'bg-secondary text-secondary-content';
                                $name_tag = htmlspecialchars($ans['sender_name']);
                            }
                            
                            $msg_content = str_replace(['[ADMIN]: ', '[ADMIN SUPPORT]: '], '', $ans['content']);
                        ?>
                        <div class="chat <?= $align ?>">
                            <div class="chat-image avatar placeholder">
                                <div class="w-8 h-8 rounded-full <?= $avatar_bg ?> text-xs shadow-sm">
                                    <span><?= $is_admin ? '<i class="fa-solid fa-shield"></i>' : strtoupper(substr($ans['sender_name'],0,1)) ?></span>
                                </div>
                            </div>
                            <div class="chat-header text-xs opacity-50 mb-1 mx-1">
                                <?= $name_tag ?> <time class="opacity-50 ml-1"><?= date('H:i d/m', strtotime($ans['created_at'])) ?></time>
                            </div>
                            <div class="chat-bubble <?= $bubble_cls ?> text-sm shadow-sm relative group">
                                <?= nl2br(htmlspecialchars($msg_content)) ?>
                                
                                <?php if($ans['attachment']): ?>
                                    <div class="mt-2 pt-2 border-t border-white/20">
                                        <?php if(isImageFile($ans['attachment'])): ?>
                                            <div class="rounded-lg overflow-hidden border border-white/20 bg-black/10">
                                                <img src="/uploads/tutors/<?= $ans['attachment'] ?>" class="max-w-[200px] max-h-[200px] object-cover hover:scale-105 transition-transform cursor-pointer" onclick="window.open(this.src)">
                                            </div>
                                        <?php else: ?>
                                            <a href="/uploads/tutors/<?= $ans['attachment'] ?>" target="_blank" class="btn btn-xs btn-ghost btn-active bg-white/20 text-white border-0 gap-2">
                                                <i class="fa-solid fa-paperclip"></i> File đính kèm
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="divider text-xs opacity-40 font-mono my-8">Chưa có phản hồi</div>
                    <?php endif; ?>
                    
                </div>

                <!-- Admin Reply Area -->
                <div class="p-4 bg-base-100 border-t border-base-200 shrink-0">
                    <form method="POST" enctype="multipart/form-data" class="relative">
                        <input type="hidden" name="action" value="admin_reply">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        
                        <!-- File Preview Area -->
                        <div id="file-preview-<?= $req['id'] ?>" class="hidden mb-3 p-3 bg-base-200/50 rounded-xl border border-base-300">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i id="file-icon-<?= $req['id'] ?>" class="fa-solid fa-file text-primary text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div id="file-name-<?= $req['id'] ?>" class="font-medium text-sm truncate"></div>
                                    <div id="file-size-<?= $req['id'] ?>" class="text-xs text-base-content/60"></div>
                                </div>
                                <button type="button" onclick="clearFileInput(<?= $req['id'] ?>)" class="btn btn-sm btn-circle btn-ghost text-error hover:bg-error/10">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                            <img id="img-preview-<?= $req['id'] ?>" class="hidden mt-3 max-h-32 rounded-lg border border-base-300 object-cover">
                        </div>
                        
                        <div class="flex gap-2 items-end">
                            <!-- File Upload Button -->
                            <div class="relative">
                                <input type="file" name="attachment" id="file-input-<?= $req['id'] ?>" class="hidden" 
                                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar"
                                       onchange="handleFileSelect(this, <?= $req['id'] ?>)">
                                <button type="button" onclick="document.getElementById('file-input-<?= $req['id'] ?>').click()" 
                                        class="btn btn-square btn-ghost border border-base-300 hover:bg-primary/10 hover:border-primary hover:text-primary transition-all"
                                        title="Đính kèm file">
                                    <i class="fa-solid fa-paperclip"></i>
                                </button>
                            </div>
                            
                            <div class="flex-1">
                                <textarea name="content" class="textarea textarea-bordered w-full h-12 min-h-[3rem] max-h-32 leading-tight focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all resize-none shadow-inner bg-base-200/30" placeholder="Viết phản hồi với tư cách Admin..."></textarea>
                            </div>
                            <button class="btn btn-primary btn-square shadow-lg hover:scale-105 transition-transform">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                        <div class="text-xs text-base-content/40 mt-2 flex items-center gap-2">
                            <i class="fa-solid fa-info-circle"></i>
                            Hỗ trợ: ảnh, PDF, Word, Excel, ZIP (tối đa 10MB)
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</dialog>
<?php endforeach; ?>

<script>
function handleFileSelect(input, requestId) {
    const file = input.files[0];
    const preview = document.getElementById('file-preview-' + requestId);
    const fileName = document.getElementById('file-name-' + requestId);
    const fileSize = document.getElementById('file-size-' + requestId);
    const fileIcon = document.getElementById('file-icon-' + requestId);
    const imgPreview = document.getElementById('img-preview-' + requestId);
    
    if (file) {
        preview.classList.remove('hidden');
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Set icon based on file type
        const ext = file.name.split('.').pop().toLowerCase();
        const iconMap = {
            'pdf': 'fa-file-pdf text-error',
            'doc': 'fa-file-word text-info',
            'docx': 'fa-file-word text-info',
            'xls': 'fa-file-excel text-success',
            'xlsx': 'fa-file-excel text-success',
            'zip': 'fa-file-zipper text-warning',
            'rar': 'fa-file-zipper text-warning'
        };
        
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            fileIcon.className = 'fa-solid fa-image text-primary text-xl';
            // Show image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                imgPreview.src = e.target.result;
                imgPreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            imgPreview.classList.add('hidden');
            fileIcon.className = 'fa-solid ' + (iconMap[ext] || 'fa-file text-primary') + ' text-xl';
        }
    }
}

function clearFileInput(requestId) {
    document.getElementById('file-input-' + requestId).value = '';
    document.getElementById('file-preview-' + requestId).classList.add('hidden');
    document.getElementById('img-preview-' + requestId).classList.add('hidden');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
