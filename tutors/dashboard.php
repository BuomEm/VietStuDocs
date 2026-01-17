<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

// redirectIfNotLoggedIn(); // Allow guest access

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? getCurrentUserId() : null;
$user_info = $is_logged_in ? getUserInfo($user_id) : null;
$is_tutor = $is_logged_in ? isTutor($user_id) : false;
$tutor_profile = ($is_logged_in && $is_tutor) ? getTutorProfile($user_id) : null;
$page_title = "Gia sư DocShare";
$current_page = 'tutor_dashboard'; 

// Get incoming requests if tutor
$incoming_requests = ($is_logged_in && $is_tutor) ? getRequestsForTutor($user_id) : [];

// Get my requests (as student)
$my_requests = [];
if ($is_logged_in) {
    try {
        $pdo = getTutorDBConnection();
        $stmt = $pdo->prepare("SELECT r.*, t.username as tutor_name, t.avatar as tutor_avatar 
                              FROM tutor_requests r 
                              JOIN users t ON r.tutor_id = t.id 
                              WHERE r.student_id = ? 
                              ORDER BY r.created_at DESC");
        $stmt->execute([$user_id]);
        $my_requests = $stmt->fetchAll();
    } catch(Exception $e) {
        $my_requests = [];
    }
}
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
        --vsd-red: #991b1b;
        --red-gradient: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    /* Glass Card Style */
    .glass-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid var(--glass-border);
        border-radius: 2.5rem;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
    }

    /* Profile Header */
    .dashboard-header {
        display: flex;
        align-items: center;
        gap: 32px;
        margin-bottom: 48px;
        position: relative;
    }

    .header-info-vsd {
        flex: 1;
    }

    .header-info-vsd h1 {
        font-size: 2.5rem;
        font-weight: 1000;
        letter-spacing: -0.05em;
        line-height: 1;
        margin-bottom: 8px;
    }

    .header-info-vsd p {
        font-size: 1.1rem;
        font-weight: 600;
        opacity: 0.5;
    }

    .header-stats-vsd {
        display: flex;
        gap: 16px;
    }

    .stat-box-vsd {
        padding: 16px 28px;
        border-radius: 1.5rem;
        background: oklch(var(--b2) / 0.5);
        border: 1px solid oklch(var(--bc) / 0.05);
        text-align: center;
        min-width: 130px;
    }

    .stat-label-vsd {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        opacity: 0.4;
        margin-bottom: 4px;
    }

    .stat-value-vsd {
        font-size: 1.5rem;
        font-weight: 900;
        color: var(--vsd-red);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    /* Tabs Navigation */
    .tabs-nav-vsd {
        display: inline-flex;
        background: oklch(var(--b2) / 0.5);
        padding: 8px;
        border-radius: 2rem;
        border: 1px solid oklch(var(--bc) / 0.05);
        margin-bottom: 48px;
    }

    .tab-btn-vsd {
        padding: 12px 32px;
        border-radius: 1.5rem;
        font-size: 0.85rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        color: oklch(var(--bc) / 0.5);
        border: none;
        background: transparent;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tab-btn-vsd i {
        font-size: 1rem;
    }

    .tab-btn-vsd.active {
        background: white;
        color: var(--vsd-red);
        box-shadow: 0 10px 25px -10px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] .tab-btn-vsd.active {
        background: #1e293b;
    }

    /* Request Cards */
    .req-card-vsd {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 2rem;
        padding: 32px;
        transition: all 0.4s ease;
        position: relative;
        overflow: hidden;
    }

    .req-card-vsd:hover {
        transform: translateY(-4px);
        border-color: rgba(153, 27, 27, 0.2);
        box-shadow: 0 30px 60px -15px rgba(0,0,0,0.05);
    }

    .req-badge-vsd {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding: 6px 12px;
        border-radius: 100px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 16px;
    }

    .badge-pending-vsd { background: #fde68a; color: #92400e; }
    .badge-answered-vsd { background: #bbf7d0; color: #166534; }
    .badge-completed-vsd { background: oklch(var(--b3)); color: oklch(var(--bc) / 0.5); }

    .req-title-vsd {
        font-size: 1.25rem;
        font-weight: 900;
        margin-bottom: 12px;
        color: oklch(var(--bc));
    }

    .req-meta-vsd {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
        font-size: 0.8rem;
        font-weight: 600;
        opacity: 0.5;
    }

    .req-user-box-vsd {
        display: flex;
        align-items: center;
        gap: 12px;
        background: oklch(var(--b2) / 0.5);
        padding: 8px 16px;
        border-radius: 1rem;
        margin-bottom: 24px;
    }

    .small-avatar-vsd {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        overflow: hidden;
        background: var(--vsd-red);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 12px;
    }

    .small-avatar-vsd img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .vsd-btn-view {
        background: var(--vsd-red);
        color: white;
        border: none;
        height: 48px;
        padding: 0 24px;
        border-radius: 1rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .vsd-btn-view:hover {
        filter: brightness(1.1);
        box-shadow: 0 10px 20px -5px rgba(153, 27, 27, 0.3);
    }

    /* Table Styling for My Questions */
    .vsd-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px;
    }

    .vsd-table th {
        text-align: left;
        padding: 0 24px 12px;
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        opacity: 0.3;
    }

    .vsd-table tr[content] {
        background: var(--glass-bg);
        border-radius: 1.5rem;
        transition: all 0.3s ease;
    }

    .vsd-table tr[content]:hover {
        transform: scale(1.005);
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
    }

    .vsd-table td {
        padding: 24px;
    }

    .vsd-table td:first-child { border-radius: 1.5rem 0 0 1.5rem; }
    .vsd-table td:last-child { border-radius: 0 1.5rem 1.5rem 0; }

</style>

<body class="bg-base-100">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="dashboard-container">
                
                <?php if ($is_logged_in): ?>
                    <!-- FOR LOGGED IN USERS -->
                    <!-- Header with Stats -->
                    <div class="dashboard-header animate-in fade-in slide-in-from-top-4 duration-700">
                        <div class="header-info-vsd">
                            <h1>Bảng điều khiển</h1>
                            <p>Chào mừng trở lại, <?= htmlspecialchars($user_info['username'] ?? 'Thành viên') ?>!</p>
                        </div>
                        
                        <?php if ($is_tutor): ?>
                        <div class="header-stats-vsd">
                            <div class="stat-box-vsd">
                                <div class="stat-label-vsd">Đánh giá</div>
                                <div class="stat-value-vsd"><?= $tutor_profile['rating'] ?> <i class="fa-solid fa-star text-yellow-500 text-lg"></i></div>
                            </div>
                            <div class="stat-box-vsd">
                                <div class="stat-label-vsd">Đã trả lời</div>
                                <div class="stat-value-vsd"><?= $tutor_profile['total_answers'] ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($is_tutor): ?>
                            <div class="ml-8">
                                <a href="/tutors/profile_edit" class="btn btn-ghost btn-square rounded-2xl w-14 h-14 bg-base-200/50 hover:bg-base-200">
                                    <i class="fa-solid fa-user-gear text-xl"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tabs Navigation -->
                    <div class="tabs-nav-vsd animate-in fade-in zoom-in-95 duration-700">
                        <?php if ($is_tutor): ?>
                        <button onclick="switchTab(event, 'incoming')" 
                                class="tab-btn-vsd active"
                                id="tab-btn-incoming">
                            <i class="fa-solid fa-inbox text-blue-500"></i>
                            <span>Học viên hỏi (<?= count($incoming_requests) ?>)</span>
                        </button>
                        <?php endif; ?>
                        
                        <button onclick="switchTab(event, 'outgoing')" 
                                class="tab-btn-vsd <?= !$is_tutor ? 'active' : '' ?>"
                                id="tab-btn-outgoing">
                            <i class="fa-solid fa-paper-plane text-red-500"></i>
                            <span>Câu hỏi của tôi (<?= count($my_requests) ?>)</span>
                        </button>
                    </div>

                    <!-- INCOMING REQUESTS (Tutor Role) -->
                    <?php if ($is_tutor): ?>
                    <div id="incoming" class="tab-content animate-in fade-in slide-in-from-bottom-4 duration-500 block">
                        <div class="flex flex-wrap justify-between items-center mb-10 gap-4">
                            <h2 class="text-xs font-black uppercase tracking-[0.3em] opacity-30">Học viên hỏi</h2>
                            <div class="flex gap-3">
                                <a href="/tutors" class="vsd-btn-view h-12 px-6 rounded-2xl shadow-none hover:shadow-lg">
                                    <i class="fa-solid fa-plus text-xs"></i> <span>Đặt câu hỏi</span>
                                </a>
                                <a href="/tutors/withdraw" class="vsd-btn-view h-12 px-6 rounded-2xl shadow-none hover:shadow-lg bg-orange-600">
                                    <i class="fa-solid fa-money-bill-transfer text-xs"></i> <span>Rút tiền</span>
                                </a>
                                <a href="/tutors/profile_edit" class="vsd-btn-view h-12 px-6 rounded-2xl shadow-none hover:shadow-lg bg-emerald-600">
                                    <i class="fa-solid fa-user-pen text-xs"></i> <span>Cập nhật hồ sơ</span>
                                </a>
                            </div>
                        </div>

                        <?php if (empty($incoming_requests)): ?>
                            <div class="glass-card-vsd border-dashed border-2 flex flex-col items-center justify-center py-24 opacity-60">
                                <div class="w-20 h-20 rounded-full bg-emerald-500/5 flex items-center justify-center mb-8">
                                    <i class="fa-solid fa-graduation-cap text-3xl text-emerald-600 opacity-50"></i>
                                </div>
                                <h3 class="font-black text-2xl mb-3">Chưa có câu hỏi nào</h3>
                                <p class="text-sm font-bold opacity-40 mb-10 text-center max-w-sm">Đừng lo lắng, hãy đảm bảo hồ sơ của bạn luôn đầy đủ và ấn tượng để thu hút học viên!</p>
                                <a href="/tutors/profile_edit" class="vsd-btn-view bg-emerald-600">Hoàn thiện hồ sơ</a>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach($incoming_requests as $req): ?>
                                    <div class="req-card-vsd">
                                        <div class="flex justify-between items-start">
                                            <div class="req-badge-vsd <?= $req['status'] === 'pending' ? 'badge-pending-vsd' : ($req['status'] === 'answered' ? 'badge-answered-vsd' : 'badge-completed-vsd') ?>">
                                                <i class="fa-solid <?= $req['status'] === 'pending' ? 'fa-hourglass-half' : 'fa-check-double' ?>"></i>
                                                <?= $req['status'] === 'pending' ? 'Chờ trả lời' : ($req['status'] === 'answered' ? 'Đã trả lời' : 'Hoàn tất') ?>
                                            </div>
                                            <div class="text-[10px] font-black uppercase opacity-30"><?= $req['package_type'] ?></div>
                                        </div>
                                        
                                        <h3 class="req-title-vsd"><?= htmlspecialchars($req['title']) ?></h3>
                                        
                                        <div class="req-meta-vsd">
                                            <span><i class="fa-regular fa-clock"></i> <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></span>
                                            <span class="text-error font-black">+<?= $req['points_used'] ?> VSD</span>
                                        </div>
                                        
                                        <div class="req-user-box-vsd">
                                            <div class="small-avatar-vsd">
                                                <?php if(!empty($req['student_avatar']) && file_exists('../uploads/avatars/' . $req['student_avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $req['student_avatar'] ?>" />
                                                <?php else: ?>
                                                    <?= strtoupper(substr($req['student_name'] ?? 'U', 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs font-bold"><?= htmlspecialchars($req['student_name'] ?? 'Ẩn danh') ?></div>
                                        </div>
                                        
                                        <div class="flex gap-3">
                                            <a href="/tutors/request?id=<?= $req['id'] ?>" class="vsd-btn-view flex-1 text-center justify-center">
                                                <?= $req['status'] === 'pending' ? 'Trả lời ngay' : 'Xem chi tiết' ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- OUTGOING REQUESTS (Student Role) -->
                    <div id="outgoing" class="tab-content animate-in fade-in slide-in-from-bottom-4 duration-500 <?= $is_tutor ? 'hidden' : 'block' ?>">
                        <div class="flex justify-between items-center mb-10">
                            <h2 class="text-xs font-black uppercase tracking-[0.3em] opacity-30">Câu hỏi của tôi</h2>
                            <a href="/tutors" class="vsd-btn-view h-12 px-6 rounded-2xl shadow-none hover:shadow-lg">
                                <i class="fa-solid fa-plus text-xs"></i> <span>Thuê gia sư</span>
                            </a>
                        </div>

                        <?php if (empty($my_requests)): ?>
                            <div class="glass-card-vsd border-dashed border-2 flex flex-col items-center justify-center py-24 opacity-60">
                                <div class="w-20 h-20 rounded-full bg-primary/5 flex items-center justify-center mb-8">
                                    <i class="fa-solid fa-paper-plane text-3xl text-primary opacity-50"></i>
                                </div>
                                <h3 class="font-black text-2xl mb-3">Bạn chưa gửi câu hỏi nào</h3>
                                <p class="text-sm font-bold opacity-40 mb-10 text-center max-w-sm">Hãy tìm một gia sư phù hợp và đặt câu hỏi để nhận được sự trợ giúp tốt nhất!</p>
                                <a href="/tutors" class="vsd-btn-view">Khám phá ngay</a>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="vsd-table">
                                    <thead>
                                        <tr>
                                            <th>Tiêu đề & Nội dung</th>
                                            <th>Gia sư</th>
                                            <th>Trạng thái</th>
                                            <th>Khoản phí</th>
                                            <th>Ngày gửi</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($my_requests as $req): ?>
                                        <tr content>
                                            <td>
                                                <div class="font-black text-sm mb-1"><?= htmlspecialchars($req['title']) ?></div>
                                                <div class="text-[10px] opacity-40 font-bold max-w-[250px] truncate"><?= htmlspecialchars($req['content']) ?></div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="small-avatar-vsd !bg-emerald-500">
                                                        <?php if(!empty($req['tutor_avatar']) && file_exists('../uploads/avatars/' . $req['tutor_avatar'])): ?>
                                                            <img src="../uploads/avatars/<?= $req['tutor_avatar'] ?>" />
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($req['tutor_name'], 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs font-black"><?= htmlspecialchars($req['tutor_name']) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($req['status'] === 'pending'): ?>
                                                    <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest"><i class="fa-solid fa-circle text-[6px] mr-2"></i> Đang chờ</span>
                                                <?php elseif($req['status'] === 'answered'): ?>
                                                    <span class="text-[10px] font-black text-emerald-500 uppercase tracking-widest"><i class="fa-solid fa-circle text-[6px] mr-2"></i> Đã trả lời</span>
                                                <?php else: ?>
                                                    <span class="text-[10px] font-black opacity-30 uppercase tracking-widest">Hoàn tất</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="text-[10px] font-black uppercase opacity-30 mb-1"><?= $req['package_type'] ?></div>
                                                <div class="text-xs font-black text-red-500">-<?= $req['points_used'] ?> VSD</div>
                                            </td>
                                            <td>
                                                <div class="text-xs font-black opacity-40"><?= date('d/m/Y', strtotime($req['created_at'])) ?></div>
                                            </td>
                                            <td class="text-right">
                                                <a href="/tutors/request?id=<?= $req['id'] ?>" class="btn btn-ghost btn-circle">
                                                    <i class="fa-solid fa-chevron-right text-primary"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <!-- FOR GUEST USERS -->
                    <div class="flex flex-col items-center text-center max-w-4xl mx-auto py-12 animate-in fade-in slide-in-from-bottom-4 duration-700">
                        <div class="w-24 h-24 rounded-3xl bg-primary/10 text-primary flex items-center justify-center text-5xl mb-8 rotate-3">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <h1 class="text-4xl md:text-5xl font-black tracking-tighter mb-6">Kết nối với Gia sư chuyên nghiệp</h1>
                        <p class="text-xl text-base-content/60 font-semibold mb-12 max-w-2xl leading-relaxed">
                            Nhận giải đáp cho mọi thắc mắc học tập từ đội ngũ gia sư hàng đầu của DocShare. Giải quyết bài tập nhanh chóng và hiệu quả.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full mb-16 px-4">
                            <div class="glass-card-vsd !p-8 flex flex-col items-center">
                                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl mb-4">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <h3 class="font-black text-sm mb-2">Đội ngũ uy tín</h3>
                                <p class="text-xs opacity-50 font-bold">Các gia sư đều được xác minh danh tính và trình độ chuyên môn.</p>
                            </div>
                            <div class="glass-card-vsd !p-8 flex flex-col items-center">
                                <div class="w-12 h-12 rounded-xl bg-orange-500/10 text-orange-500 flex items-center justify-center text-xl mb-4">
                                    <i class="fa-solid fa-bolt"></i>
                                </div>
                                <h3 class="font-black text-sm mb-2">Phản hồi nhanh</h3>
                                <p class="text-xs opacity-50 font-bold">Câu hỏi của bạn sẽ được giải đáp sớm nhất có thể.</p>
                            </div>
                            <div class="glass-card-vsd !p-8 flex flex-col items-center">
                                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl mb-4">
                                    <i class="fa-solid fa-hand-holding-dollar"></i>
                                </div>
                                <h3 class="font-black text-sm mb-2">Chi phí hợp lý</h3>
                                <p class="text-xs opacity-50 font-bold">Chỉ thanh toán khi câu hỏi được giải đáp thỏa đáng.</p>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="/login?redirect=/tutors/dashboard" class="btn btn-primary btn-lg px-12 rounded-2xl normal-case text-base font-black shadow-xl shadow-primary/20">
                                Đăng nhập để bắt đầu
                            </a>
                            <a href="/tutors" class="btn btn-ghost btn-lg px-12 rounded-2xl normal-case text-base font-bold border-2 border-primary/20">
                                Khám phá danh sách gia sư
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
        
        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>

    <script>
    function switchTab(evt, tabId) {
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        document.getElementById(tabId).classList.remove('hidden');
        
        document.querySelectorAll('.tab-btn-vsd').forEach(el => el.classList.remove('active'));
        evt.currentTarget.classList.add('active');
    }
    </script>
</body>
</html>
