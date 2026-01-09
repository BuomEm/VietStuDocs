<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/categories.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý hệ thống phân loại";

// Data Processing
$education_levels = getEducationLevels();
$category_stats = [];
$stats_result = $VSD->get_list("SELECT education_level, COUNT(*) as count FROM document_categories GROUP BY education_level");
foreach ($stats_result as $row) {
    $category_stats[$row['education_level']] = $row['count'];
}

$admin_active_page = 'categories';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-primary"></i>
                    Hệ thống phân loại
                </h1>
                <p class="text-base-content/60 text-sm mt-1">
                    Cấu trúc phân cấp tài liệu: Cấp học → Lớp/Ngành → Môn học
                </p>
            </div>
            
            <div class="alert alert-info shadow-sm py-2 px-4 max-w-md text-sm">
                <i class="fa-solid fa-code"></i>
                <span>Dữ liệu được quản lý bởi file JSON tại <code class="font-mono bg-base-100 px-1 rounded">/API</code></span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php 
            $levels_cfg = [
                'tieu_hoc' => ['color' => 'text-success', 'bg' => 'bg-success/10', 'icon' => 'fa-shapes'],
                'thcs' => ['color' => 'text-info', 'bg' => 'bg-info/10', 'icon' => 'fa-book-open'],
                'thpt' => ['color' => 'text-secondary', 'bg' => 'bg-secondary/10', 'icon' => 'fa-graduation-cap'],
                'dai_hoc' => ['color' => 'text-warning', 'bg' => 'bg-warning/10', 'icon' => 'fa-building-columns']
            ];
            foreach($education_levels as $level): 
                $code = $level['code'];
                $count = $category_stats[$code] ?? 0;
                $cfg = $levels_cfg[$code] ?? ['color'=>'text-primary', 'bg'=>'bg-primary/10', 'icon'=>'fa-folder'];
            ?>
            <div class="card bg-base-100 shadow-sm border border-base-200 hover:-translate-y-1 transition-transform">
                <div class="card-body p-5 flex flex-row items-center gap-4">
                    <div class="w-12 h-12 rounded-xl <?= $cfg['bg'] ?> <?= $cfg['color'] ?> grid place-items-center text-xl shadow-sm">
                        <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold tracking-tight"><?= number_format($count) ?></div>
                        <div class="text-xs uppercase font-bold text-base-content/50"><?= $level['name'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left: JSON Files (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-0">
                        <div class="p-4 border-b border-base-200 bg-base-200/30 flex justify-between items-center">
                            <h3 class="font-bold flex items-center gap-2">
                                <i class="fa-solid fa-file-code text-error"></i>
                                Tệp cấu hình JSON
                            </h3>
                            <a href="https://jsonlint.com" target="_blank" class="btn btn-xs btn-ghost text-base-content/60" title="Validate JSON">
                                Check JSON <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr class="text-xs text-base-content/50 bg-base-200/20">
                                        <th>File Name</th>
                                        <th>Mô tả chức năng</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $files = [
                                        ['name'=>'mon-hoc.json', 'desc'=>'Danh mục môn học Phổ thông (Cấp 1-2-3)', 'path'=>'/API/mon-hoc.json', 'icon'=>'fa-book'],
                                        ['name'=>'nganh-hoc.json', 'desc'=>'Danh mục Ngành & Nhóm ngành Đại học', 'path'=>'/API/nganh-hoc.json', 'icon'=>'fa-university'],
                                        ['name'=>'loai-tai-lieu.json', 'desc'=>'Phân loại định dạng tài liệu chung', 'path'=>'/API/loai-tai-lieu.json', 'icon'=>'fa-tags'],
                                    ];
                                    foreach($files as $f): 
                                    ?>
                                    <tr class="hover">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded bg-base-200 grid place-items-center text-base-content/70">
                                                    <i class="fa-solid <?= $f['icon'] ?>"></i>
                                                </div>
                                                <code class="text-sm font-bold"><?= $f['name'] ?></code>
                                            </div>
                                        </td>
                                        <td class="text-base-content/70 text-sm"><?= $f['desc'] ?></td>
                                        <td class="text-right">
                                            <a href="<?= $f['path'] ?>" target="_blank" class="btn btn-sm btn-ghost btn-square text-primary">
                                                <i class="fa-solid fa-up-right-from-square"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Guide Block -->
                <div class="card bg-base-100 shadow-sm border border-base-200 border-l-4 border-l-primary">
                    <div class="card-body">
                        <h3 class="font-bold text-lg mb-2">Hướng dẫn cập nhật</h3>
                        <p class="text-sm text-base-content/70 mb-4">
                            Hệ thống sử dụng cấu trúc tĩnh JSON để tối ưu tốc độ truy vấn cho các danh mục ít thay đổi.
                            Để thêm môn học hoặc ngành mới:
                        </p>
                        <ol class="list-decimal list-inside text-sm space-y-2 font-medium text-base-content/80">
                            <li>Truy cập thư mục <code class="bg-base-200 px-1 rounded">/API</code> trong source code.</li>
                            <li>Mở file JSON tương ứng (ví dụ: <code class="bg-base-200 px-1 rounded">mon-hoc.json</code>).</li>
                            <li>Thêm object mới tuân thủ cấu trúc hiện có (ID phải là duy nhất).</li>
                            <li>Lưu file và tải lại trang để thấy thay đổi.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Right: Logic Flows (1 col) -->
            <div class="space-y-6">
                <!-- High School Flow -->
                <div class="card bg-base-100 shadow-sm border border-base-200 p-1">
                    <div class="card-body p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-success/10 rounded-full blur-xl"></div>
                        <h3 class="font-bold flex items-center gap-2 mb-4 relative z-10">
                            <i class="fa-solid fa-school text-success"></i>
                            Luồng Phổ Thông
                        </h3>
                        
                        <ul class="steps steps-vertical text-sm w-full">
                            <li class="step step-success w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Cấp học</div>
                                    <div class="text-xs opacity-60">Tiểu học ➝ THPT</div>
                                </div>
                            </li>
                            <li class="step step-success w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Lớp</div>
                                    <div class="text-xs opacity-60">Lớp 1 ➝ Lớp 12</div>
                                </div>
                            </li>
                            <li class="step step-success w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Môn học</div>
                                    <div class="text-xs opacity-60">Toán, Văn, Anh...</div>
                                </div>
                            </li>
                             <li class="step step-neutral w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Loại tài liệu</div>
                                    <div class="text-xs opacity-60">SGK, Đề thi...</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- University Flow -->
                <div class="card bg-base-100 shadow-sm border border-base-200 p-1">
                    <div class="card-body p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-warning/10 rounded-full blur-xl"></div>
                        <h3 class="font-bold flex items-center gap-2 mb-4 relative z-10">
                            <i class="fa-solid fa-graduation-cap text-warning"></i>
                            Luồng Đại Học
                        </h3>
                        
                        <ul class="steps steps-vertical text-sm w-full">
                            <li class="step step-warning w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Cấp học</div>
                                    <div class="text-xs opacity-60">Đại học</div>
                                </div>
                            </li>
                            <li class="step step-warning w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Nhóm ngành</div>
                                    <div class="text-xs opacity-60">Kinh tế, Kỹ thuật...</div>
                                </div>
                            </li>
                            <li class="step step-warning w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Chuyên ngành</div>
                                    <div class="text-xs opacity-60">CNTT, Quản trị...</div>
                                </div>
                            </li>
                            <li class="step step-neutral w-full text-left">
                                <div class="text-left w-full pl-2">
                                    <div class="font-bold">Loại tài liệu</div>
                                    <div class="text-xs opacity-60">Giáo trình, Đồ án...</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
