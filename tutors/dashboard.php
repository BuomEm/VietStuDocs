<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$is_tutor = isTutor($user_id);
$tutor_profile = $is_tutor ? getTutorProfile($user_id) : null;
$page_title = "Tutor Dashboard - VietStuDocs";
$current_page = 'dashboard'; // Or 'tutors' to keep that active? Let's use 'tutors' context.
// Actually sidebar handles /tutor/ matching.

// Get incoming requests if tutor
$incoming_requests = $is_tutor ? getRequestsForTutor($user_id) : [];

// Get my requests (as student)
// We need a helper for this, adding ad-hoc query here for speed
$pdo = getTutorDBConnection();
$stmt = $pdo->prepare("SELECT r.*, t.username as tutor_name 
                      FROM tutor_requests r 
                      JOIN users t ON r.tutor_id = t.id 
                      WHERE r.student_id = ? 
                      ORDER BY r.created_at DESC");
$stmt->execute([$user_id]);
$my_requests = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="flex-1 p-6">
        <div class="container mx-auto">
            
            <!-- Profile Header (if Tutor) -->
            <?php if ($is_tutor): ?>
            <div class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="avatar placeholder">
                            <div class="bg-primary text-primary-content rounded-full w-16">
                                <span class="text-2xl"><i class="fa-solid fa-chalkboard-user"></i></span>
                            </div>
                        </div>
                        <div>
                            <h2 class="card-title">Tutor Dashboard</h2>
                            <p class="text-base-content/70">Chào mừng <?= htmlspecialchars($tutor_profile['username']) ?></p>
                        </div>
                        <div class="ml-auto flex gap-4 text-center">
                            <div class="bg-base-200 p-2 rounded-box min-w-[100px]">
                                <div class="text-xs text-secondary font-bold">RATING</div>
                                <div class="text-xl font-black"><?= $tutor_profile['rating'] ?> <i class="fa-solid fa-star text-yellow-500"></i></div>
                            </div>
                            <div class="bg-base-200 p-2 rounded-box min-w-[100px]">
                                <div class="text-xs text-primary font-bold">ANSWERS</div>
                                <div class="text-xl font-black"><?= $tutor_profile['total_answers'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- MODERN TAB NAVIGATION -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div class="inline-flex p-1.5 bg-base-300/30 backdrop-blur-md rounded-2xl border border-base-300/50 shadow-inner">
                    <?php if ($is_tutor): ?>
                    <button onclick="switchTab(event, 'incoming')" 
                            class="dashboard-tab active flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-300"
                            id="tab-btn-incoming">
                        <i class="fa-solid fa-inbox"></i>
                        <span>Câu hỏi từ học viên</span>
                        <?php if(!empty($incoming_requests)): ?>
                            <span class="badge badge-sm badge-primary ml-1"><?= count($incoming_requests) ?></span>
                        <?php endif; ?>
                    </button>
                    <?php endif; ?>
                    
                    <button onclick="switchTab(event, 'outgoing')" 
                            class="dashboard-tab <?= !$is_tutor ? 'active' : '' ?> flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-300"
                            id="tab-btn-outgoing">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Câu hỏi của tôi</span>
                    </button>
                </div>

                <?php if ($is_tutor): ?>
                <div class="flex gap-2">
                    <a href="/tutors/profile_edit" class="btn btn-ghost btn-sm rounded-xl border-base-300">
                        <i class="fa-solid fa-user-gear"></i> Cấu hình hồ sơ
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- INCOMING REQUESTS (Tutor Only) -->
            <?php if ($is_tutor): ?>
            <div id="incoming" class="tab-content animate-in fade-in slide-in-from-bottom-2 duration-500 <?= $is_tutor ? 'block' : 'hidden' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-inbox text-primary"></i> 
                        Câu hỏi từ học viên
                    </h3>
                </div>
                
                <?php if (empty($incoming_requests)): ?>
                    <div class="alert alert-info bg-base-200 border-none">
                        <i class="fa-solid fa-circle-info text-info"></i>
                        <span>Hiện tại chưa có câu hỏi nào. Hãy cập nhật hồ sơ để thu hút học viên nhé!</span>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach($incoming_requests as $req): ?>
                            <div class="card bg-base-100 shadow-sm hover:shadow-md transition-all border border-base-200">
                                <div class="card-body p-5">
                                    <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="badge <?= $req['package_type'] == 'premium' ? 'badge-warning' : ($req['package_type'] == 'standard' ? 'badge-info' : 'badge-success') ?> badge-outline uppercase text-xs font-bold">
                                                    <?= $req['package_type'] ?>
                                                </span>
                                                <span class="text-xs text-base-content/60"><i class="fa-regular fa-clock"></i> <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></span>
                                            </div>
                                            <h4 class="card-title text-lg hover:text-primary transition-colors">
                                                <a href="/tutors/request?id=<?= $req['id'] ?>"><?= htmlspecialchars($req['title']) ?></a>
                                                <?php if($req['status'] === 'pending'): ?>
                                                    <span class="badge badge-warning badge-sm animate-pulse">Mới</span>
                                                <?php endif; ?>
                                            </h4>
                                            <div class="flex items-center gap-2 mt-2 text-sm">
                                                <div class="avatar placeholder w-6 h-6 rounded-full bg-neutral text-neutral-content text-[10px] flex items-center justify-center">
                                                    <span><?= strtoupper(substr($req['student_name'], 0, 1)) ?></span>
                                                </div>
                                                <span class="font-medium"><?= htmlspecialchars($req['student_name']) ?></span>
                                            </div>
                                            <p class="mt-3 text-base-content/80 line-clamp-2 text-sm bg-base-200/50 p-3 rounded-lg border border-base-200">
                                                <?= htmlspecialchars($req['content']) ?>
                                            </p>
                                        </div>
                                        
                                        <div class="flex flex-col items-end gap-3 min-w-[120px]">
                                            <?php if($req['status'] === 'pending'): ?>
                                                <div class="badge badge-warning gap-1 p-3 w-full justify-center font-bold">
                                                    <i class="fa-solid fa-hourglass-half"></i> Chờ trả lời
                                                </div>
                                                <a href="/tutors/request?id=<?= $req['id'] ?>" class="btn btn-primary btn-sm w-full">
                                                    Trả lời ngay <i class="fa-solid fa-arrow-right"></i>
                                                </a>
                                            <?php elseif($req['status'] === 'answered'): ?>
                                                <div class="badge badge-success gap-1 p-3 w-full justify-center font-bold text-white">
                                                    <i class="fa-solid fa-check"></i> Đã trả lời
                                                </div>
                                                <a href="/tutors/request?id=<?= $req['id'] ?>" class="btn btn-ghost btn-sm w-full">Xem lại</a>
                                            <?php else: ?>
                                                <div class="badge badge-ghost gap-1 p-3 w-full justify-center font-bold">
                                                    <i class="fa-solid fa-check-double"></i> Hoàn tất
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- OUTGOING REQUESTS (My Questions) -->
            <div id="outgoing" class="tab-content animate-in fade-in slide-in-from-bottom-2 duration-500 <?= $is_tutor ? 'hidden' : 'block' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-secondary"></i>
                        Câu hỏi tôi đã gửi
                    </h3>
                    <a href="/tutors" class="btn btn-primary btn-sm gap-2">
                        <i class="fa-solid fa-plus"></i> Đặt câu hỏi mới
                    </a>
                </div>
                
                <?php if (empty($my_requests)): ?>
                    <div class="flex flex-col items-center justify-center py-16 bg-base-200/30 rounded-xl border border-dashed border-base-300">
                        <div class="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4 text-primary">
                            <i class="fa-solid fa-question text-3xl"></i>
                        </div>
                        <p class="text-lg font-semibold mb-2">Bạn chưa đặt câu hỏi nào</p>
                        <p class="text-base-content/60 mb-6 text-sm max-w-md text-center">Hãy tìm một gia sư phù hợp và đặt câu hỏi để nhận được sự trợ giúp tốt nhất nhé!</p>
                        <a href="/tutors" class="btn btn-primary">Tìm Gia Sư Ngay</a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-lg border border-base-200">
                        <table class="table table-zebra w-full whitespace-nowrap">
                            <thead class="bg-base-200/50 text-base-content/80 font-bold uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="py-4">Tiêu đề</th>
                                    <th>Gia sư</th>
                                    <th>Trạng thái</th>
                                    <th>Gói / Điểm</th>
                                    <th>Ngày tạo</th>
                                    <th class="text-right">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($my_requests as $req): ?>
                                <tr class="hover:bg-base-100 transition-colors">
                                    <td class="max-w-[200px] md:max-w-[300px]">
                                        <div class="flex items-center gap-3">
                                            <div class="font-bold truncate text-base text-primary">
                                                <?= htmlspecialchars($req['title']) ?>
                                            </div>
                                        </div>
                                        <div class="text-xs text-base-content/50 truncate mt-1 max-w-[200px]">
                                            <?= htmlspecialchars(mb_strimwidth($req['content'], 0, 50, "...")) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="avatar placeholder w-6 h-6 rounded-full bg-neutral text-neutral-content text-[10px] flex items-center justify-center">
                                                <span><?= strtoupper(substr($req['tutor_name'], 0, 1)) ?></span>
                                            </div>
                                            <span class="font-medium text-sm"><?= htmlspecialchars($req['tutor_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($req['status'] === 'pending'): ?>
                                            <span class="badge badge-warning badge-sm gap-1 font-semibold">
                                                <i class="fa-solid fa-spinner fa-spin-pulse text-[10px]"></i> Đang chờ
                                            </span>
                                        <?php elseif($req['status'] === 'answered'): ?>
                                            <span class="badge badge-success badge-sm gap-1 font-semibold text-white">
                                                <i class="fa-solid fa-check text-[10px]"></i> Đã trả lời
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-ghost badge-sm font-semibold"><?= ucfirst($req['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold uppercase tracking-wide opacity-70"><?= $req['package_type'] ?></span>
                                            <span class="text-error font-bold text-sm">-<?= $req['points_used'] ?> pts</span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-base-content/70">
                                        <?= date('d/m', strtotime($req['created_at'])) ?>
                                        <span class="text-xs opacity-50 block"><?= date('H:i', strtotime($req['created_at'])) ?></span>
                                    </td>
                                    <td class="text-right">
                                        <a href="/tutors/request?id=<?= $req['id'] ?>" class="btn btn-sm btn-ghost btn-square tooltip tooltip-left" data-tip="Xem chi tiết">
                                            <i class="fa-solid fa-arrow-right-long text-primary"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div> <!-- end drawer-content -->
</div> <!-- end drawer -->

<style>
.dashboard-tab {
    @apply transition-all duration-200 cursor-pointer;
    color: oklch(var(--bc) / 0.6);
}
.dashboard-tab:hover {
    color: oklch(var(--p));
    background-color: oklch(var(--p) / 0.05);
}
.dashboard-tab.active {
    background-color: oklch(var(--b1));
    color: oklch(var(--p));
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}
/* Tailwind Animate utility simulation if not present */
.animate-in {
    animation-duration: 0.3s;
    animation-fill-mode: both;
}
.fade-in { animation-name: fadeIn; }
.slide-in-from-bottom-2 { animation-name: slideInBottom; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideInBottom { from { transform: translateY(8px); } to { transform: translateY(0); } }
</style>

<script>
function switchTab(evt, tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
        el.classList.remove('block');
    });
    
    // Show selected
    const selectedContent = document.getElementById(tabId);
    selectedContent.classList.remove('hidden');
    selectedContent.classList.add('block');
    
    // Update tab button classes
    document.querySelectorAll('.dashboard-tab').forEach(el => el.classList.remove('active'));
    evt.currentTarget.classList.add('active');
}
</script>
</body>
</html>
