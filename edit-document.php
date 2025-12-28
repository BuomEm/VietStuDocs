<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/db.php';
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
$doc = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT * FROM documents WHERE id=$doc_id AND user_id=$user_id"));

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
    $description = !empty($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';
    $is_public = isset($_POST['is_public']) && $_POST['is_public'] == '1' ? 1 : 0;
    
    // Get category data
    $category_data = null;
    if (!empty($_POST['category_data'])) {
        $category_data = json_decode($_POST['category_data'], true);
    }
    
    // Update document
    $update_query = "UPDATE documents SET 
                     description='$description', 
                     is_public=$is_public
                     WHERE id=$doc_id AND user_id=$user_id";
    
    if(mysqli_query($conn, $update_query)) {
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
        $error = "Có lỗi xảy ra khi cập nhật: " . mysqli_error($conn);
    }
}

$page_title = "Edit Document - DocShare";
$current_page = 'dashboard';
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <div class="max-w-2xl mx-auto">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h1 class="card-title text-2xl flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Chỉnh Sửa Tài Liệu
                    </h1>
                    <p class="text-base-content/70">Cập nhật thông tin tài liệu của bạn</p>
                    
                    <?php if(isset($error)): ?>
                        <div class="alert alert-error">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
                        <div class="alert alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Tài liệu đã được cập nhật thành công</span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="editForm" class="space-y-4">
                        <input type="hidden" name="category_data" id="category_data_input" value="">
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Tên Tài Liệu</span>
                            </label>
                            <input type="text" value="<?= htmlspecialchars($doc['original_name']) ?>" 
                                   class="input input-bordered" readonly disabled>
                            <label class="label">
                                <span class="label-text-alt">Tên tài liệu không thể thay đổi</span>
                            </label>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Mô Tả</span>
                            </label>
                            <textarea name="description" rows="5" 
                                      class="textarea textarea-bordered"
                                      placeholder="Nhập mô tả về tài liệu của bạn..."><?= htmlspecialchars($doc['description'] ?? '') ?></textarea>
                            <label class="label">
                                <span class="label-text-alt">Mô tả giúp người dùng hiểu rõ hơn về nội dung tài liệu</span>
                            </label>
                        </div>
                        
                        <!-- Category Cascade Selection -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">📂 Phân loại</span>
                            </label>
                            <div class="category-cascade bg-base-200 p-4 rounded-lg" id="category-cascade">
                                <!-- Cấp học -->
                                <div class="form-control mb-3">
                                    <label class="label py-1">
                                        <span class="label-text text-xs uppercase font-semibold text-primary">Cấp học</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" id="education_level">
                                        <option value="">-- Chọn cấp học --</option>
                                        <?php foreach($education_levels as $level): ?>
                                            <option value="<?= $level['code'] ?>" <?= ($current_category && $current_category['education_level'] == $level['code']) ? 'selected' : '' ?>><?= $level['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Lớp (for phổ thông) -->
                                <div class="form-control mb-3 hidden" id="grade_container">
                                    <!-- <div class="text-center text-primary text-xs my-1">↓</div> -->
                                    <label class="label py-1">
                                        <span class="label-text text-xs uppercase font-semibold text-primary">Lớp</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" id="grade_id">
                                        <option value="">-- Chọn lớp --</option>
                                    </select>
                                </div>
                                
                                <!-- Môn học (for phổ thông) -->
                                <div class="form-control mb-3 hidden" id="subject_container">
                                    <!-- <div class="text-center text-primary text-xs my-1">↓</div> -->
                                    <label class="label py-1">
                                        <span class="label-text text-xs uppercase font-semibold text-primary">Môn học</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" id="subject_code">
                                        <option value="">-- Chọn môn học --</option>
                                    </select>
                                </div>
                                
                                <!-- Nhóm ngành (for đại học) -->
                                <div class="form-control mb-3 hidden" id="major_group_container">
                                    <!-- <div class="text-center text-primary text-xs my-1">↓</div> -->
                                    <label class="label py-1">
                                        <span class="label-text text-xs uppercase font-semibold text-primary">Nhóm ngành</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" id="major_group_id">
                                        <option value="">-- Chọn nhóm ngành --</option>
                                    </select>
                                </div>
                                
                                <!-- Ngành học (for đại học) -->
                                <div class="form-control mb-3 hidden" id="major_container">
                                    <!-- <div class="text-center text-primary text-xs my-1">↓</div> -->
                                    <label class="label py-1">
                                        <span class="label-text text-xs uppercase font-semibold text-primary">Ngành học</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" id="major_code">
                                        <option value="">-- Chọn ngành học --</option>
                                    </select>
                                </div>
                                
                                <!-- Loại tài liệu -->
                                <div class="form-control mb-3 hidden" id="doc_type_container">
                                    <!-- <div class="text-center text-primary text-xs my-1">↓</div> -->
                                    <label class="label py-1">
                                        <span class="label-text text-xs uppercase font-semibold text-primary">Loại tài liệu</span>
                                    </label>
                                    <select class="select select-bordered select-sm w-full" id="doc_type_code">
                                        <option value="">-- Chọn loại tài liệu --</option>
                                    </select>
                                </div>
                                
                                <!-- Summary -->
                                <div class="mt-3 p-2 bg-base-100 rounded text-xs hidden" id="category-summary"></div>
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Quyền Riêng Tư</span>
                            </label>
                            <div class="space-y-2">
                                <label class="label cursor-pointer">
                                    <span class="label-text">
                                        <div class="font-semibold flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m-2.284 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                            </svg>
                                            Công Khai
                                        </div>
                                        <div class="text-sm text-base-content/70">Mọi người có thể xem và tìm thấy tài liệu này</div>
                                    </span>
                                    <input type="radio" name="is_public" value="1" class="radio radio-primary" <?= $doc['is_public'] == 1 ? 'checked' : '' ?>>
                                </label>
                                <label class="label cursor-pointer">
                                    <span class="label-text">
                                        <div class="font-semibold flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                            </svg>
                                            Riêng Tư
                                        </div>
                                        <div class="text-sm text-base-content/70">Chỉ bạn mới có thể xem tài liệu này</div>
                                    </span>
                                    <input type="radio" name="is_public" value="0" class="radio radio-primary" <?= $doc['is_public'] == 0 ? 'checked' : '' ?>>
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="font-semibold flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    Thông Tin Bổ Sung
                                </div>
                                <div class="text-sm">
                                    <div><strong>Trạng thái:</strong> 
                                        <?php 
                                        $status_icons = [
                                            'pending' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> Đang Duyệt',
                                            'approved' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> Đã Duyệt',
                                            'rejected' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> Đã Từ Chối'
                                        ];
                                        $status_labels = $status_icons;
                                        echo $status_labels[$doc['status']] ?? ucfirst($doc['status']);
                                        ?>
                                    </div>
                                    <div class="mt-1"><strong>Ngày tạo:</strong> <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-actions justify-end mt-6">
                            <a href="dashboard.php" class="btn btn-ghost">← Hủy</a>
                            <button type="submit" class="btn btn-primary flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Lưu Thay Đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
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
const categoryDataInput = document.getElementById('category_data_input');
const editForm = document.getElementById('editForm');

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
    });
});

async function loadCascadeFromState() {
    const level = categoryData.education_level;
    const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
    
    if (isPhoThong) {
        // Show grade container and load grades
        showElement('grade_container');
        await loadGrades(level);
        
        if (categoryData.grade_id) {
            gradeSelect.value = categoryData.grade_id;
            showElement('subject_container');
            await loadSubjects(level, categoryData.grade_id);
            
            if (categoryData.subject_code) {
                subjectSelect.value = categoryData.subject_code;
            }
        }
    } else if (level === 'dai_hoc') {
        // Show major group container and load major groups
        showElement('major_group_container');
        await loadMajorGroups();
        
        if (categoryData.major_group_id) {
            majorGroupSelect.value = categoryData.major_group_id;
            showElement('major_container');
            await loadMajors(categoryData.major_group_id);
            
            if (categoryData.major_code) {
                majorSelect.value = categoryData.major_code;
            }
        }
    }
    
    // Load doc types
    if (level) {
        showElement('doc_type_container');
        await loadDocTypes(level);
        
        if (categoryData.doc_type_code) {
            docTypeSelect.value = categoryData.doc_type_code;
        }
    }
    
    updateSummary();
}

async function onEducationLevelChange(e) {
    const level = e.target.value;
    categoryData = {
        education_level: level,
        grade_id: null,
        subject_code: null,
        major_group_id: null,
        major_code: null,
        doc_type_code: ''
    };
    
    // Hide all containers
    hideElement('grade_container');
    hideElement('subject_container');
    hideElement('major_group_container');
    hideElement('major_container');
    hideElement('doc_type_container');
    hideElement('category-summary');
    
    if (!level) return;
    
    const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
    
    if (isPhoThong) {
        showElement('grade_container');
        await loadGrades(level);
    } else {
        showElement('major_group_container');
        await loadMajorGroups();
    }
    
    showElement('doc_type_container');
    await loadDocTypes(level);
}

async function onGradeChange(e) {
    const gradeId = e.target.value;
    categoryData.grade_id = gradeId ? parseInt(gradeId) : null;
    categoryData.subject_code = null;
    
    hideElement('subject_container');
    
    if (!gradeId) {
        updateSummary();
        return;
    }
    
    showElement('subject_container');
    await loadSubjects(categoryData.education_level, gradeId);
    updateSummary();
}

function onSubjectChange(e) {
    categoryData.subject_code = e.target.value || null;
    updateSummary();
}

async function onMajorGroupChange(e) {
    const groupId = e.target.value;
    categoryData.major_group_id = groupId ? parseInt(groupId) : null;
    categoryData.major_code = null;
    
    hideElement('major_container');
    
    if (!groupId) {
        updateSummary();
        return;
    }
    
    showElement('major_container');
    await loadMajors(groupId);
    updateSummary();
}

function onMajorChange(e) {
    categoryData.major_code = e.target.value || null;
    updateSummary();
}

function onDocTypeChange(e) {
    categoryData.doc_type_code = e.target.value || '';
    updateSummary();
}

// API loaders
async function loadGrades(level) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=grades&level=${level}`);
        const data = await response.json();
        
        if (data.success) {
            gradeSelect.innerHTML = '<option value="">-- Chọn lớp --</option>' + 
                data.data.map(grade => `<option value="${grade.id}">${grade.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading grades:', error);
    }
}

async function loadSubjects(level, gradeId) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=subjects&level=${level}&grade_id=${gradeId}`);
        const data = await response.json();
        
        if (data.success) {
            subjectSelect.innerHTML = '<option value="">-- Chọn môn học --</option>' + 
                data.data.map(subject => `<option value="${subject.code}">${subject.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
}

async function loadMajorGroups() {
    try {
        const response = await fetch(`/handler/categories_api.php?action=major_groups`);
        const data = await response.json();
        
        if (data.success) {
            majorGroupSelect.innerHTML = '<option value="">-- Chọn nhóm ngành --</option>' + 
                data.data.map(group => `<option value="${group.id}">${group.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading major groups:', error);
    }
}

async function loadMajors(groupId) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=majors&group_id=${groupId}`);
        const data = await response.json();
        
        if (data.success) {
            majorSelect.innerHTML = '<option value="">-- Chọn ngành học --</option>' + 
                data.data.map(major => `<option value="${major.code}">${major.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading majors:', error);
    }
}

async function loadDocTypes(level) {
    try {
        const response = await fetch(`/handler/categories_api.php?action=doc_types&level=${level}`);
        const data = await response.json();
        
        if (data.success) {
            docTypeSelect.innerHTML = '<option value="">-- Chọn loại tài liệu --</option>' + 
                data.data.map(docType => `<option value="${docType.code}">${docType.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading doc types:', error);
    }
}

// Helper functions
function showElement(id) {
    document.getElementById(id)?.classList.remove('hidden');
}

function hideElement(id) {
    document.getElementById(id)?.classList.add('hidden');
}

function updateSummary() {
    const parts = [];
    
    if (categoryData.education_level && educationLevelSelect.selectedOptions[0]) {
        parts.push(educationLevelSelect.selectedOptions[0].text);
    }
    
    if (categoryData.grade_id && gradeSelect.selectedOptions[0] && gradeSelect.value) {
        parts.push(gradeSelect.selectedOptions[0].text);
    }
    
    if (categoryData.subject_code && subjectSelect.selectedOptions[0] && subjectSelect.value) {
        parts.push(subjectSelect.selectedOptions[0].text);
    }
    
    if (categoryData.major_group_id && majorGroupSelect.selectedOptions[0] && majorGroupSelect.value) {
        parts.push(majorGroupSelect.selectedOptions[0].text);
    }
    
    if (categoryData.major_code && majorSelect.selectedOptions[0] && majorSelect.value) {
        parts.push(majorSelect.selectedOptions[0].text);
    }
    
    if (categoryData.doc_type_code && docTypeSelect.selectedOptions[0] && docTypeSelect.value) {
        parts.push(`[${docTypeSelect.selectedOptions[0].text}]`);
    }
    
    if (parts.length > 0) {
        categorySummary.innerHTML = `<strong>📂 Phân loại:</strong> ${parts.join(' → ')}`;
        categorySummary.classList.remove('hidden');
    } else {
        categorySummary.classList.add('hidden');
    }
}
</script>

<?php 
mysqli_close($conn);
?>
