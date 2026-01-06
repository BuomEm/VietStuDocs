<?php
session_start();
require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';

$page_title = "Sơ Đồ Trang Web";
$current_page = 'sitemap';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $is_logged_in && isAdmin($_SESSION['user_id']);

// Define sitemap structure
$sitemap = [
    'main' => [
        'title' => 'Trang Chính',
        'icon' => 'fa-house',
        'color' => 'primary',
        'pages' => [
            ['url' => '/index.php', 'name' => 'Trang Chủ', 'desc' => 'Trang đích chính của website', 'icon' => 'fa-home', 'public' => true],
            ['url' => '/login.php', 'name' => 'Đăng Nhập / Đăng Ký', 'desc' => 'Truy cập tài khoản hoặc tạo tài khoản mới', 'icon' => 'fa-right-to-bracket', 'public' => true],
            ['url' => '/dashboard.php', 'name' => 'Bảng Điều Khiển', 'desc' => 'Quản lý tài liệu và xem tổng quan', 'icon' => 'fa-gauge-high', 'public' => false],
            ['url' => '/search.php', 'name' => 'Tìm Kiếm', 'desc' => 'Tìm kiếm tài liệu trong thư viện', 'icon' => 'fa-magnifying-glass', 'public' => true],
        ]
    ],
    'documents' => [
        'title' => 'Tài Liệu',
        'icon' => 'fa-file-lines',
        'color' => 'info',
        'pages' => [
            ['url' => '/upload.php', 'name' => 'Tải Lên Tài Liệu', 'desc' => 'Chia sẻ tài liệu với cộng đồng', 'icon' => 'fa-cloud-arrow-up', 'public' => false],
            ['url' => '/view.php', 'name' => 'Xem Tài Liệu', 'desc' => 'Đọc và tương tác với tài liệu', 'icon' => 'fa-eye', 'public' => true],
            ['url' => '/edit-document.php', 'name' => 'Chỉnh Sửa Tài Liệu', 'desc' => 'Cập nhật thông tin tài liệu của bạn', 'icon' => 'fa-pen-to-square', 'public' => false],
            ['url' => '/saved.php', 'name' => 'Tài Liệu Đã Lưu', 'desc' => 'Danh sách tài liệu yêu thích', 'icon' => 'fa-bookmark', 'public' => false],
        ]
    ],
    'tutors' => [
        'title' => 'Gia Sư',
        'icon' => 'fa-chalkboard-user',
        'color' => 'success',
        'pages' => [
            ['url' => '/tutors/index.php', 'name' => 'Danh Sách Gia Sư', 'desc' => 'Khám phá các gia sư chất lượng', 'icon' => 'fa-users', 'public' => true],
            ['url' => '/tutors/apply.php', 'name' => 'Đăng Ký Làm Gia Sư', 'desc' => 'Trở thành gia sư trên nền tảng', 'icon' => 'fa-user-plus', 'public' => false],
            ['url' => '/tutors/dashboard.php', 'name' => 'Dashboard Gia Sư', 'desc' => 'Quản lý yêu cầu và thu nhập', 'icon' => 'fa-chart-line', 'public' => false],
            ['url' => '/tutors/request.php', 'name' => 'Yêu Cầu Hỗ Trợ', 'desc' => 'Gửi yêu cầu hỗ trợ từ gia sư', 'icon' => 'fa-question-circle', 'public' => false],
            ['url' => '/tutors/profile_edit.php', 'name' => 'Chỉnh Sửa Hồ Sơ Gia Sư', 'desc' => 'Cập nhật thông tin gia sư', 'icon' => 'fa-id-card', 'public' => false],
        ]
    ],
    'user' => [
        'title' => 'Tài Khoản',
        'icon' => 'fa-user',
        'color' => 'warning',
        'pages' => [
            ['url' => '/profile.php', 'name' => 'Hồ Sơ Cá Nhân', 'desc' => 'Xem và chỉnh sửa thông tin cá nhân', 'icon' => 'fa-user-circle', 'public' => false],
            ['url' => '/user_profile.php', 'name' => 'Trang Cá Nhân Công Khai', 'desc' => 'Xem hồ sơ công khai của người dùng', 'icon' => 'fa-address-card', 'public' => true],
            ['url' => '/premium.php', 'name' => 'Gói Premium', 'desc' => 'Nâng cấp tài khoản Premium', 'icon' => 'fa-crown', 'public' => false],
            ['url' => '/history.php', 'name' => 'Lịch Sử Giao Dịch', 'desc' => 'Xem lịch sử hoạt động và giao dịch', 'icon' => 'fa-clock-rotate-left', 'public' => false],
            ['url' => '/transaction-details.php', 'name' => 'Chi Tiết Giao Dịch', 'desc' => 'Xem chi tiết từng giao dịch', 'icon' => 'fa-receipt', 'public' => false],
        ]
    ],
    'admin' => [
        'title' => 'Quản Trị Viên',
        'icon' => 'fa-shield-halved',
        'color' => 'error',
        'pages' => [
            ['url' => '/admin/index.php', 'name' => 'Tổng Quan Admin', 'desc' => 'Thống kê và quản lý hệ thống', 'icon' => 'fa-chart-pie', 'admin' => true],
            ['url' => '/admin/users.php', 'name' => 'Quản Lý Người Dùng', 'desc' => 'Quản lý tài khoản người dùng', 'icon' => 'fa-users-gear', 'admin' => true],
            ['url' => '/admin/all-documents.php', 'name' => 'Tất Cả Tài Liệu', 'desc' => 'Quản lý toàn bộ tài liệu', 'icon' => 'fa-folder-open', 'admin' => true],
            ['url' => '/admin/pending-docs.php', 'name' => 'Tài Liệu Chờ Duyệt', 'desc' => 'Phê duyệt tài liệu mới', 'icon' => 'fa-hourglass-half', 'admin' => true],
            ['url' => '/admin/categories.php', 'name' => 'Danh Mục', 'desc' => 'Quản lý danh mục tài liệu', 'icon' => 'fa-tags', 'admin' => true],
            ['url' => '/admin/tutors.php', 'name' => 'Quản Lý Gia Sư', 'desc' => 'Quản lý danh sách gia sư', 'icon' => 'fa-chalkboard-user', 'admin' => true],
            ['url' => '/admin/tutor_requests.php', 'name' => 'Yêu Cầu Gia Sư', 'desc' => 'Xem và xử lý yêu cầu gia sư', 'icon' => 'fa-list-check', 'admin' => true],
            ['url' => '/admin/transactions.php', 'name' => 'Giao Dịch', 'desc' => 'Quản lý giao dịch tài chính', 'icon' => 'fa-money-bill-transfer', 'admin' => true],
            ['url' => '/admin/reports.php', 'name' => 'Báo Cáo', 'desc' => 'Xem các báo cáo hệ thống', 'icon' => 'fa-flag', 'admin' => true],
            ['url' => '/admin/notifications.php', 'name' => 'Thông Báo', 'desc' => 'Quản lý thông báo hệ thống', 'icon' => 'fa-bell', 'admin' => true],
            ['url' => '/admin/settings.php', 'name' => 'Cài Đặt Hệ Thống', 'desc' => 'Cấu hình website', 'icon' => 'fa-gear', 'admin' => true],
        ]
    ],
    'other' => [
        'title' => 'Khác',
        'icon' => 'fa-ellipsis',
        'color' => 'neutral',
        'pages' => [
            ['url' => '/error.php', 'name' => 'Trang Lỗi', 'desc' => 'Trang hiển thị khi có lỗi', 'icon' => 'fa-triangle-exclamation', 'public' => true],
            ['url' => '/sitemap.php', 'name' => 'Sơ Đồ Trang Web', 'desc' => 'Bạn đang ở đây!', 'icon' => 'fa-sitemap', 'public' => true],
            ['url' => '/logout.php', 'name' => 'Đăng Xuất', 'desc' => 'Thoát khỏi tài khoản', 'icon' => 'fa-right-from-bracket', 'public' => false],
        ]
    ]
];

// Count total pages
$total_pages = 0;
$public_pages = 0;
$private_pages = 0;
$admin_pages = 0;

foreach ($sitemap as $section) {
    foreach ($section['pages'] as $page) {
        $total_pages++;
        if (isset($page['admin']) && $page['admin']) {
            $admin_pages++;
        } elseif (isset($page['public']) && $page['public']) {
            $public_pages++;
        } else {
            $private_pages++;
        }
    }
}
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<style>
    .sitemap-section {
        position: relative;
        overflow: hidden;
    }
    
    .sitemap-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--section-color), transparent);
        border-radius: 2rem 2rem 0 0;
    }
    
    .sitemap-link {
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .sitemap-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 0;
        background: var(--link-color);
        border-radius: 2px;
        transition: height 0.3s ease;
    }
    
    .sitemap-link:hover::before {
        height: 60%;
    }
    
    .sitemap-link:hover {
        transform: translateX(8px);
        background: oklch(var(--b2));
    }
    
    .tree-connector {
        position: relative;
    }
    
    .tree-connector::before {
        content: '';
        position: absolute;
        left: 1.5rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, oklch(var(--b3)), transparent);
    }
    
    .page-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        position: relative;
        z-index: 1;
        transition: all 0.3s ease;
    }
    
    .sitemap-link:hover .page-dot {
        transform: scale(1.3);
        box-shadow: 0 0 20px var(--link-color);
    }
    
    @keyframes float-slow {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(5deg); }
    }
    
    .float-animation {
        animation: float-slow 6s ease-in-out infinite;
    }
    
    /* Stats Cards */
    .stat-card {
        background: linear-gradient(135deg, oklch(var(--b1)) 0%, oklch(var(--b2)) 100%);
        border: 1px solid oklch(var(--b3));
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -10px oklch(var(--bc) / 0.1);
    }
</style>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <!-- Hero Header -->
        <div class="relative mb-12 overflow-hidden rounded-[3rem] bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 p-8 lg:p-12 border border-base-200">
            <!-- Background Decorations -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-secondary/5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
            
            <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-8">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="p-4 rounded-2xl bg-primary/10 text-primary shadow-lg shadow-primary/20 float-animation">
                            <i class="fa-solid fa-sitemap text-3xl"></i>
                        </div>
                        <div>
                            <h1 class="text-4xl lg:text-5xl font-black text-base-content">Sơ Đồ Trang Web</h1>
                            <p class="text-base-content/60 font-medium mt-1">Khám phá tất cả các trang trên VietStuDocs</p>
                        </div>
                    </div>
                    
                    <p class="text-base-content/50 max-w-xl mt-4">
                        Đây là bản đồ tổng quan về cấu trúc website, giúp bạn dễ dàng điều hướng và tìm kiếm các tính năng cần thiết.
                    </p>
                </div>
                
                <!-- Stats -->
                <div class="flex flex-wrap gap-4">
                    <div class="stat-card rounded-2xl p-6 text-center min-w-[120px]">
                        <div class="text-3xl font-black text-primary"><?= $total_pages ?></div>
                        <div class="text-xs font-bold uppercase tracking-wider text-base-content/40 mt-1">Tổng Trang</div>
                    </div>
                    <div class="stat-card rounded-2xl p-6 text-center min-w-[120px]">
                        <div class="text-3xl font-black text-success"><?= $public_pages ?></div>
                        <div class="text-xs font-bold uppercase tracking-wider text-base-content/40 mt-1">Công Khai</div>
                    </div>
                    <div class="stat-card rounded-2xl p-6 text-center min-w-[120px]">
                        <div class="text-3xl font-black text-warning"><?= $private_pages ?></div>
                        <div class="text-xs font-bold uppercase tracking-wider text-base-content/40 mt-1">Yêu Cầu Login</div>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="stat-card rounded-2xl p-6 text-center min-w-[120px]">
                        <div class="text-3xl font-black text-error"><?= $admin_pages ?></div>
                        <div class="text-xs font-bold uppercase tracking-wider text-base-content/40 mt-1">Admin Only</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="flex flex-wrap gap-4 mb-8 justify-center">
            <div class="flex items-center gap-2 px-4 py-2 bg-base-100 rounded-full border border-base-200 shadow-sm">
                <span class="w-3 h-3 rounded-full bg-success"></span>
                <span class="text-sm font-medium text-base-content/70">Công khai</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-base-100 rounded-full border border-base-200 shadow-sm">
                <span class="w-3 h-3 rounded-full bg-warning"></span>
                <span class="text-sm font-medium text-base-content/70">Yêu cầu đăng nhập</span>
            </div>
            <?php if ($is_admin): ?>
            <div class="flex items-center gap-2 px-4 py-2 bg-base-100 rounded-full border border-base-200 shadow-sm">
                <span class="w-3 h-3 rounded-full bg-error"></span>
                <span class="text-sm font-medium text-base-content/70">Chỉ Admin</span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sitemap Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($sitemap as $section_key => $section): ?>
                <?php 
                // Skip admin section for non-admin users
                if ($section_key === 'admin' && !$is_admin) continue;
                
                $color_class = match($section['color']) {
                    'primary' => 'oklch(var(--p))',
                    'info' => 'oklch(var(--in))',
                    'success' => 'oklch(var(--su))',
                    'warning' => 'oklch(var(--wa))',
                    'error' => 'oklch(var(--er))',
                    default => 'oklch(var(--n))'
                };
                ?>
                <div class="sitemap-section bg-base-100 rounded-[2rem] border border-base-200 overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-500" style="--section-color: <?= $color_class ?>">
                    <!-- Section Header -->
                    <div class="p-6 border-b border-base-200 bg-gradient-to-r from-base-100 to-base-200/30">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-<?= $section['color'] ?>/10 flex items-center justify-center shadow-lg shadow-<?= $section['color'] ?>/10">
                                <i class="fa-solid <?= $section['icon'] ?> text-2xl text-<?= $section['color'] ?>"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-black text-base-content"><?= $section['title'] ?></h2>
                                <p class="text-xs font-medium text-base-content/40 uppercase tracking-wider"><?= count($section['pages']) ?> trang</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pages List -->
                    <div class="p-4 tree-connector">
                        <?php foreach ($section['pages'] as $index => $page): ?>
                            <?php
                            // Determine access level
                            $is_admin_page = isset($page['admin']) && $page['admin'];
                            $is_public = isset($page['public']) && $page['public'];
                            
                            // Skip admin pages for non-admin
                            if ($is_admin_page && !$is_admin) continue;
                            
                            // Determine dot color
                            if ($is_admin_page) {
                                $dot_color = 'bg-error';
                                $link_color = 'oklch(var(--er))';
                            } elseif ($is_public) {
                                $dot_color = 'bg-success';
                                $link_color = 'oklch(var(--su))';
                            } else {
                                $dot_color = 'bg-warning';
                                $link_color = 'oklch(var(--wa))';
                            }
                            ?>
                            <a href="<?= $page['url'] ?>" 
                               class="sitemap-link flex items-center gap-4 p-4 rounded-xl group"
                               style="--link-color: <?= $link_color ?>">
                                <div class="page-dot <?= $dot_color ?> shadow-lg flex-shrink-0"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid <?= $page['icon'] ?> text-sm text-base-content/40 group-hover:text-<?= $section['color'] ?> transition-colors"></i>
                                        <span class="font-bold text-base-content group-hover:text-<?= $section['color'] ?> transition-colors truncate">
                                            <?= $page['name'] ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-base-content/40 mt-0.5 truncate"><?= $page['desc'] ?></p>
                                </div>
                                <i class="fa-solid fa-arrow-right text-base-content/20 group-hover:text-<?= $section['color'] ?> group-hover:translate-x-1 transition-all"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Quick Navigation -->
        <div class="mt-12 p-8 bg-gradient-to-br from-primary/5 to-secondary/5 rounded-[2.5rem] border border-base-200">
            <div class="text-center mb-8">
                <h3 class="text-2xl font-black text-base-content">Điều Hướng Nhanh</h3>
                <p class="text-base-content/50 mt-2">Truy cập nhanh các trang phổ biến nhất</p>
            </div>
            
            <div class="flex flex-wrap justify-center gap-4">
                <a href="/dashboard.php" class="btn btn-primary rounded-2xl gap-2 shadow-lg shadow-primary/20">
                    <i class="fa-solid fa-gauge-high"></i>
                    Dashboard
                </a>
                <a href="/search.php" class="btn btn-info rounded-2xl gap-2 shadow-lg shadow-info/20">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Tìm Kiếm
                </a>
                <a href="/upload.php" class="btn btn-success rounded-2xl gap-2 shadow-lg shadow-success/20">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Tải Lên
                </a>
                <a href="/tutors/index.php" class="btn btn-warning rounded-2xl gap-2 shadow-lg shadow-warning/20">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    Gia Sư
                </a>
                <a href="/premium.php" class="btn btn-secondary rounded-2xl gap-2 shadow-lg shadow-secondary/20">
                    <i class="fa-solid fa-crown"></i>
                    Premium
                </a>
            </div>
        </div>
        
        <!-- SEO Info -->
        <div class="mt-8 text-center">
            <p class="text-xs text-base-content/30">
                <i class="fa-solid fa-robot mr-1"></i>
                Trang này giúp các công cụ tìm kiếm và người dùng hiểu rõ hơn về cấu trúc website.
            </p>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>
