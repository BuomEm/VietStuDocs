<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/categories.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Categories Management - Admin Panel";

// Get education levels
$education_levels = getEducationLevels();

// Get unread notifications count
$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'categories';

// Count documents by category
$category_stats = [];
$stats_query = "SELECT 
    education_level,
    COUNT(*) as count 
    FROM document_categories 
    GROUP BY education_level";
$stats_result = mysqli_query($conn, $stats_query);
if ($stats_result) {
    while ($row = mysqli_fetch_assoc($stats_result)) {
        $category_stats[$row['education_level']] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.4.19/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen">
    <div class="drawer lg:drawer-open">
        <input id="admin-drawer" type="checkbox" class="drawer-toggle" />
        
        <!-- Page content -->
        <div class="drawer-content">
            <!-- Navbar for mobile -->
            <div class="navbar bg-base-100 lg:hidden sticky top-0 z-30 shadow-sm">
                <div class="flex-none">
                    <label for="admin-drawer" class="btn btn-square btn-ghost drawer-button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </label>
                </div>
                <div class="flex-1">
                    <span class="text-xl font-bold">Admin Panel</span>
                </div>
            </div>
            
            <!-- Main content -->
            <main class="p-4 lg:p-6">
                <!-- Header -->
                <div class="card bg-base-100 shadow-sm mb-6">
                    <div class="card-body">
                        <h1 class="card-title text-2xl gap-2">
                            <span class="text-3xl">📚</span>
                            Quản Lý Phân Loại (Categories V2)
                        </h1>
                        <p class="text-base-content/70">Hệ thống phân loại cascade: Cấp học → Lớp/Ngành → Môn học → Loại tài liệu</p>
                    </div>
                </div>

                <!-- Info Alert -->
                <div class="alert alert-info mb-6 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h3 class="font-bold">Hệ thống phân loại mới</h3>
                        <p class="text-sm">Dữ liệu môn học, ngành học và loại tài liệu được quản lý thông qua các file JSON trong thư mục <kbd class="kbd kbd-sm">API/</kbd></p>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <?php 
                    $colors = [
                        'tieu_hoc' => 'bg-success text-success-content',
                        'thcs' => 'bg-info text-info-content',
                        'thpt' => 'bg-secondary text-secondary-content',
                        'dai_hoc' => 'bg-warning text-warning-content'
                    ];
                    $icons = [
                        'tieu_hoc' => '🎒',
                        'thcs' => '📖',
                        'thpt' => '🎓',
                        'dai_hoc' => '🏛️'
                    ];
                    foreach($education_levels as $level): 
                        $count = $category_stats[$level['code']] ?? 0;
                        $bgColor = $colors[$level['code']] ?? 'bg-neutral text-neutral-content';
                        $icon = $icons[$level['code']] ?? '📄';
                    ?>
                    <div class="card <?= $bgColor ?> shadow-md">
                        <div class="card-body p-4">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl"><?= $icon ?></span>
                                <div>
                                    <h3 class="font-semibold"><?= $level['name'] ?></h3>
                                    <p class="text-2xl font-bold"><?= $count ?></p>
                                    <p class="text-xs opacity-80">tài liệu</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- JSON Files Management -->
                <div class="card bg-base-100 shadow-md mb-6">
                    <div class="card-body">
                        <h2 class="card-title text-lg mb-4">
                            <span class="text-xl">📁</span>
                            Các File JSON Quản Lý Categories
                        </h2>
                        
                        <div class="overflow-x-auto">
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Mô tả</th>
                                        <th>Đường dẫn</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><div class="badge badge-primary gap-1">📚 mon-hoc.json</div></td>
                                        <td class="text-sm">Danh sách môn học theo cấp học và lớp (Tiểu học, THCS, THPT)</td>
                                        <td><kbd class="kbd kbd-sm">API/mon-hoc.json</kbd></td>
                                        <td>
                                            <a href="/API/mon-hoc.json" target="_blank" class="btn btn-sm btn-ghost gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                Xem
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><div class="badge badge-secondary gap-1">🎓 nganh-hoc.json</div></td>
                                        <td class="text-sm">Danh sách nhóm ngành và ngành học đại học</td>
                                        <td><kbd class="kbd kbd-sm">API/nganh-hoc.json</kbd></td>
                                        <td>
                                            <a href="/API/nganh-hoc.json" target="_blank" class="btn btn-sm btn-ghost gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                Xem
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><div class="badge badge-accent gap-1">📄 loai-tai-lieu.json</div></td>
                                        <td class="text-sm">Danh sách loại tài liệu cho phổ thông và đại học</td>
                                        <td><kbd class="kbd kbd-sm">API/loai-tai-lieu.json</kbd></td>
                                        <td>
                                            <a href="/API/loai-tai-lieu.json" target="_blank" class="btn btn-sm btn-ghost gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                Xem
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Category Structure Preview -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Phổ thông flow -->
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <h2 class="card-title text-lg mb-4">
                                <span class="text-xl">🏫</span>
                                Luồng Phổ Thông
                            </h2>
                            <ul class="steps steps-vertical">
                                <li class="step step-primary" data-content="1">
                                    <div class="text-left">
                                        <p class="font-semibold">Cấp học</p>
                                        <p class="text-sm text-base-content/60">Tiểu học / THCS / THPT</p>
                                    </div>
                                </li>
                                <li class="step step-primary" data-content="2">
                                    <div class="text-left">
                                        <p class="font-semibold">Lớp</p>
                                        <p class="text-sm text-base-content/60">Lớp 1-5 (TH), Lớp 6-9 (THCS), Lớp 10-12 (THPT)</p>
                                    </div>
                                </li>
                                <li class="step step-primary" data-content="3">
                                    <div class="text-left">
                                        <p class="font-semibold">Môn học</p>
                                        <p class="text-sm text-base-content/60">Toán, Văn, Anh, Lý, Hóa...</p>
                                    </div>
                                </li>
                                <li class="step step-primary" data-content="4">
                                    <div class="text-left">
                                        <p class="font-semibold">Loại tài liệu</p>
                                        <p class="text-sm text-base-content/60">SGK, Bài tập, Đề thi, Đề kiểm tra...</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Đại học flow -->
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <h2 class="card-title text-lg mb-4">
                                <span class="text-xl">🎓</span>
                                Luồng Đại Học
                            </h2>
                            <ul class="steps steps-vertical">
                                <li class="step step-secondary" data-content="1">
                                    <div class="text-left">
                                        <p class="font-semibold">Cấp học</p>
                                        <p class="text-sm text-base-content/60">Đại học</p>
                                    </div>
                                </li>
                                <li class="step step-secondary" data-content="2">
                                    <div class="text-left">
                                        <p class="font-semibold">Nhóm ngành</p>
                                        <p class="text-sm text-base-content/60">CNTT, Kinh tế, Y dược, Kỹ thuật...</p>
                                    </div>
                                </li>
                                <li class="step step-secondary" data-content="3">
                                    <div class="text-left">
                                        <p class="font-semibold">Ngành học</p>
                                        <p class="text-sm text-base-content/60">Công nghệ thông tin, Quản trị kinh doanh...</p>
                                    </div>
                                </li>
                                <li class="step step-secondary" data-content="4">
                                    <div class="text-left">
                                        <p class="font-semibold">Loại tài liệu</p>
                                        <p class="text-sm text-base-content/60">Giáo trình, Slide, Luận văn, Đồ án...</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- How to Edit -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <h2 class="card-title text-lg mb-4">
                            <span class="text-xl">📝</span>
                            Hướng dẫn chỉnh sửa
                        </h2>
                        <div class="prose max-w-none">
                            <p>Để thêm/sửa/xóa categories, bạn cần chỉnh sửa trực tiếp các file JSON trong thư mục <kbd class="kbd kbd-sm">API/</kbd>:</p>
                            <ul class="list-disc list-inside space-y-2 mt-4">
                                <li><strong>Thêm môn học mới:</strong> Chỉnh sửa file <kbd class="kbd kbd-sm">API/mon-hoc.json</kbd></li>
                                <li><strong>Thêm ngành học mới:</strong> Chỉnh sửa file <kbd class="kbd kbd-sm">API/nganh-hoc.json</kbd></li>
                                <li><strong>Thêm loại tài liệu:</strong> Chỉnh sửa file <kbd class="kbd kbd-sm">API/loai-tai-lieu.json</kbd></li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning mt-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Đảm bảo giữ đúng format JSON khi chỉnh sửa. Sử dụng công cụ như <a href="https://jsonlint.com" target="_blank" class="link link-primary">jsonlint.com</a> để kiểm tra.</span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        
        <!-- Sidebar -->
        <div class="drawer-side z-40">
            <label for="admin-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <aside class="bg-primary text-primary-content w-64 min-h-full">
                <div class="p-4 border-b border-primary-content/20">
                    <h2 class="text-xl font-bold text-center">🔧 Admin Panel</h2>
                </div>
                <ul class="menu p-4 gap-1">
                    <li>
                        <a href="index.php" class="<?= $admin_active_page === 'dashboard' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="pending-documents.php" class="<?= $admin_active_page === 'pending' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Pending Documents
                            <?php if($unread_notifications > 0): ?>
                                <span class="badge badge-warning badge-sm"><?= $unread_notifications ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="all-documents.php" class="<?= $admin_active_page === 'documents' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            All Documents
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="<?= $admin_active_page === 'users' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="categories.php" class="<?= $admin_active_page === 'categories' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            Categories
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="<?= $admin_active_page === 'reports' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Reports
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="<?= $admin_active_page === 'settings' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </a>
                    </li>
                    
                    <div class="divider my-2"></div>
                    
                    <li>
                        <a href="../dashboard.php">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                            </svg>
                            Back to Site
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="text-error">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</body>
</html>

<?php mysqli_close($conn); ?>
