<?php
session_start();
require_once 'config/db.php';
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
$pending_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM documents WHERE user_id=$user_id AND status='pending'"));
$approved_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM documents WHERE user_id=$user_id AND status='approved'"));

?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <!-- Stats Cards - Horizontal Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Points Balance Card -->
            <div class="card bg-primary text-primary-content shadow-lg">
                <div class="card-body text-center">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h3 class="text-sm font-semibold uppercase tracking-wide opacity-90">Your Points Balance</h3>
                    </div>
                    <div class="text-4xl font-bold my-2"><?= number_format($user_points['current_points']) ?></div>
                    <div class="text-xs opacity-80">Earned: <?= number_format($user_points['total_earned']) ?> | Spent: <?= number_format($user_points['total_spent']) ?></div>
                </div>
            </div>

            <!-- Your Documents Card -->
            <div class="card bg-base-100 shadow-lg">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        Your Documents
                    </h3>
                    <div class="stats stats-horizontal shadow-sm w-full">
                        <div class="stat">
                            <div class="stat-title text-xs">Pending Review</div>
                            <div class="stat-value text-warning text-2xl"><?= $pending_count ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-title text-xs">Approved</div>
                            <div class="stat-value text-success text-2xl"><?= $approved_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Upload Documents
                </h1>
                <p class="text-base-content/70 mt-2">Upload and share your documents with the community</p>
            </div>
        </div>

        <div id="alertMessage" class="mb-4"></div>

        <form id="uploadForm" class="upload-form">
            <!-- Drag Drop Area -->
            <div id="dragDropArea" class="card bg-base-200 border-dashed border-4 border-primary cursor-pointer hover:bg-base-300 transition-colors min-h-[400px] flex flex-col items-center justify-center p-8 mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 text-primary mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <p class="text-2xl font-bold text-primary mb-2">Drag & Drop Files Here</p>
                <p class="text-base-content/70">or click to select files from your device</p>
            </div>

            <input type="file" id="fileInput" name="documents" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip" class="file-input-hidden">

            <!-- File List -->
            <div id="fileList" class="card bg-base-100 shadow-md mb-6 hidden">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-1.932-2.639l-3.866-2.437a3.375 3.375 0 0 0-2.932-1.305H8.25a3.375 3.375 0 0 0-3.375 3.375v2.625M19.5 14.25l-3.866 2.437M19.5 14.25v2.625M15.634 16.687l-3.866-2.437M8.25 10.5v2.625m0-2.625l3.866 2.437M8.25 10.5H4.875a3.375 3.375 0 0 0-3.375 3.375v2.625M8.25 10.5v-2.625a3.375 3.375 0 0 1 3.375-3.375h3.866a3.375 3.375 0 0 1 3.375 3.375v2.625" />
                        </svg>
                        Files to Upload (<span id="fileCount">0</span>)
                    </h3>
                    <div id="fileListContent" class="space-y-4 max-h-[600px] overflow-y-auto"></div>
                </div>
            </div>

            <!-- Upload Progress -->
            <div id="uploadProgress" class="card bg-base-100 shadow-md mb-6 hidden">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Upload Progress
                    </h3>
                    <div id="progressContainer" class="space-y-4"></div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex gap-4 mt-6">
                <button type="submit" id="submitBtn" class="btn btn-primary btn-lg flex-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Upload Files
                </button>
            </div>
        </form>
    </main>
    
<?php 
    mysqli_close($conn);
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
        background: hsl(var(--b2));
        border-radius: 8px;
        padding: 16px;
    }
    
    .category-cascade .form-control {
        margin-bottom: 12px;
    }
    
    .category-cascade .form-control:last-child {
        margin-bottom: 0;
    }
    
    .category-cascade .label-text {
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: hsl(var(--p));
    }
    
    .cascade-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        color: hsl(var(--p));
        font-size: 12px;
        margin: 8px 0;
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
        if(selectedFiles.length > 0) {
            fileList.classList.remove('hidden');
            fileCount.textContent = selectedFiles.length;
            fileListContent.innerHTML = selectedFiles.map((file, index) => `
                <div class="card bg-base-200 border-l-4 border-primary">
                    <div class="card-body p-4">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <div class="font-semibold text-base">📄 ${file.name}</div>
                                <div class="text-sm text-base-content/70">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                            </div>
                            <button type="button" onclick="removeFile(${index})" class="btn btn-sm btn-error">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                Remove
                            </button>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text font-semibold">Tên Tài Liệu <span class="text-error">*</span> <small class="text-base-content/70">(Tối thiểu 40 ký tự)</small></span>
                            </label>
                            <input type="text" class="input input-bordered fileName" data-index="${index}" placeholder="Nhập tên tài liệu (tối thiểu 40 ký tự)..." value="${getFileNameWithoutExtension(file.name)}" required>
                            <label class="label">
                                <span class="label-text-alt text-base-content/70" id="name-length-${index}">0 ký tự</span>
                            </label>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text font-semibold">Mô tả <span class="text-error">*</span></span>
                            </label>
                            <input type="text" class="input input-bordered fileDescription" data-index="${index}" placeholder="Nhập mô tả tài liệu..." required>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text font-semibold">📂 Phân loại <span class="text-error">*</span></span>
                            </label>
                            ${generateCategoryCascade(index)}
                        </div>
                        
                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" class="checkbox filePrivacy" data-index="${index}" checked>
                                <span class="label-text"><strong>Public</strong> <span class="text-base-content/70">(visible to all users)</span></span>
                            </label>
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Initialize cascade handlers for each file
            initializeCascadeHandlers();
        } else {
            fileList.classList.add('hidden');
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
            <div class="category-cascade" id="cascade-${fileIndex}">
                <!-- Cấp học -->
                <div class="form-control">
                    <label class="label py-1">
                        <span class="label-text">Cấp học</span>
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
                    <!-- <div class="cascade-arrow">↓</div> -->
                    <label class="label py-1">
                        <span class="label-text">Lớp</span>
                    </label>
                    <select class="select select-bordered select-sm w-full grade-select" 
                            data-index="${fileIndex}"
                            onchange="onGradeChange(${fileIndex}, this.value)">
                        <option value="">-- Chọn lớp --</option>
                    </select>
                </div>
                
                <!-- Môn học (for phổ thông) -->
                <div class="form-control hidden" id="subject-container-${fileIndex}">
                    <!-- <div class="cascade-arrow">↓</div> -->
                    <label class="label py-1">
                        <span class="label-text">Môn học</span>
                    </label>
                    <select class="select select-bordered select-sm w-full subject-select" 
                            data-index="${fileIndex}"
                            onchange="onSubjectChange(${fileIndex}, this.value)">
                        <option value="">-- Chọn môn học --</option>
                    </select>
                </div>
                
                <!-- Nhóm ngành (for đại học) -->
                <div class="form-control hidden" id="major-group-container-${fileIndex}">
                    <!-- <div class="cascade-arrow">↓</div> -->
                    <label class="label py-1">
                        <span class="label-text">Nhóm ngành</span>
                    </label>
                    <select class="select select-bordered select-sm w-full major-group-select" 
                            data-index="${fileIndex}"
                            onchange="onMajorGroupChange(${fileIndex}, this.value)">
                        <option value="">-- Chọn nhóm ngành --</option>
                    </select>
                </div>
                
                <!-- Ngành học (for đại học) -->
                <div class="form-control hidden" id="major-container-${fileIndex}">
                    <!-- <div class="cascade-arrow">↓</div> -->
                    <label class="label py-1">
                        <span class="label-text">Ngành học</span>
                    </label>
                    <select class="select select-bordered select-sm w-full major-select" 
                            data-index="${fileIndex}"
                            onchange="onMajorChange(${fileIndex}, this.value)">
                        <option value="">-- Chọn ngành học --</option>
                    </select>
                </div>
                
                <!-- Loại tài liệu -->
                <div class="form-control hidden" id="doc-type-container-${fileIndex}">
                    <!-- <div class="cascade-arrow">↓</div> -->
                    <label class="label py-1">
                        <span class="label-text">Loại tài liệu</span>
                    </label>
                    <select class="select select-bordered select-sm w-full doc-type-select" 
                            data-index="${fileIndex}"
                            onchange="onDocTypeChange(${fileIndex}, this.value)">
                        <option value="">-- Chọn loại tài liệu --</option>
                    </select>
                </div>
                
                <!-- Summary -->
                <div class="mt-3 p-2 bg-base-100 rounded text-xs hidden" id="category-summary-${fileIndex}"></div>
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
