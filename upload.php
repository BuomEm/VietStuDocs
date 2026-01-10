<?php
session_start();
require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/points.php';
require_once 'config/file.php';
require_once 'config/categories.php';
require_once 'config/premium.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$user_points = getUserPoints($user_id);

// Get education levels for the cascade selection
$education_levels = getEducationLevels();

$page_title = "Upload Document - DocShare";
$current_page = 'upload';

// Count pending and approved documents
$pending_count = (int)$VSD->num_rows("SELECT id FROM documents WHERE user_id=$user_id AND status='pending'");
$approved_count = (int)$VSD->num_rows("SELECT id FROM documents WHERE user_id=$user_id AND status='approved'");

?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-extrabold flex items-center gap-3 text-base-content">
                    <div class="p-3 rounded-2xl bg-primary/10 text-primary shadow-inner">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    Tải Lên Tài Liệu
                </h1>
                <p class="text-base-content/60 mt-2 font-medium">Chia sẻ kiến thức, nhận lại giá trị xứng đáng</p>
            </div>
            
            <div class="flex gap-2">
                <div class="badge badge-lg py-4 px-6 bg-base-100 border-base-300 shadow-sm font-bold gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary/60"></i>
                    Chờ duyệt: <span class="text-warning ml-1"><?= $pending_count ?></span>
                </div>
                <div class="badge badge-lg py-4 px-6 bg-base-100 border-base-300 shadow-sm font-bold gap-2">
                    <i class="fa-solid fa-circle-check text-primary/60"></i>
                    Đã duyệt: <span class="text-success ml-1"><?= $approved_count ?></span>
                </div>
            </div>
        </div>

        <div id="alertMessage" class="mb-6"></div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
            <!-- Left Side: Upload Zone -->
            <div class="xl:col-span-8 flex flex-col gap-6">
                <form id="uploadForm" class="upload-form">
                    <!-- Premium Drag Drop Area -->
                    <div id="dragDropArea" class="group relative overflow-hidden rounded-[2rem] bg-base-100 border-2 border-dashed border-base-300 hover:border-primary transition-all duration-500 cursor-pointer min-h-[450px] flex flex-col items-center justify-center p-12 shadow-sm hover:shadow-2xl hover:shadow-primary/5">
                        <!-- Decorative Background -->
                        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                        <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors"></div>
                        
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="w-24 h-24 rounded-3xl bg-primary/5 flex items-center justify-center mb-8 group-hover:scale-110 group-hover:bg-primary transition-all duration-500 shadow-sm">
                                <i class="fa-solid fa-folder-open text-4xl group-hover:text-white transition-colors duration-500"></i>
                            </div>
                            <h2 class="text-3xl font-black mb-3 text-base-content group-hover:text-primary transition-colors">Kéo thả file vào đây</h2>
                            <p class="text-base-content/50 max-w-sm mb-8 text-lg">Hoặc nhấp để chọn tệp từ thiết bị của bạn. Hỗ trợ PDF, Word, Excel, PowerPoint và nhiều định dạng khác.</p>
                            
                            <div class="flex flex-wrap justify-center gap-2 max-w-md opacity-40 group-hover:opacity-100 transition-opacity">
                                <span class="badge badge-outline gap-1 p-3"><i class="fa-solid fa-file-pdf"></i> PDF</span>
                                <span class="badge badge-outline gap-1 p-3"><i class="fa-solid fa-file-word"></i> Word</span>
                                <span class="badge badge-outline gap-1 p-3"><i class="fa-solid fa-file-excel"></i> Excel</span>
                                <span class="badge badge-outline gap-1 p-3"><i class="fa-solid fa-file-powerpoint"></i> PPT</span>
                            </div>
                        </div>

                        <!-- Pulse Ring for Attraction -->
                        <div class="absolute w-full h-full border-2 border-primary/20 rounded-[2rem] scale-95 opacity-0 group-hover:scale-100 group-hover:opacity-100 transition-all duration-700 animate-pulse"></div>
                    </div>

                    <input type="file" id="fileInput" name="documents" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip" class="file-input-hidden">

                    <!-- File List Container -->
                    <div id="fileList" class="mt-8 hidden">
                        <div class="flex items-center justify-between mb-4 px-2">
                             <h3 class="text-xl font-bold flex items-center gap-2">
                                <i class="fa-solid fa-layer-group text-primary"></i>
                                Danh sách chờ tải lên (<span id="fileCount" class="text-primary">0</span>)
                            </h3>
                        </div>
                        <div id="fileListContent" class="grid grid-cols-1 gap-6"></div>
                    </div>

                    <!-- Upload Progress -->
                    <div id="uploadProgress" class="mt-8 hidden">
                        <div class="card bg-base-100 shadow-xl border border-base-200 overflow-hidden">
                            <div class="card-body p-6">
                                <h3 class="card-title text-xl mb-6 flex items-center gap-2">
                                    <span class="loading loading-spinner loading-md text-primary"></span>
                                    Đang xử lý dữ liệu...
                                </h3>
                                <div id="progressContainer" class="space-y-6"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Final Action Button -->
                    <div id="submitContainer" class="mt-10 hidden">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-full h-16 rounded-2xl text-xl font-black shadow-lg shadow-primary/20 hover:shadow-primary/40 group transition-all duration-300">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl group-hover:-translate-y-1 transition-transform"></i>
                            BẮT ĐẦU TẢI LÊN NGAY
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side: Guidelines & Points Info -->
            <div class="xl:col-span-4 flex flex-col gap-6">
                <!-- User Balance Card -->
                 <div class="group relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-primary to-primary-focus p-8 text-primary-content shadow-xl shadow-primary/30">
                    <div class="absolute -right-8 -bottom-8 w-48 h-48 bg-white/10 rounded-full blur-2xl group-hover:scale-110 transition-transform duration-700"></div>
                    <div class="absolute right-6 top-6 text-white/20">
                        <i class="fa-solid fa-wallet text-6xl"></i>
                    </div>
                    
                    <div class="relative z-10">
                        <span class="text-xs font-bold uppercase tracking-[0.2em] opacity-80">Số dư hiện tại</span>
                        <div class="text-5xl font-black my-3 flex items-baseline gap-2">
                            <?= number_format($user_points['current_points']) ?>
                             <span class="text-sm font-bold opacity-70">VSD</span>
                        </div>
                        <div class="divider divider-horizontal bg-white/10 h-px my-4"></div>
                        <div class="flex justify-between items-center text-sm font-medium">
                            <div class="flex flex-col">
                                <span class="opacity-70">Đã nhận</span>
                                <span class="text-lg font-bold"><?= number_format($user_points['total_earned']) ?></span>
                            </div>
                            <div class="w-px h-8 bg-white/20"></div>
                            <div class="flex flex-col text-right">
                                <span class="opacity-70">Đã dùng</span>
                                <span class="text-lg font-bold"><?= number_format($user_points['total_spent']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guidelines Card -->
                <div class="card bg-base-100 shadow-xl border border-base-200">
                    <div class="card-body p-6">
                        <h3 class="card-title text-xl mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-shield-heart text-primary"></i>
                            Quy trình Tải lên
                        </h3>
                        <div class="space-y-6">
                            <div class="flex gap-4">
                                <div class="flex-none w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">1</div>
                                <div>
                                    <h4 class="font-bold text-sm">Chọn Tệp</h4>
                                    <p class="text-xs text-base-content/60 mt-1">Chọn một hoặc nhiều tệp cùng lúc.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="flex-none w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">2</div>
                                <div>
                                    <h4 class="font-bold text-sm">Thông tin Tài liệu</h4>
                                    <p class="text-xs text-base-content/60 mt-1">Nhập tiêu đề (tối thiểu 40 ký tự) và mô tả chi tiết.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="flex-none w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">3</div>
                                <div>
                                    <h4 class="font-bold text-sm">Phân loại & Kiểm duyệt</h4>
                                    <p class="text-xs text-base-content/60 mt-1">Chọn đúng chuyên mục để được duyệt nhanh nhất.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-8 p-4 bg-warning/5 rounded-2xl border border-warning/10 border-dashed">
                             <div class="flex gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-warning mt-1"></i>
                                <div class="text-[11px] leading-relaxed">
                                    <strong class="text-warning">Lưu ý:</strong> Chúng tôi nghiêm cấm đăng tải tài liệu vi phạm bản quyền hoặc nội dung trái pháp luật. Tài liệu sẽ được kiểm duyệt trong vòng 24h.
                                </div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
<?php 
    include 'includes/footer.php'; 
?>
</div>
</div>

<style>
    /* File input hidden */
    .file-input-hidden {
        display: none;
    }
    
    /* Category cascade styling */
    .category-cascade {
        background: hsl(var(--b1));
        border-radius: 1.5rem;
        padding: 1.5rem;
        border: 1px solid hsl(var(--b3));
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .category-select-group {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .category-cascade .select-sm {
        border-radius: 0.75rem;
        height: 2.5rem;
    }
    
    .category-cascade .label-text {
        font-weight: 800;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: hsl(var(--p));
        opacity: 0.7;
    }
</style>

<!-- PDF.js for thumbnail generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="js/pdf-functions.js"></script>
<script src="js/categories.js"></script>
<script>
    // Education levels from PHP
    const educationLevels = <?= json_encode($education_levels) ?>;
    
    const dragDropArea = document.getElementById('dragDropArea');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const fileListContent = document.getElementById('fileListContent');
    const uploadForm = document.getElementById('uploadForm');
    const submitBtn = document.getElementById('submitBtn');
    const alertMessage = document.getElementById('alertMessage');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressContainer = document.getElementById('progressContainer');
    const fileCount = document.getElementById('fileCount');

    let selectedFiles = [];
    let fileCategories = {}; // Store category data for each file
    
    // Helper function to get filename without extension
    function getFileNameWithoutExtension(filename) {
        const lastDot = filename.lastIndexOf('.');
        return lastDot > 0 ? filename.substring(0, lastDot) : filename;
    }

    // Click to select
    dragDropArea.addEventListener('click', () => fileInput.click());

    // Drag over
    dragDropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dragDropArea.classList.add('border-primary-focus', 'bg-primary/20');
    });

    dragDropArea.addEventListener('dragleave', () => {
        dragDropArea.classList.remove('border-primary-focus', 'bg-primary/20');
    });

    // Drop
    dragDropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dragDropArea.classList.remove('border-primary-focus', 'bg-primary/20');
        fileInput.files = e.dataTransfer.files;
        updateFileList();
    });

    // File input change
    fileInput.addEventListener('change', updateFileList);

    function updateFileList() {
        selectedFiles = Array.from(fileInput.files);
        const submitContainer = document.getElementById('submitContainer');
        
        if(selectedFiles.length > 0) {
            fileList.classList.remove('hidden');
            submitContainer.classList.remove('hidden');
            fileCount.textContent = selectedFiles.length;
            fileListContent.innerHTML = selectedFiles.map((file, index) => {
                const extension = file.name.split('.').pop().toUpperCase();
                let iconClass = 'fa-file-lines';
                let iconColor = 'text-primary';
                
                if (['JPG', 'JPEG', 'PNG'].includes(extension)) {
                    iconClass = 'fa-file-image';
                    iconColor = 'text-info';
                } else if (['PDF'].includes(extension)) {
                    iconClass = 'fa-file-pdf';
                    iconColor = 'text-error';
                } else if (['XLS', 'XLSX'].includes(extension)) {
                    iconClass = 'fa-file-excel';
                    iconColor = 'text-success';
                } else if (['PPT', 'PPTX'].includes(extension)) {
                    iconClass = 'fa-file-powerpoint';
                    iconColor = 'text-warning';
                }

                return `
                <div class="group relative card bg-base-100 border border-base-200 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 rounded-3xl overflow-hidden">
                    <div class="card-body p-0">
                        <div class="flex flex-col lg:flex-row divide-y lg:divide-y-0 lg:divide-x divide-base-200">
                             <!-- File Info Column -->
                             <div class="p-6 lg:w-1/3 bg-base-200/30 flex flex-col justify-between">
                                <div class="flex items-start gap-4">
                                     <div class="w-14 h-14 rounded-2xl bg-base-100 flex items-center justify-center text-2xl shadow-sm ${iconColor}">
                                        <i class="fa-solid ${iconClass}"></i>
                                     </div>
                                     <div class="flex-1 min-w-0">
                                        <div class="font-bold text-base truncate" title="${file.name}">${file.name}</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="badge badge-sm font-bold uppercase">${extension}</span>
                                            <span class="text-xs opacity-50 font-medium">${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                                        </div>
                                     </div>
                                </div>
                                <button type="button" onclick="removeFile(${index})" class="mt-6 btn btn-ghost btn-sm text-error hover:bg-error/10 rounded-xl gap-2 w-fit">
                                    <i class="fa-solid fa-trash-can"></i>
                                    Loại bỏ
                                </button>
                             </div>

                             <!-- Input Fields Column -->
                             <div class="p-6 lg:w-2/3 flex flex-col gap-6">
                                <div class="space-y-6">
                                     <div class="form-control w-full">
                                        <label class="label pt-0">
                                            <span class="label-text-alt uppercase font-black text-primary/80 tracking-wider">Tên Tài Liệu <span class="text-error font-extrabold">*</span></span>
                                        </label>
                                        <input type="text" class="input input-bordered input-md bg-base-200/50 focus:bg-base-100 rounded-xl fileName w-full" data-index="${index}" placeholder="Tên tệp sẽ hiển thị trên web..." value="${getFileNameWithoutExtension(file.name)}" required>
                                        <label class="label pb-0">
                                            <span class="label-text-alt opacity-50 font-bold" id="name-length-${index}">0 ký tự</span>
                                            <span class="label-text-alt text-[10px] uppercase font-bold text-base-content/40 italic">Tối thiểu 40 ký tự</span>
                                        </label>
                                     </div>
                                     
                                     <div class="form-control w-full">
                                        <label class="label pt-0">
                                            <span class="label-text-alt uppercase font-black text-primary/80 tracking-wider">Mô tả tóm tắt <span class="text-error font-extrabold">*</span></span>
                                        </label>
                                        <textarea class="textarea textarea-bordered bg-base-200/50 focus:bg-base-100 rounded-xl fileDescription w-full h-24" data-index="${index}" placeholder="Vài lời giới thiệu về tài liệu..." required></textarea>
                                     </div>
                                </div>

                                <div class="grid grid-cols-1 gap-8">
                                    <div class="form-control">
                                        <label class="label pt-0">
                                            <span class="label-text-alt uppercase font-black text-primary/80 tracking-wider">📂 Phân loại chuyên sâu <span class="text-error font-extrabold">*</span></span>
                                        </label>
                                        ${generateCategoryCascade(index)}
                                    </div>

                                    <div class="space-y-4">
                                        <label class="label pt-0">
                                            <span class="label-text-alt uppercase font-black text-primary/80 tracking-wider">🔧 Cấu hình nâng cao</span>
                                        </label>
                                        <div class="p-4 bg-base-200/50 rounded-2xl border border-dashed border-base-300">
                                            <label class="label cursor-pointer justify-start gap-3 p-0">
                                                <input type="checkbox" class="checkbox checkbox-primary checkbox-sm rounded-lg filePrivacy" data-index="${index}" checked>
                                                <div>
                                                    <span class="label-text font-bold block">Chế độ Công khai</span>
                                                    <span class="label-text-alt opacity-50">Tất cả người dùng trên hệ thống có thể thấy.</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>
            `;}).join('');
            
            // Initialize cascade handlers for each file
            initializeCascadeHandlers();
        } else {
            fileList.classList.add('hidden');
            submitContainer.classList.add('hidden');
        }
        
        // Add event listeners to remove error highlighting and update character count
        setTimeout(() => {
            document.querySelectorAll('.fileDescription').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('input-error');
                });
            });
            
            // Add character count and validation for file names
            document.querySelectorAll('.fileName').forEach(input => {
                const index = input.getAttribute('data-index');
                const lengthLabel = document.getElementById(`name-length-${index}`);
                
                input.addEventListener('input', function() {
                    const length = this.value.length;
                    if(lengthLabel) {
                        lengthLabel.textContent = `${length} ký tự`;
                        if(length < 40) {
                            lengthLabel.classList.add('text-error');
                            lengthLabel.classList.remove('text-success');
                        } else {
                            lengthLabel.classList.remove('text-error');
                            lengthLabel.classList.add('text-success');
                        }
                    }
                    this.classList.remove('input-error');
                });
                
                // Initial character count
                const initialLength = input.value.length;
                if(lengthLabel) {
                    lengthLabel.textContent = `${initialLength} ký tự`;
                    if(initialLength < 40) {
                        lengthLabel.classList.add('text-error');
                    } else {
                        lengthLabel.classList.add('text-success');
                    }
                }
            });
        }, 100);
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;
        delete fileCategories[index];
        updateFileList();
    }

    // Generate cascade category selectors for each file
    function generateCategoryCascade(fileIndex) {
        return `
            <div class="category-cascade shadow-sm" id="cascade-${fileIndex}">
                <div class="category-select-group">
                    <!-- Cấp học -->
                    <div class="form-control">
                        <label class="label py-0 mb-1">
                            <span class="label-text">1. Cấp học</span>
                        </label>
                        <select class="select select-bordered select-sm w-full education-level-select" 
                                data-index="${fileIndex}"
                                onchange="onEducationLevelChange(${fileIndex}, this.value)">
                            <option value="">-- Chọn cấp học --</option>
                            ${educationLevels.map(level => `<option value="${level.code}">${level.name}</option>`).join('')}
                        </select>
                    </div>
                    
                        <!-- Lớp (for phổ thông) -->
                        <div class="form-control hidden" id="grade-container-${fileIndex}">
                            <label class="label py-0 mb-1">
                                <span class="label-text">2. Lớp</span>
                            </label>
                            <select class="select select-bordered select-sm w-full grade-select" 
                                    data-index="${fileIndex}"
                                    onchange="onGradeChange(${fileIndex}, this.value)">
                                <option value="">-- Chọn lớp --</option>
                            </select>
                        </div>
                        
                        <!-- Môn học (for phổ thông) -->
                        <div class="form-control hidden" id="subject-container-${fileIndex}">
                            <label class="label py-0 mb-1">
                                <span class="label-text">3. Môn học</span>
                            </label>
                            <select class="select select-bordered select-sm w-full subject-select" 
                                    data-index="${fileIndex}"
                                    onchange="onSubjectChange(${fileIndex}, this.value)">
                                <option value="">-- Chọn môn học --</option>
                            </select>
                        </div>
                    
                    
                        <!-- Nhóm ngành (for đại học) -->
                        <div class="form-control hidden" id="major-group-container-${fileIndex}">
                            <label class="label py-0 mb-1">
                                <span class="label-text">2. Nhóm ngành</span>
                            </label>
                            <select class="select select-bordered select-sm w-full major-group-select" 
                                    data-index="${fileIndex}"
                                    onchange="onMajorGroupChange(${fileIndex}, this.value)">
                                <option value="">-- Chọn nhóm ngành --</option>
                            </select>
                        </div>
                        
                        <!-- Ngành học (for đại học) -->
                        <div class="form-control hidden" id="major-container-${fileIndex}">
                            <label class="label py-0 mb-1">
                                <span class="label-text">3. Ngành học</span>
                            </label>
                            <select class="select select-bordered select-sm w-full major-select" 
                                    data-index="${fileIndex}"
                                    onchange="onMajorChange(${fileIndex}, this.value)">
                                <option value="">-- Chọn ngành học --</option>
                            </select>
                        </div>
                    
                    <!-- Loại tài liệu -->
                    <div class="form-control hidden" id="doc-type-container-${fileIndex}">
                        <label class="label py-0 mb-1">
                            <span class="label-text">4. Loại tài liệu</span>
                        </label>
                        <select class="select select-bordered select-sm w-full doc-type-select" 
                                data-index="${fileIndex}"
                                onchange="onDocTypeChange(${fileIndex}, this.value)">
                            <option value="">-- Chọn loại tài liệu --</option>
                        </select>
                    </div>
                </div>
                
                <!-- Summary Area -->
                <div class="mt-4 p-3 bg-primary/5 rounded-xl border border-primary/10 hidden" id="category-summary-${fileIndex}"></div>
            </div>
        `;
    }

    function initializeCascadeHandlers() {
        // Initialize fileCategories for each file
        selectedFiles.forEach((file, index) => {
            if (!fileCategories[index]) {
                fileCategories[index] = {
                    education_level: '',
                    grade_id: null,
                    subject_code: null,
                    major_group_id: null,
                    major_code: null,
                    doc_type_code: ''
                };
            }
        });
    }

    // Cascade handlers
    async function onEducationLevelChange(fileIndex, level) {
        fileCategories[fileIndex] = {
            education_level: level,
            grade_id: null,
            subject_code: null,
            major_group_id: null,
            major_code: null,
            doc_type_code: ''
        };
        
        // Hide all subsequent containers first
        hideElement(`grade-container-${fileIndex}`);
        hideElement(`subject-container-${fileIndex}`);
        hideElement(`major-group-container-${fileIndex}`);
        hideElement(`major-container-${fileIndex}`);
        hideElement(`doc-type-container-${fileIndex}`);
        hideElement(`category-summary-${fileIndex}`);
        
        if (!level) return;
        
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
        
        if (isPhoThong) {
            // Load grades
            showElement(`grade-container-${fileIndex}`);
            await loadGrades(fileIndex, level);
        } else {
            // Load major groups for đại học
            showElement(`major-group-container-${fileIndex}`);
            await loadMajorGroups(fileIndex);
        }
        
        // Load doc types
        showElement(`doc-type-container-${fileIndex}`);
        await loadDocTypes(fileIndex, level);
    }

    async function onGradeChange(fileIndex, gradeId) {
        fileCategories[fileIndex].grade_id = gradeId ? parseInt(gradeId) : null;
        fileCategories[fileIndex].subject_code = null;
        
        hideElement(`subject-container-${fileIndex}`);
        
        if (!gradeId) {
            updateCategorySummary(fileIndex);
            return;
        }
        
        // Load subjects
        showElement(`subject-container-${fileIndex}`);
        await loadSubjects(fileIndex, fileCategories[fileIndex].education_level, gradeId);
        updateCategorySummary(fileIndex);
    }

    async function onSubjectChange(fileIndex, subjectCode) {
        fileCategories[fileIndex].subject_code = subjectCode || null;
        updateCategorySummary(fileIndex);
    }

    async function onMajorGroupChange(fileIndex, groupId) {
        fileCategories[fileIndex].major_group_id = groupId ? parseInt(groupId) : null;
        fileCategories[fileIndex].major_code = null;
        
        hideElement(`major-container-${fileIndex}`);
        
        if (!groupId) {
            updateCategorySummary(fileIndex);
            return;
        }
        
        // Load majors
        showElement(`major-container-${fileIndex}`);
        await loadMajors(fileIndex, groupId);
        updateCategorySummary(fileIndex);
    }

    async function onMajorChange(fileIndex, majorCode) {
        fileCategories[fileIndex].major_code = majorCode || null;
        updateCategorySummary(fileIndex);
    }

    async function onDocTypeChange(fileIndex, docTypeCode) {
        fileCategories[fileIndex].doc_type_code = docTypeCode || '';
        updateCategorySummary(fileIndex);
    }

    // API loaders
    async function loadGrades(fileIndex, level) {
        try {
            const response = await fetch(`/handler/categories_api.php?action=grades&level=${level}`);
            const data = await response.json();
            
            if (data.success) {
                const select = document.querySelector(`#cascade-${fileIndex} .grade-select`);
                select.innerHTML = '<option value="">-- Chọn lớp --</option>' + 
                    data.data.map(grade => `<option value="${grade.id}">${grade.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading grades:', error);
        }
    }

    async function loadSubjects(fileIndex, level, gradeId) {
        try {
            const response = await fetch(`/handler/categories_api.php?action=subjects&level=${level}&grade_id=${gradeId}`);
            const data = await response.json();
            
            if (data.success) {
                const select = document.querySelector(`#cascade-${fileIndex} .subject-select`);
                select.innerHTML = '<option value="">-- Chọn môn học --</option>' + 
                    data.data.map(subject => `<option value="${subject.code}">${subject.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading subjects:', error);
        }
    }

    async function loadMajorGroups(fileIndex) {
        try {
            const response = await fetch(`/handler/categories_api.php?action=major_groups`);
            const data = await response.json();
            
            if (data.success) {
                const select = document.querySelector(`#cascade-${fileIndex} .major-group-select`);
                select.innerHTML = '<option value="">-- Chọn nhóm ngành --</option>' + 
                    data.data.map(group => `<option value="${group.id}">${group.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading major groups:', error);
        }
    }

    async function loadMajors(fileIndex, groupId) {
        try {
            const response = await fetch(`/handler/categories_api.php?action=majors&group_id=${groupId}`);
            const data = await response.json();
            
            if (data.success) {
                const select = document.querySelector(`#cascade-${fileIndex} .major-select`);
                select.innerHTML = '<option value="">-- Chọn ngành học --</option>' + 
                    data.data.map(major => `<option value="${major.code}">${major.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading majors:', error);
        }
    }

    async function loadDocTypes(fileIndex, level) {
        try {
            const response = await fetch(`/handler/categories_api.php?action=doc_types&level=${level}`);
            const data = await response.json();
            
            if (data.success) {
                const select = document.querySelector(`#cascade-${fileIndex} .doc-type-select`);
                select.innerHTML = '<option value="">-- Chọn loại tài liệu --</option>' + 
                    data.data.map(docType => `<option value="${docType.code}">${docType.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading doc types:', error);
        }
    }

    // Helper functions
    function showElement(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    }

    function hideElement(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    }

    function updateCategorySummary(fileIndex) {
        const summary = document.getElementById(`category-summary-${fileIndex}`);
        const cat = fileCategories[fileIndex];
        
        if (!cat || !cat.education_level) {
            summary.classList.add('hidden');
            return;
        }
        
        const parts = [];
        
        // Education level
        const eduSelect = document.querySelector(`#cascade-${fileIndex} .education-level-select`);
        if (eduSelect && eduSelect.selectedOptions[0]) {
            parts.push(eduSelect.selectedOptions[0].text);
        }
        
        // Grade or Major Group
        if (cat.grade_id) {
            const gradeSelect = document.querySelector(`#cascade-${fileIndex} .grade-select`);
            if (gradeSelect && gradeSelect.selectedOptions[0]) {
                parts.push(gradeSelect.selectedOptions[0].text);
            }
        }
        if (cat.major_group_id) {
            const mgSelect = document.querySelector(`#cascade-${fileIndex} .major-group-select`);
            if (mgSelect && mgSelect.selectedOptions[0]) {
                parts.push(mgSelect.selectedOptions[0].text);
            }
        }
        
        // Subject or Major
        if (cat.subject_code) {
            const subSelect = document.querySelector(`#cascade-${fileIndex} .subject-select`);
            if (subSelect && subSelect.selectedOptions[0]) {
                parts.push(subSelect.selectedOptions[0].text);
            }
        }
        if (cat.major_code) {
            const majorSelect = document.querySelector(`#cascade-${fileIndex} .major-select`);
            if (majorSelect && majorSelect.selectedOptions[0]) {
                parts.push(majorSelect.selectedOptions[0].text);
            }
        }
        
        // Doc type
        if (cat.doc_type_code) {
            const dtSelect = document.querySelector(`#cascade-${fileIndex} .doc-type-select`);
            if (dtSelect && dtSelect.selectedOptions[0]) {
                parts.push(`[${dtSelect.selectedOptions[0].text}]`);
            }
        }
        
        if (parts.length > 0) {
            summary.innerHTML = `<strong>📂 Phân loại:</strong> ${parts.join(' → ')}`;
            summary.classList.remove('hidden');
        } else {
            summary.classList.add('hidden');
        }
    }

    // Validate category selection
    function validateCategories(fileIndex) {
        const cat = fileCategories[fileIndex];
        const errors = [];
        
        if (!cat || !cat.education_level) {
            errors.push('Chưa chọn cấp học');
            return errors;
        }
        
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(cat.education_level);
        
        if (isPhoThong) {
            if (!cat.grade_id) errors.push('Chưa chọn lớp');
            if (!cat.subject_code) errors.push('Chưa chọn môn học');
        } else {
            if (!cat.major_group_id) errors.push('Chưa chọn nhóm ngành');
            if (!cat.major_code) errors.push('Chưa chọn ngành học');
        }
        
        if (!cat.doc_type_code) errors.push('Chưa chọn loại tài liệu');
        
        return errors;
    }

    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if(selectedFiles.length === 0) {
            showAlert('⚠️ Vui lòng chọn ít nhất một file để upload', 'warning');
            return;
        }

        // Validate description, file name, and categories for each file
        let validationErrors = [];
        let firstErrorElement = null;
        
        for(let i = 0; i < selectedFiles.length; i++) {
            const file = selectedFiles[i];
            const fileName = document.querySelector(`.fileName[data-index="${i}"]`)?.value || '';
            const description = document.querySelector(`.fileDescription[data-index="${i}"]`)?.value || '';
            const categoryErrors = validateCategories(i);
            
            if(!fileName.trim()) {
                validationErrors.push(`📄 ${file.name}: Chưa nhập tên tài liệu`);
                const nameInput = document.querySelector(`.fileName[data-index="${i}"]`);
                if(nameInput) {
                    nameInput.classList.add('input-error');
                    if(!firstErrorElement) firstErrorElement = nameInput;
                }
            } else if(fileName.trim().length < 40) {
                validationErrors.push(`📄 ${file.name}: Tên tài liệu phải có ít nhất 40 ký tự (hiện tại: ${fileName.trim().length} ký tự)`);
                const nameInput = document.querySelector(`.fileName[data-index="${i}"]`);
                if(nameInput) {
                    nameInput.classList.add('input-error');
                    if(!firstErrorElement) firstErrorElement = nameInput;
                }
            }
            
            if(!description.trim()) {
                validationErrors.push(`📄 ${file.name}: Chưa nhập mô tả`);
                const descInput = document.querySelector(`.fileDescription[data-index="${i}"]`);
                if(descInput) {
                    descInput.classList.add('input-error');
                    if(!firstErrorElement) firstErrorElement = descInput;
                }
            }
            
            if(categoryErrors.length > 0) {
                validationErrors.push(`📄 ${file.name}: ${categoryErrors.join(', ')}`);
                const cascade = document.getElementById(`cascade-${i}`);
                if(cascade && !firstErrorElement) firstErrorElement = cascade;
            }
        }
        
        if(validationErrors.length > 0) {
            if(firstErrorElement) {
                firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            const errorMessage = `
                <div style="text-align: left;">
                    <strong>⚠️ Vui lòng điền đầy đủ thông tin bắt buộc:</strong>
                    <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                        ${validationErrors.map(err => `<li>${err}</li>`).join('')}
                    </ul>
                </div>
            `;
            showAlert(errorMessage, 'error');
            return;
        }

        submitBtn.disabled = true;
        uploadProgress.classList.remove('hidden');
        progressContainer.innerHTML = '';
        alertMessage.innerHTML = '';

        let successCount = 0;
        let failCount = 0;
        const results = [];

        for(let i = 0; i < selectedFiles.length; i++) {
            const file = selectedFiles[i];
            const fileName = document.querySelector(`.fileName[data-index="${i}"]`)?.value || '';
            const description = document.querySelector(`.fileDescription[data-index="${i}"]`)?.value || '';
            const isPublic = document.querySelector(`.filePrivacy[data-index="${i}"]`)?.checked ? 1 : 0;
            const categories = fileCategories[i];

            try {
                await uploadFileSequential(file, fileName, description, isPublic, categories, i, selectedFiles.length);
                successCount++;
            } catch(error) {
                failCount++;
                results[i] = { success: false, name: file.name, error: error.message };
            }
        }

        uploadProgress.classList.add('hidden');
        submitBtn.disabled = false;

        if(successCount > 0) {
            showAlert(`✓ Upload thành công ${successCount} file!`, 'success');
            
            fileInput.value = '';
            selectedFiles = [];
            fileList.classList.add('hidden');
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        }

        if(failCount > 0) {
            const failedFiles = results.filter(r => r && !r.success).map(r => `${r.name}: ${r.error}`).join('\n');
            showAlert(`⚠️ Upload thất bại ${failCount} file:\n${failedFiles}`, 'error');
        }
    });

    function uploadFileSequential(file, fileName, description, isPublic, categories, index, total) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('document_name', fileName);
            formData.append('description', description);
            formData.append('is_public', isPublic);
            
            // Add category data as JSON
            if (categories) {
                formData.append('category_data', JSON.stringify(categories));
            }

            const progressItem = document.createElement('div');
            progressItem.id = `progress-${index}`;
            progressItem.className = 'mb-4';
            progressItem.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <div class="flex-1">
                        <div class="font-semibold text-sm">📄 ${file.name}</div>
                        <div class="text-xs text-base-content/70">File ${index + 1}/${total}</div>
                    </div>
                    <div id="status-${index}" class="text-sm text-base-content/70">⏳ Uploading...</div>
                </div>
                <progress id="bar-${index}" class="progress progress-primary w-full" value="0" max="100"></progress>
            `;
            progressContainer.appendChild(progressItem);

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if(e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    const bar = document.getElementById(`bar-${index}`);
                    const status = document.getElementById(`status-${index}`);
                    bar.value = percentComplete;
                    status.textContent = percentComplete.toFixed(0) + '%';
                }
            });

            xhr.addEventListener('load', async () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    const bar = document.getElementById(`bar-${index}`);
                    const status = document.getElementById(`status-${index}`);
                    if(xhr.status === 200 && response.success) {
                        bar.classList.remove('progress-primary');
                        bar.classList.add('progress-success');
                        bar.value = 100;
                        status.innerHTML = '✓ <span class="text-success">Success</span>';
                        
                        // Process PDF/DOCX files using client-side PDF.js
                        if(response.needs_client_processing && response.doc_id && response.pdf_path_for_processing) {
                            status.innerHTML = '✓ <span class="text-success">Success</span> | Processing PDF...';
                            try {
                                const result = await processPdfDocument(
                                    response.pdf_path_for_processing,
                                    response.doc_id,
                                    {
                                        countPages: true,
                                        generateThumbnail: true,
                                        thumbnailWidth: 400
                                    }
                                );
                                
                                if(result.success) {
                                    status.innerHTML = `✓ <span class="text-success">Success</span> | ✓ ${result.pages} pages | ✓ Thumbnail`;
                                } else {
                                    status.innerHTML = '✓ <span class="text-success">Success</span> | ⚠ Processing incomplete';
                                }
                            } catch(processError) {
                                console.error('PDF processing failed:', processError);
                                status.innerHTML = '✓ <span class="text-success">Success</span> | ⚠ Processing failed';
                            }
                        }
                        
                        resolve();
                    } else {
                        throw new Error(response.message || 'Upload failed');
                    }
                } catch(error) {
                    const bar = document.getElementById(`bar-${index}`);
                    const status = document.getElementById(`status-${index}`);
                    bar.classList.remove('progress-primary');
                    bar.classList.add('progress-error');
                    status.innerHTML = '✗ <span class="text-error">Failed</span>';
                    reject(new Error(error.message || 'Invalid response'));
                }
            });

            xhr.addEventListener('error', () => {
                const bar = document.getElementById(`bar-${index}`);
                const status = document.getElementById(`status-${index}`);
                bar.classList.remove('progress-primary');
                bar.classList.add('progress-error');
                status.innerHTML = '✗ <span class="text-error">Error</span>';
                reject(new Error('Network error'));
            });

            xhr.addEventListener('abort', () => {
                reject(new Error('Upload cancelled'));
            });

            xhr.open('POST', 'handler/upload_handler.php', true);
            xhr.send(formData);
        });
    }

    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-error' : 'alert-warning';
        alertMessage.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
    }
</script>
