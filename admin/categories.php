<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/categories.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý phân loại - Admin Panel";

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

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-grid-3x3"></i>
                    Quản lý phân loại (Categories V2)
                </h2>
                <p class="text-base-content/70 mt-1">Hệ thống phân loại cascade: Cấp học → Lớp/Ngành → Môn học → Loại tài liệu</p>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Info Alert -->
        <div class="alert alert-info mb-6">
            <i class="fa-solid fa-circle-info text-2xl"></i>
            <div>
                <h4 class="font-bold">Hệ thống phân loại mới</h4>
                <div class="text-sm">Dữ liệu môn học, ngành học và loại tài liệu được quản lý thông qua các file JSON trong thư mục <code class="bg-base-300 px-1 rounded">API/</code></div>
            </div>
        </div>

        <!-- Statistics by Education Level -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php 
            $colors = [
                'tieu_hoc' => 'bg-success',
                'thcs' => 'bg-info',
                'thpt' => 'bg-secondary',
                'dai_hoc' => 'bg-warning'
            ];
            $icons = [
                'tieu_hoc' => 'fa-solid fa-backpack',
                'thcs' => 'fa-solid fa-book',
                'thpt' => 'fa-solid fa-school',
                'dai_hoc' => 'fa-solid fa-building-columns'
            ];
            foreach($education_levels as $level): 
                $count = $category_stats[$level['code']] ?? 0;
                $bgColor = $colors[$level['code']] ?? 'bg-secondary';
                $icon = $icons[$level['code']] ?? 'fa-regular fa-file';
            ?>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="avatar placeholder">
                            <div class="<?= $bgColor ?> text-white rounded w-12 h-12">
                                <i class="<?= $icon ?> text-2xl"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-semibold text-base-content/70"><?= $level['name'] ?></div>
                            <div class="text-3xl font-bold"><?= number_format($count) ?></div>
                            <div class="text-sm text-base-content/70">tài liệu</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- JSON Files Management -->
        <div class="card bg-base-100 shadow mb-6">
            <div class="card-header bg-base-200">
                <h3 class="card-title flex items-center gap-2">
                    <i class="fa-solid fa-folder"></i>
                    Các File JSON quản lý Categories
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Mô tả</th>
                                <th>Đường dẫn</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span class="badge badge-info badge-lg">
                                        <i class="fa-solid fa-book mr-1"></i>mon-hoc.json
                                    </span>
                                </td>
                                <td class="text-base-content/70">Danh sách môn học theo cấp học và lớp (Tiểu học, THCS, THPT)</td>
                                <td><code class="bg-base-300 px-2 py-1 rounded">API/mon-hoc.json</code></td>
                                <td>
                                    <a href="/API/mon-hoc.json" target="_blank" class="btn btn-ghost btn-sm btn-square">
                                        <i class="fa-solid fa-external-link"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary badge-lg">
                                        <i class="fa-solid fa-building-columns mr-1"></i>nganh-hoc.json
                                    </span>
                                </td>
                                <td class="text-base-content/70">Danh sách nhóm ngành và ngành học đại học</td>
                                <td><code class="bg-base-300 px-2 py-1 rounded">API/nganh-hoc.json</code></td>
                                <td>
                                    <a href="/API/nganh-hoc.json" target="_blank" class="btn btn-ghost btn-sm btn-square">
                                        <i class="fa-solid fa-external-link"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="badge badge-warning badge-lg">
                                        <i class="fa-regular fa-file-lines mr-1"></i>loai-tai-lieu.json
                                    </span>
                                </td>
                                <td class="text-base-content/70">Danh sách loại tài liệu cho phổ thông và đại học</td>
                                <td><code class="bg-base-300 px-2 py-1 rounded">API/loai-tai-lieu.json</code></td>
                                <td>
                                    <a href="/API/loai-tai-lieu.json" target="_blank" class="btn btn-ghost btn-sm btn-square">
                                        <i class="fa-solid fa-external-link"></i>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Category Flow Diagrams -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Phổ thông flow -->
            <div class="card bg-base-100 shadow">
                <div class="card-header bg-base-200">
                    <h3 class="card-title flex items-center gap-2">
                        <i class="fa-solid fa-school"></i>
                        Luồng Phổ Thông
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="steps steps-vertical steps-primary">
                        <li class="step step-primary">
                            <div>
                                <div class="font-semibold">Cấp học</div>
                                <div class="text-sm text-base-content/70">Tiểu học / THCS / THPT</div>
                            </div>
                        </li>
                        <li class="step step-primary">
                            <div>
                                <div class="font-semibold">Lớp</div>
                                <div class="text-sm text-base-content/70">Lớp 1-5 (TH), Lớp 6-9 (THCS), Lớp 10-12 (THPT)</div>
                            </div>
                        </li>
                        <li class="step step-primary">
                            <div>
                                <div class="font-semibold">Môn học</div>
                                <div class="text-sm text-base-content/70">Toán, Văn, Anh, Lý, Hóa...</div>
                            </div>
                        </li>
                        <li class="step step-primary">
                            <div>
                                <div class="font-semibold">Loại tài liệu</div>
                                <div class="text-sm text-base-content/70">SGK, Bài tập, Đề thi, Đề kiểm tra...</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Đại học flow -->
            <div class="card bg-base-100 shadow">
                <div class="card-header bg-base-200">
                    <h3 class="card-title flex items-center gap-2">
                        <i class="fa-solid fa-building-columns"></i>
                        Luồng Đại Học
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="steps steps-vertical steps-secondary">
                        <li class="step step-secondary">
                            <div>
                                <div class="font-semibold">Cấp học</div>
                                <div class="text-sm text-base-content/70">Đại học</div>
                            </div>
                        </li>
                        <li class="step step-secondary">
                            <div>
                                <div class="font-semibold">Nhóm ngành</div>
                                <div class="text-sm text-base-content/70">CNTT, Kinh tế, Y dược, Kỹ thuật...</div>
                            </div>
                        </li>
                        <li class="step step-secondary">
                            <div>
                                <div class="font-semibold">Ngành học</div>
                                <div class="text-sm text-base-content/70">Công nghệ thông tin, Quản trị kinh doanh...</div>
                            </div>
                        </li>
                        <li class="step step-secondary">
                            <div>
                                <div class="font-semibold">Loại tài liệu</div>
                                <div class="text-sm text-base-content/70">Giáo trình, Slide, Luận văn, Đồ án...</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- How to Edit -->
        <div class="card bg-base-100 shadow">
            <div class="card-header bg-base-200">
                <h3 class="card-title flex items-center gap-2">
                    <i class="fa-solid fa-pencil"></i>
                    Hướng dẫn chỉnh sửa
                </h3>
            </div>
            <div class="card-body">
                <p>Để thêm/sửa/xóa categories, bạn cần chỉnh sửa trực tiếp các file JSON trong thư mục <code class="bg-base-300 px-1 rounded">API/</code>:</p>
                
                <div class="space-y-2 mt-4">
                    <div class="flex items-start gap-3 p-3 bg-base-200 rounded">
                        <i class="fa-solid fa-plus text-success mt-1"></i>
                        <div>
                            <strong>Thêm môn học mới:</strong>
                            <span class="text-base-content/70">Chỉnh sửa file <code class="bg-base-300 px-1 rounded">API/mon-hoc.json</code></span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-base-200 rounded">
                        <i class="fa-solid fa-plus text-success mt-1"></i>
                        <div>
                            <strong>Thêm ngành học mới:</strong>
                            <span class="text-base-content/70">Chỉnh sửa file <code class="bg-base-300 px-1 rounded">API/nganh-hoc.json</code></span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-base-200 rounded">
                        <i class="fa-solid fa-plus text-success mt-1"></i>
                        <div>
                            <strong>Thêm loại tài liệu:</strong>
                            <span class="text-base-content/70">Chỉnh sửa file <code class="bg-base-300 px-1 rounded">API/loai-tai-lieu.json</code></span>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning mt-6">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong>Lưu ý:</strong> Đảm bảo giữ đúng format JSON khi chỉnh sửa. Sử dụng công cụ như 
                        <a href="https://jsonlint.com" target="_blank" class="link">jsonlint.com</a> để kiểm tra.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn); 
?>
