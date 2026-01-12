<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/categories.php';

$user_id = getCurrentUserId();
$doc_id = intval($_GET['id'] ?? 0);

if($doc_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Get document - only owner can edit
$doc = db_get_row("SELECT d.*, aa.rejection_reason, dp.notes as admin_notes, dp.admin_points
     FROM documents d 
     LEFT JOIN admin_approvals aa ON d.id = aa.document_id AND d.status = 'rejected'
     LEFT JOIN docs_points dp ON d.id = dp.document_id
     WHERE d.id=$doc_id AND d.user_id=$user_id");

if(!$doc) {
    header("Location: dashboard.php?error=not_found");
    exit;
}

// Get current document category
$current_category = getDocumentCategory($doc_id);

// Get education levels for the cascade selection
$education_levels = getEducationLevels();

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Double check ownership (already checked in $doc fetch)
        $file_path = "uploads/" . $doc['file_name'];
        if(file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete thumbnail if it exists
        if (!empty($doc['thumbnail_path']) && file_exists($doc['thumbnail_path'])) {
            unlink($doc['thumbnail_path']);
        }
        
        // Delete converted PDF if it exists
        if (!empty($doc['converted_pdf_path']) && file_exists($doc['converted_pdf_path'])) {
            unlink($doc['converted_pdf_path']);
        }

        if(db_query("DELETE FROM documents WHERE id=$doc_id AND user_id=$user_id")) {
            header("Location: dashboard.php?msg=deleted");
            exit;
        } else {
            $error = "Lỗi khi xóa tài liệu: " . db_error();
        }
    }
    
    $description = !empty($_POST['description']) ? db_escape(trim($_POST['description'])) : '';
    $is_public = isset($_POST['is_public']) && $_POST['is_public'] == '1' ? 1 : 0;
    
    // Get pricing data
    $use_user_price = isset($_POST['use_user_price']) && $_POST['use_user_price'] == '1' ? 1 : 0;
    $user_price = null; // Default to NULL (use admin_points)
    
    if ($use_user_price) {
        $user_price_input = isset($_POST['user_price']) ? trim($_POST['user_price']) : '';
        if ($user_price_input !== '') {
            $user_price = intval($user_price_input);
            if ($user_price < 0) $user_price = 0; // Ensure non-negative
            
            // Get admin_points for validation
            $admin_points_query = "SELECT dp.admin_points FROM docs_points dp WHERE dp.document_id = $doc_id";
            $admin_points_result = db_query($admin_points_query);
            $admin_points_row = mysqli_fetch_assoc($admin_points_result);
            $admin_points = $admin_points_row ? intval($admin_points_row['admin_points'] ?? 0) : 0;
            
            // Validation: user_price must be <= admin_points (if admin_points > 0)
            if ($admin_points > 0 && $user_price > $admin_points) {
                $error = "Giá bạn đặt (" . number_format($user_price) . " điểm) không được vượt quá giá Admin (" . number_format($admin_points) . " điểm).";
                // Fallback to admin_points if validation fails
                $user_price = $admin_points;
            }
        } else {
            // If toggle is on but no value provided, set to 0 (free)
            $user_price = 0;
        }
    }
    
    // Get category data
    $category_data = null;
    if (!empty($_POST['category_data'])) {
        $category_data = json_decode($_POST['category_data'], true);
    }
    
    // Update document
    $user_price_sql = $user_price === null ? 'NULL' : $user_price;
    $update_query = "UPDATE documents SET 
                     description='$description', 
                     is_public=$is_public,
                     user_price=$user_price_sql
                     WHERE id=$doc_id AND user_id=$user_id";
    
    if(db_query($update_query)) {
        // Save category data if provided
        if ($category_data && !empty($category_data['education_level'])) {
            saveDocumentCategory(
                $doc_id,
                $category_data['education_level'],
                $category_data['grade_id'] ?? null,
                $category_data['subject_code'] ?? null,
                $category_data['major_group_id'] ?? null,
                $category_data['major_code'] ?? null,
                $category_data['doc_type_code'] ?? ''
            );
        }
        
        header("Location: dashboard.php?msg=updated");
        exit;
    } else {
        $error = "Có lỗi xảy ra khi cập nhật: " . db_error();
    }
}

$page_title = "Edit Document - DocShare";
$current_page = 'dashboard';
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8 bg-base-200/50">
        <!-- Header Section -->
        <div class="mb-10 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold flex items-center gap-4 text-base-content">
                    <div class="p-3.5 rounded-[1.5rem] bg-primary/10 text-primary shadow-inner border border-primary/10">
                        <i class="fa-solid fa-file-pen"></i>
                    </div>
                    Chỉnh Sửa Tài Liệu
                </h1>
                <p class="text-base-content/60 mt-2 font-medium">Cập nhật thông tin chi tiết cho tài liệu của bạn</p>
            </div>
            
            <div class="flex items-center gap-3">
                <?php 
                    $status_configs = [
                        'pending' => ['bg' => 'bg-warning/10', 'text' => 'text-warning', 'label' => 'ĐANG DUYỆT', 'icon' => 'fa-hourglass-half'],
                        'approved' => ['bg' => 'bg-success/10', 'text' => 'text-success', 'label' => 'ĐÃ DUYỆT', 'icon' => 'fa-circle-check'],
                        'rejected' => ['bg' => 'bg-error/10', 'text' => 'text-error', 'label' => 'BỊ TỪ CHỐI', 'icon' => 'fa-circle-xmark']
                    ];
                    $config = $status_configs[$doc['status']] ?? $status_configs['pending'];
                ?>
                <div class="badge badge-lg h-14 px-6 bg-base-100 border-base-200 shadow-sm font-black gap-3 rounded-2xl <?= $config['text'] ?>">
                    <i class="fa-solid <?= $config['icon'] ?> opacity-70"></i>
                    <?= $config['label'] ?>
                </div>
                <div class="badge badge-lg h-14 px-6 bg-base-100 border-base-200 shadow-sm font-black gap-3 rounded-2xl text-base-content/40">
                    <i class="fa-solid fa-calendar opacity-50"></i>
                    <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                </div>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert bg-error/10 border-error/20 text-error mb-8 rounded-3xl animate-shake">
                <i class="fa-solid fa-circle-xmark"></i>
                <span class="font-bold"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="editForm" class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-stretch pb-20">
            <input type="hidden" name="category_data" id="category_data_input" value="">
            
            <!-- Left Side: Main Info -->
            <div class="xl:col-span-8 flex flex-col h-full">
                <div class="bg-base-100 rounded-[3rem] p-8 md:p-12 border border-base-200 shadow-xl shadow-base-200/50 relative overflow-hidden flex-1">
                    <div class="absolute -right-20 -top-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl"></div>
                    
                    <div class="relative z-10 space-y-10">
                        <!-- Name Field (Read Only) -->
                        <div class="form-control">
                            <label class="label mb-2">
                                <span class="text-xs font-black uppercase tracking-widest text-base-content/40">Tên Tài Liệu</span>
                            </label>
                            <div class="relative group">
                                <div class="absolute left-6 top-1/2 -translate-y-1/2 text-primary/40 group-focus-within:text-primary transition-colors">
                                    <i class="fa-solid fa-file-lines text-xl"></i>
                                </div>
                                <input type="text" value="<?= htmlspecialchars($doc['original_name']) ?>" 
                                       class="input input-lg w-full bg-base-200/50 border-transparent font-bold pl-16 rounded-2xl cursor-not-allowed text-base-content/60" readonly disabled>
                            </div>
                        </div>

                        <!-- Description Field -->
                        <div class="form-control">
                            <label class="label mb-2">
                                <span class="text-xs font-black uppercase tracking-widest text-base-content/40">Mô Tả Tóm Tắt</span>
                            </label>
                            <textarea name="description" rows="6" 
                                      class="textarea textarea-lg w-full bg-base-200/30 border-2 border-transparent focus:border-primary/20 focus:bg-base-100 transition-all rounded-3xl p-6 font-medium leading-relaxed"
                                      placeholder="Hãy mô tả một chút về nội dung tài liệu này..."><?= htmlspecialchars($doc['description'] ?? '') ?></textarea>
                            <label class="label mt-2">
                                <span class="text-[10px] font-bold text-base-content/30 italic uppercase tracking-wider">Gợi ý: Mô tả hay giúp tài liệu dễ được tìm thấy hơn</span>
                            </label>
                        </div>

                        <!-- Category Cascade -->
                        <div class="form-control">
                            <label class="label mb-4">
                                <span class="text-xs font-black uppercase tracking-widest text-base-content/40">Phân Loại Tài Liệu</span>
                            </label>
                            
                            <div class="bg-base-200/50 p-6 md:p-8 rounded-[2.5rem] border border-base-200/50 space-y-6" id="category-cascade">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Cấp học -->
                                    <div class="form-control">
                                        <label class="label py-1">
                                            <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">1. Cấp học</span>
                                        </label>
                                        <select class="select select-bordered w-full rounded-2xl font-bold h-14" id="education_level">
                                            <option value="">-- Chọn trình độ --</option>
                                            <?php foreach($education_levels as $level): ?>
                                                <option value="<?= $level['code'] ?>" <?= ($current_category && $current_category['education_level'] == $level['code']) ? 'selected' : '' ?>><?= $level['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Lớp -->
                                    <div class="form-control hidden" id="grade_container">
                                        <label class="label py-1">
                                            <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">2. Lớp</span>
                                        </label>
                                        <select class="select select-bordered w-full rounded-2xl font-bold h-14" id="grade_id">
                                            <option value="">-- Chọn lớp --</option>
                                        </select>
                                    </div>

                                    <!-- Nhóm ngành -->
                                    <div class="form-control hidden" id="major_group_container">
                                        <label class="label py-1">
                                            <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">2. Nhóm ngành</span>
                                        </label>
                                        <select class="select select-bordered w-full rounded-2xl font-bold h-14" id="major_group_id">
                                            <option value="">-- Chọn nhóm ngành --</option>
                                        </select>
                                    </div>

                                    <!-- Môn học -->
                                    <div class="form-control hidden" id="subject_container">
                                        <label class="label py-1">
                                            <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">3. Môn học</span>
                                        </label>
                                        <select class="select select-bordered w-full rounded-2xl font-bold h-14" id="subject_code">
                                            <option value="">-- Chọn môn học --</option>
                                        </select>
                                    </div>

                                    <!-- Ngành học -->
                                    <div class="form-control hidden" id="major_container">
                                        <label class="label py-1">
                                            <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">3. Ngành học</span>
                                        </label>
                                        <select class="select select-bordered w-full rounded-2xl font-bold h-14" id="major_code">
                                            <option value="">-- Chọn ngành học --</option>
                                        </select>
                                    </div>

                                    <!-- Loại tài liệu -->
                                    <div class="form-control hidden" id="doc_type_container">
                                        <label class="label py-1">
                                            <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">4. Loại tài liệu</span>
                                        </label>
                                        <select class="select select-bordered w-full rounded-2xl font-bold h-14" id="doc_type_code">
                                            <option value="">-- Chọn loại tài liệu --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Summary Badge -->
                                <div class="pt-4 border-t border-base-300/30 hidden" id="category-summary">
                                    <div class="badge badge-lg h-auto py-3 px-6 bg-primary/10 text-primary border-none rounded-2xl gap-3">
                                        <i class="fa-solid fa-folder-tree opacity-60"></i>
                                        <span class="font-black text-xs uppercase tracking-wider" id="summary-text"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Action Center (Mirrored Card) -->
            <div class="xl:col-span-4 flex flex-col h-full">
                <div class="bg-base-100 rounded-[3rem] border border-base-200 shadow-xl shadow-base-200/50 overflow-hidden flex flex-col h-full relative">
                    <!-- Top Section: Preview -->
                    <div class="p-8 pb-0">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xs font-black uppercase tracking-widest text-base-content/40">Xem trước & Trạng thái</h3>
                            <div class="badge badge-sm bg-base-200 border-none font-bold text-[10px]"><?= strtoupper($doc['status']) ?></div>
                        </div>
                        
                        <div class="w-full aspect-[4/3] rounded-[2rem] overflow-hidden bg-base-200 shadow-inner group relative mb-8">
                            <?php if($doc['thumbnail']): ?>
                                <img src="uploads/<?= htmlspecialchars($doc['thumbnail']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex flex-col items-center justify-center text-primary/10">
                                    <i class="fa-solid fa-file-pdf text-7xl"></i>
                                    <span class="text-[10px] font-black mt-2">KHÔNG CÓ ẢNH BÌA</span>
                                </div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-primary/20 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-500 flex items-center justify-center">
                                <span class="bg-white text-primary font-black px-5 py-2 rounded-full text-xs shadow-xl scale-90 group-hover:scale-100 transition-transform">XEM LỚN</span>
                            </div>
                        </div>

                        <?php if($doc['status'] === 'rejected' && !empty($doc['rejection_reason'])): ?>
                            <div class="p-4 bg-error/5 rounded-2xl border border-error/10 mb-6">
                                <div class="flex items-center gap-2 text-error font-black text-[9px] uppercase tracking-wider mb-2">
                                    <i class="fa-solid fa-circle-exclamation"></i> Lý do từ chối
                                </div>
                                <p class="text-[11px] font-medium opacity-70 leading-relaxed"><?= htmlspecialchars($doc['rejection_reason']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Middle Section: Privacy Settings & Pricing -->
                    <div class="flex-1 px-8 space-y-6">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-widest text-base-content/40 mb-6">Cài đặt hiển thị</h3>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <label class="group cursor-pointer flex items-center justify-between p-5 rounded-[1.5rem] bg-base-200/30 border-2 border-transparent transition-all hover:bg-base-200/50 <?= $doc['is_public'] == 1 ? 'border-primary/20 bg-primary/5' : '' ?>" id="pubLabel">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-primary shadow-sm border border-base-100">
                                            <i class="fa-solid fa-earth-asia"></i>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-black uppercase tracking-tight">Công khai</div>
                                            <div class="text-[9px] font-bold opacity-30 italic">Ai cũng có thể thấy</div>
                                        </div>
                                    </div>
                                    <input type="radio" name="is_public" value="1" class="radio radio-primary radio-sm" <?= $doc['is_public'] == 1 ? 'checked' : '' ?> onclick="updatePrivacyUI(true)">
                                </label>

                                <label class="group cursor-pointer flex items-center justify-between p-5 rounded-[1.5rem] bg-base-200/30 border-2 border-transparent transition-all hover:bg-base-200/50 <?= $doc['is_public'] == 0 ? 'border-primary/20 bg-primary/5' : '' ?>" id="privLabel">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-primary shadow-sm border border-base-100">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-black uppercase tracking-tight">Riêng tư</div>
                                            <div class="text-[9px] font-bold opacity-30 italic">Chỉ mình bạn xem</div>
                                        </div>
                                    </div>
                                    <input type="radio" name="is_public" value="0" class="radio radio-primary radio-sm" <?= $doc['is_public'] == 0 ? 'checked' : '' ?> onclick="updatePrivacyUI(false)">
                                </label>
                            </div>
                        </div>

                        <!-- Pricing Settings -->
                        <div class="pt-6 border-t border-base-300/30">
                            <h3 class="text-xs font-black uppercase tracking-widest text-base-content/40 mb-6">Cài đặt điểm</h3>
                            
                            <?php 
                            // Check if user_price is NULL, 0, or > 0
                            $current_user_price = isset($doc['user_price']) && $doc['user_price'] !== null ? intval($doc['user_price']) : null;
                            $current_admin_points = intval($doc['admin_points'] ?? 0);
                            
                            // Toggle is ON if user_price is not NULL (can be 0 or > 0)
                            $use_user_price = $current_user_price !== null ? 1 : 0;
                            
                            // Display price: NULL -> admin_points, 0 -> 0 (free), > 0 -> user_price
                            if ($current_user_price === null) {
                                $display_price = $current_admin_points;
                            } else {
                                $display_price = $current_user_price;
                            }
                            ?>
                            
                            <div class="space-y-4">
                                <!-- Toggle: Use Custom Price -->
                                <div class="form-control">
                                    <label class="label cursor-pointer justify-between p-4 rounded-[1.5rem] bg-base-200/30 border-2 border-transparent transition-all hover:bg-base-200/50 <?= $use_user_price ? 'border-primary/20 bg-primary/5' : '' ?>" id="priceToggleLabel">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-warning shadow-sm border border-base-100">
                                                <i class="fa-solid fa-coins"></i>
                                            </div>
                                            <div>
                                                <div class="text-[11px] font-black uppercase tracking-tight">Đặt giá riêng</div>
                                                <div class="text-[9px] font-bold opacity-30 italic">
                                                    <?php if ($use_user_price && $current_user_price !== null): ?>
                                                        <?php if ($current_user_price == 0): ?>
                                                            Đang dùng: Miễn phí
                                                        <?php else: ?>
                                                            Đang dùng: <?= number_format($current_user_price) ?> điểm
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        Đang dùng: <?= number_format($current_admin_points) ?> điểm (từ Admin)
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="checkbox" name="use_user_price" value="1" class="toggle toggle-primary toggle-sm" id="use_user_price_toggle" <?= $use_user_price ? 'checked' : '' ?> onchange="toggleUserPrice(this.checked)">
                                    </label>
                                </div>

                                <!-- User Price Input (Hidden by default) -->
                                <div class="form-control <?= $use_user_price ? '' : 'hidden' ?>" id="user_price_container">
                                    <label class="label py-1">
                                        <span class="text-[10px] font-black text-primary uppercase tracking-widest opacity-60">Số điểm tùy chỉnh</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute left-6 top-1/2 -translate-y-1/2 text-warning/60">
                                            <i class="fa-solid fa-coins text-lg"></i>
                                        </div>
                                        <input type="number" 
                                               name="user_price" 
                                               id="user_price_input"
                                               min="0" 
                                               max="<?= $current_admin_points > 0 ? $current_admin_points : '' ?>"
                                               step="1"
                                               value="<?= $current_user_price !== null ? $current_user_price : '' ?>"
                                               placeholder="<?= $current_admin_points > 0 ? 'Nhập số điểm (0 = miễn phí, tối đa ' . number_format($current_admin_points) . ')' : 'Nhập số điểm (0 = miễn phí)' ?>"
                                               <?= !$use_user_price ? 'readonly' : '' ?>
                                               class="input input-lg w-full bg-base-200/50 border-2 border-transparent focus:border-primary/20 focus:bg-base-100 transition-all font-bold pl-16 rounded-2xl <?= !$use_user_price ? 'cursor-not-allowed opacity-50' : '' ?>">
                                    </div>
                                    <label class="label mt-2">
                                        <span class="text-[9px] font-bold text-base-content/30 italic uppercase tracking-wider">
                                            <?php if ($current_admin_points > 0): ?>
                                                Giá Admin: <?= number_format($current_admin_points) ?> điểm. Tắt toggle để dùng giá Admin. Đặt 0 = miễn phí. Tối đa: <?= number_format($current_admin_points) ?> điểm.
                                            <?php else: ?>
                                                Đặt 0 để tài liệu miễn phí
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </div>

                                <!-- Display Current Price Info -->
                                <div class="p-4 rounded-2xl bg-base-200/30 border border-base-200/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-warning/10 flex items-center justify-center text-warning">
                                                <i class="fa-solid fa-info-circle text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-tight text-base-content/60">Giá hiện tại</div>
                                                <div class="text-sm font-bold text-warning">
                                                    <?php if ($display_price > 0): ?>
                                                        <?= number_format($display_price) ?> điểm
                                                    <?php else: ?>
                                                        Miễn phí
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($current_user_price === null): ?>
                                            <div class="badge badge-sm bg-primary/10 text-primary border-none font-bold">
                                                Từ Admin
                                            </div>
                                        <?php elseif ($current_user_price == 0): ?>
                                            <div class="badge badge-sm bg-success/10 text-success border-none font-bold">
                                                Miễn phí
                                            </div>
                                        <?php else: ?>
                                            <div class="badge badge-sm bg-warning/10 text-warning border-none font-bold">
                                                Tùy chỉnh
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Section: Actions -->
                    <div class="p-8 space-y-4">
                        <button type="submit" class="btn btn-primary w-full h-16 rounded-[1.5rem] font-black text-base gap-3 shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all">
                            <i class="fa-solid fa-check-double text-xl"></i>
                            LƯU THAY ĐỔI
                        </button>
                        
                        <div class="flex items-center gap-2">
                            <a href="dashboard.php" class="btn btn-ghost flex-1 h-12 rounded-xl font-black text-[10px] text-base-content/40 uppercase tracking-widest">HỦY BỎ</a>
                            <div class="w-px h-6 bg-base-200"></div>
                            <button type="button" onclick="confirmDelete()" class="btn btn-ghost text-error/40 hover:text-error hover:bg-error/5 flex-1 h-12 rounded-xl font-black text-[10px] uppercase tracking-widest">XÓA TÀI LIỆU</button>
                        </div>

                        <!-- Mini Danger Note -->
                        <div class="pt-4 border-t border-base-200 mt-4 text-center">
                            <p class="text-[9px] font-bold text-base-content/20 uppercase tracking-[0.2em]">Cập nhật lần cuối: <?= date('d/m/Y') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Delete Confirmation Form -->
        <form id="deleteForm" method="POST" class="hidden">
            <input type="hidden" name="action" value="delete">
        </form>
    </main>
</div>

<script src="js/categories.js"></script>
<script>
// Current category data from PHP
const currentCategory = <?= json_encode($current_category) ?>;

// State
let categoryData = {
    education_level: currentCategory?.education_level || '',
    grade_id: currentCategory?.grade_id || null,
    subject_code: currentCategory?.subject_code || null,
    major_group_id: currentCategory?.major_group_id || null,
    major_code: currentCategory?.major_code || null,
    doc_type_code: currentCategory?.doc_type_code || ''
};

// Elements
const educationLevelSelect = document.getElementById('education_level');
const gradeContainer = document.getElementById('grade_container');
const gradeSelect = document.getElementById('grade_id');
const subjectContainer = document.getElementById('subject_container');
const subjectSelect = document.getElementById('subject_code');
const majorGroupContainer = document.getElementById('major_group_container');
const majorGroupSelect = document.getElementById('major_group_id');
const majorContainer = document.getElementById('major_container');
const majorSelect = document.getElementById('major_code');
const docTypeContainer = document.getElementById('doc_type_container');
const docTypeSelect = document.getElementById('doc_type_code');
const categorySummary = document.getElementById('category-summary');
const summaryText = document.getElementById('summary-text');
const categoryDataInput = document.getElementById('category_data_input');
const editForm = document.getElementById('editForm');

function updatePrivacyUI(isPublic) {
    const pub = document.getElementById('pubLabel');
    const priv = document.getElementById('privLabel');
    if (isPublic) {
        pub.classList.add('border-primary/20', 'bg-primary/5');
        priv.classList.remove('border-primary/20', 'bg-primary/5');
    } else {
        priv.classList.add('border-primary/20', 'bg-primary/5');
        pub.classList.remove('border-primary/20', 'bg-primary/5');
    }
}

function toggleUserPrice(enabled) {
    const container = document.getElementById('user_price_container');
    const input = document.getElementById('user_price_input');
    const label = document.getElementById('priceToggleLabel');
    
    if (enabled) {
        container.classList.remove('hidden');
        input.removeAttribute('disabled');
        input.removeAttribute('readonly');
        // If input is empty, set to 0 as default (free)
        if (input.value === '') {
            input.value = '0';
        }
        // Focus after a small delay to ensure container is visible
        setTimeout(() => input.focus(), 100);
        label.classList.add('border-primary/20', 'bg-primary/5');
    } else {
        container.classList.add('hidden');
        // Clear value and set to readonly so it submits as empty (NULL)
        input.setAttribute('readonly', 'readonly');
        input.value = '';
        label.classList.remove('border-primary/20', 'bg-primary/5');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', async function() {
    // Bind events
    educationLevelSelect.addEventListener('change', onEducationLevelChange);
    gradeSelect.addEventListener('change', onGradeChange);
    subjectSelect.addEventListener('change', onSubjectChange);
    majorGroupSelect.addEventListener('change', onMajorGroupChange);
    majorSelect.addEventListener('change', onMajorChange);
    docTypeSelect.addEventListener('change', onDocTypeChange);
    
    // If we have existing category, load the cascade
    if (categoryData.education_level) {
        await loadCascadeFromState();
    }
    
    // Update hidden input before form submit
    editForm.addEventListener('submit', function(e) {
        categoryDataInput.value = JSON.stringify(categoryData);
        
        // Validate user_price if toggle is enabled
        const useUserPriceToggle = document.getElementById('use_user_price_toggle');
        const userPriceInput = document.getElementById('user_price_input');
        
        if (useUserPriceToggle && useUserPriceToggle.checked) {
            const userPrice = parseInt(userPriceInput.value) || 0;
            const maxPrice = parseInt(userPriceInput.getAttribute('max')) || 0;
            
            // If admin_points > 0, validate user_price <= admin_points
            if (maxPrice > 0 && userPrice > maxPrice) {
                e.preventDefault();
                alert('Giá bạn đặt (' + userPrice.toLocaleString() + ' điểm) không được vượt quá giá Admin (' + maxPrice.toLocaleString() + ' điểm).');
                userPriceInput.focus();
                return false;
            }
            
            // If toggle is on but input is empty, set to 0 (free)
            if (userPriceInput.value === '') {
                userPriceInput.value = '0';
            }
        } else {
            // If toggle is off, clear the input value so it submits as empty (NULL)
            userPriceInput.value = '';
        }
    });
});

async function loadCascadeFromState() {
    const level = categoryData.education_level;
    const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
    
    if (isPhoThong) {
        showElement('grade_container');
        await loadGrades(level);
        if (categoryData.grade_id) {
            gradeSelect.value = categoryData.grade_id;
            showElement('subject_container');
            await loadSubjects(level, categoryData.grade_id);
            if (categoryData.subject_code) subjectSelect.value = categoryData.subject_code;
        }
    } else if (level === 'dai_hoc') {
        showElement('major_group_container');
        await loadMajorGroups();
        if (categoryData.major_group_id) {
            majorGroupSelect.value = categoryData.major_group_id;
            showElement('major_container');
            await loadMajors(categoryData.major_group_id);
            if (categoryData.major_code) majorSelect.value = categoryData.major_code;
        }
    }
    
    if (level) {
        showElement('doc_type_container');
        await loadDocTypes(level);
        if (categoryData.doc_type_code) docTypeSelect.value = categoryData.doc_type_code;
    }
    updateSummary();
}

async function onEducationLevelChange(e) {
    const level = e.target.value;
    categoryData = { education_level: level, grade_id: null, subject_code: null, major_group_id: null, major_code: null, doc_type_code: '' };
    hideElement('grade_container');
    hideElement('subject_container');
    hideElement('major_group_container');
    hideElement('major_container');
    hideElement('doc_type_container');
    hideElement('category-summary');
    if (!level) return;
    const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
    if (isPhoThong) { showElement('grade_container'); await loadGrades(level); }
    else { showElement('major_group_container'); await loadMajorGroups(); }
    showElement('doc_type_container');
    await loadDocTypes(level);
}

async function onGradeChange(e) {
    const gradeId = e.target.value;
    categoryData.grade_id = gradeId ? parseInt(gradeId) : null;
    categoryData.subject_code = null;
    hideElement('subject_container');
    if (!gradeId) { updateSummary(); return; }
    showElement('subject_container');
    await loadSubjects(categoryData.education_level, gradeId);
    updateSummary();
}

function onSubjectChange(e) { categoryData.subject_code = e.target.value || null; updateSummary(); }

async function onMajorGroupChange(e) {
    const groupId = e.target.value;
    categoryData.major_group_id = groupId ? parseInt(groupId) : null;
    categoryData.major_code = null;
    hideElement('major_container');
    if (!groupId) { updateSummary(); return; }
    showElement('major_container');
    await loadMajors(groupId);
    updateSummary();
}

function onMajorChange(e) { categoryData.major_code = e.target.value || null; updateSummary(); }
function onDocTypeChange(e) { categoryData.doc_type_code = e.target.value || ''; updateSummary(); }

async function loadGrades(level) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=grades&level=${level}`);
        const data = await response.json();
        if (data.success) gradeSelect.innerHTML = '<option value="">-- Chọn lớp --</option>' + data.data.map(grade => `<option value="${grade.id}">${grade.name}</option>`).join('');
    } catch (error) { console.error('Error loading grades:', error); }
}

async function loadSubjects(level, gradeId) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=subjects&level=${level}&grade_id=${gradeId}`);
        const data = await response.json();
        if (data.success) subjectSelect.innerHTML = '<option value="">-- Chọn môn học --</option>' + data.data.map(subject => `<option value="${subject.code}">${subject.name}</option>`).join('');
    } catch (error) { console.error('Error loading subjects:', error); }
}

async function loadMajorGroups() {
    try {
        const response = await fetch(`/handler/categories_api.php?action=major_groups`);
        const data = await response.json();
        if (data.success) majorGroupSelect.innerHTML = '<option value="">-- Chọn nhóm ngành --</option>' + data.data.map(group => `<option value="${group.id}">${group.name}</option>`).join('');
    } catch (error) { console.error('Error loading major groups:', error); }
}

async function loadMajors(groupId) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=majors&group_id=${groupId}`);
        const data = await response.json();
        if (data.success) majorSelect.innerHTML = '<option value="">-- Chọn ngành học --</option>' + data.data.map(major => `<option value="${major.code}">${major.name}</option>`).join('');
    } catch (error) { console.error('Error loading majors:', error); }
}

async function loadDocTypes(level) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=doc_types&level=${level}`);
        const data = await response.json();
        if (data.success) docTypeSelect.innerHTML = '<option value="">-- Chọn loại tài liệu --</option>' + data.data.map(docType => `<option value="${docType.code}">${docType.name}</option>`).join('');
    } catch (error) { console.error('Error loading doc types:', error); }
}

function showElement(id) { document.getElementById(id)?.classList.remove('hidden'); }
function hideElement(id) { document.getElementById(id)?.classList.add('hidden'); }

function updateSummary() {
    const parts = [];
    if (categoryData.education_level && educationLevelSelect.selectedOptions[0]?.value) parts.push(educationLevelSelect.selectedOptions[0].text);
    if (categoryData.grade_id && gradeSelect.selectedOptions[0]?.value) parts.push(gradeSelect.selectedOptions[0].text);
    if (categoryData.subject_code && subjectSelect.selectedOptions[0]?.value) parts.push(subjectSelect.selectedOptions[0].text);
    if (categoryData.major_group_id && majorGroupSelect.selectedOptions[0]?.value) parts.push(majorGroupSelect.selectedOptions[0].text);
    if (categoryData.major_code && majorSelect.selectedOptions[0]?.value) parts.push(majorSelect.selectedOptions[0].text);
    if (categoryData.doc_type_code && docTypeSelect.selectedOptions[0]?.value) parts.push(`[${docTypeSelect.selectedOptions[0].text}]`);
    if (parts.length > 0) { summaryText.innerText = parts.join(' → '); categorySummary.classList.remove('hidden'); }
    else { categorySummary.classList.add('hidden'); }
}

function confirmDelete() {
    vsdConfirm({
        title: 'Xóa tài liệu VĨNH VIỄN?',
        message: 'Tài liệu này sẽ biến mất khỏi hệ thống mãi mãi. Bạn có chắc chắn không?',
        confirmText: 'XÓA LUÔN!',
        type: 'error',
        onConfirm: () => document.getElementById('deleteForm').submit()
    });
}
</script>

?>
