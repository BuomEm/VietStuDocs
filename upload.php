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

// Get all active categories grouped by type
$categories_grouped = getAllCategoriesGrouped(true);

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

        <!-- <div class="card">
            <h4 class="card-title card-title-with-icon">
                <span class="card-icon">📋</span>
                <span>Upload Guide</span>
            </h4>
            <ul class="guide-list">
                <li class="guide-item">✓ Select multiple files</li>
                <li class="guide-item"><strong>✓ Add descriptions *</strong></li>
                <li class="guide-item"><strong>✓ Select categories *</strong></li>
                <li class="guide-item">✓ Choose privacy level</li>
                <li class="guide-item guide-item-last">✓ Upload & auto-verify</li>
            </ul>
            <div style="margin-top: 12px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; font-size: 11px; color: #856404;">
                <strong>*</strong> Bắt buộc phải điền
            </div>
        </div> -->

        <!-- <div class="card">
            <h4 class="card-title card-title-with-icon">
                <span class="card-icon">📁</span>
                <span>Supported Files</span>
            </h4>
            <div class="supported-files">
                <div class="file-type"><strong>Documents:</strong> PDF, DOC, DOCX</div>
                <div class="file-type"><strong>Sheets:</strong> XLS, XLSX</div>
                <div class="file-type"><strong>Presentations:</strong> PPT, PPTX</div>
                <div class="file-type"><strong>Media:</strong> JPG, PNG, ZIP</div>
                <div class="file-type"><strong>Text:</strong> TXT</div>
                <div class="file-type file-type-limit">Max 200MB per file</div>
            </div>
        </div> -->
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
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
            <!-- <a href="dashboard.php" class="btn btn-ghost">← Back to Dashboard</a> -->
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
                    <div id="fileListContent" class="space-y-4 max-h-[500px] overflow-y-auto"></div>
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
    
    /* Category type label styling */
    .category-type-label {
        display: block;
        font-size: 11px;
        color: hsl(var(--p));
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }
</style>

<!-- PDF.js for thumbnail generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="js/pdf-functions.js"></script>
<script>
    // Categories data from PHP
    const categoriesData = <?= json_encode($categories_grouped) ?>;
    
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
    let fileCategories = {}; // Store selected categories for each file

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
                                <span class="label-text font-semibold">Description <span class="text-error">*</span></span>
                            </label>
                            <input type="text" class="input input-bordered fileDescription" data-index="${index}" placeholder="Nhập mô tả tài liệu..." required>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text font-semibold">📂 Categories <span class="text-error">*</span> <small class="text-base-content/70">(Chọn ít nhất 1 category)</small></span>
                            </label>
                            ${generateCategorySelectors(index)}
                            <div class="selected-categories mt-2" id="selected-cats-${index}"></div>
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
        } else {
            fileList.classList.add('hidden');
        }
        
        // Add event listeners to remove error highlighting
        setTimeout(() => {
            document.querySelectorAll('.fileDescription').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('input-error');
                });
            });
        }, 100);
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;
        delete fileCategories[index]; // Remove categories for this file
        updateFileList();
    }

    // Category Management Functions
    function generateCategorySelectors(fileIndex) {
        const types = [
            { key: 'field', label: 'Lĩnh vực' },
            { key: 'subject', label: 'Môn học' },
            { key: 'level', label: 'Cấp học' },
            { key: 'curriculum', label: 'Chương trình' },
            { key: 'doc_type', label: 'Loại tài liệu' }
        ];
        
        return types.map(type => {
            const categories = categoriesData[type.key] || [];
            if (categories.length === 0) return '';
            
            return `
                <div class="categories-group">
                    <span class="category-type-label">${type.label}</span>
                    <select class="select select-bordered w-full mb-2" onchange="addCategory(${fileIndex}, this.value, '${type.key}', this)">
                        <option value="">-- Select ${type.label} --</option>
                        ${categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                    </select>
                </div>
            `;
        }).join('');
    }

    function addCategory(fileIndex, categoryId, categoryType, selectElement) {
        if (!categoryId) return;
        
        categoryId = parseInt(categoryId);
        
        // Initialize array if needed
        if (!fileCategories[fileIndex]) {
            fileCategories[fileIndex] = [];
        }
        
        // Check if already added
        if (fileCategories[fileIndex].includes(categoryId)) {
            selectElement.value = '';
            return;
        }
        
        // Add category
        fileCategories[fileIndex].push(categoryId);
        
        // Reset select
        selectElement.value = '';
        
        // Render tags
        renderSelectedCategories(fileIndex);
    }

    function removeCategory(fileIndex, categoryId) {
        if (!fileCategories[fileIndex]) return;
        
        const index = fileCategories[fileIndex].indexOf(categoryId);
        if (index > -1) {
            fileCategories[fileIndex].splice(index, 1);
        }
        
        renderSelectedCategories(fileIndex);
    }

    function renderSelectedCategories(fileIndex) {
        const container = document.getElementById(`selected-cats-${fileIndex}`);
        if (!container) return;
        
        // Remove error highlighting when categories are selected
        container.classList.remove('border-2', 'border-error', 'border-dashed', 'bg-error/10', 'p-2', 'rounded');
        
        const selectedCatIds = fileCategories[fileIndex] || [];
        if (selectedCatIds.length === 0) {
            container.innerHTML = '<div style="font-size: 11px; color: #999; padding: 4px 0;">Chưa chọn category nào</div>';
            return;
        }
        
        // Find category details
        const allCategories = [];
        Object.keys(categoriesData).forEach(type => {
            allCategories.push(...categoriesData[type]);
        });
        
        const selectedDetails = selectedCatIds.map(id => {
            return allCategories.find(cat => cat.id == id);
        }).filter(cat => cat != null);
        
        container.innerHTML = selectedDetails.map(cat => `
            <span class="category-tag">
                ${cat.name}
                <button type="button" class="category-tag-remove" onclick="removeCategory(${fileIndex}, ${cat.id})">×</button>
            </span>
        `).join('');
    }

    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if(selectedFiles.length === 0) {
            showAlert('⚠️ Vui lòng chọn ít nhất một file để upload', 'warning');
            return;
        }

        // Validate description and categories for each file
        let validationErrors = [];
        let firstErrorElement = null;
        
        for(let i = 0; i < selectedFiles.length; i++) {
            const file = selectedFiles[i];
            const description = document.querySelector(`.fileDescription[data-index="${i}"]`)?.value || '';
            const categories = fileCategories[i] || [];
            
            if(!description.trim()) {
                validationErrors.push(`📄 ${file.name}: Chưa nhập mô tả`);
                // Highlight the description input
                const descInput = document.querySelector(`.fileDescription[data-index="${i}"]`);
                if(descInput) {
                    descInput.classList.add('input-error');
                    if(!firstErrorElement) firstErrorElement = descInput;
                }
            }
            
            if(categories.length === 0) {
                validationErrors.push(`📄 ${file.name}: Chưa chọn category`);
                // Highlight the categories section
                const catsContainer = document.getElementById(`selected-cats-${i}`);
                if(catsContainer) {
                    catsContainer.classList.add('border-2', 'border-error', 'border-dashed', 'bg-error/10', 'p-2', 'rounded');
                    if(!firstErrorElement) firstErrorElement = catsContainer;
                }
            }
        }
        
        if(validationErrors.length > 0) {
            // Scroll to first error
            if(firstErrorElement) {
                firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Show detailed error message
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
            const description = document.querySelector(`.fileDescription[data-index="${i}"]`)?.value || '';
            const isPublic = document.querySelector(`.filePrivacy[data-index="${i}"]`)?.checked ? 1 : 0;
            const categories = fileCategories[i] || [];

            try {
                await uploadFileSequential(file, description, isPublic, categories, i, selectedFiles.length);
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

    function uploadFileSequential(file, description, isPublic, categories, index, total) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('description', description);
            formData.append('is_public', isPublic);
            
            // Add categories as JSON array
            if (categories && categories.length > 0) {
                formData.append('categories', JSON.stringify(categories));
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
                                // Use shared function to count pages and generate thumbnail
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
