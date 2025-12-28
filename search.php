<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/search.php';
require_once 'config/categories.php';

$user_id = isset($_SESSION['user_id']) ? getCurrentUserId() : null;
$is_logged_in = isset($_SESSION['user_id']);

// Get search parameters
$keyword = isset($_GET['q']) ? sanitizeSearchKeyword($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Get new category filters
$category_filters = [
    'education_level' => $_GET['education_level'] ?? '',
    'grade_id' => $_GET['grade_id'] ?? null,
    'subject_code' => $_GET['subject_code'] ?? null,
    'major_group_id' => $_GET['major_group_id'] ?? null,
    'major_code' => $_GET['major_code'] ?? null,
    'doc_type_code' => $_GET['doc_type_code'] ?? ''
];

// Build search filters for the new category structure
$filters = [];
if (!empty($category_filters['education_level'])) {
    $filters['education_level'] = $category_filters['education_level'];
}
if (!empty($category_filters['grade_id'])) {
    $filters['grade_id'] = intval($category_filters['grade_id']);
}
if (!empty($category_filters['subject_code'])) {
    $filters['subject_code'] = $category_filters['subject_code'];
}
if (!empty($category_filters['major_group_id'])) {
    $filters['major_group_id'] = intval($category_filters['major_group_id']);
}
if (!empty($category_filters['major_code'])) {
    $filters['major_code'] = $category_filters['major_code'];
}
if (!empty($category_filters['doc_type_code'])) {
    $filters['doc_type_code'] = $category_filters['doc_type_code'];
}

// Perform search using the new searchDocumentsByCategory function for category filters
// or fallback to regular search if no category filters
if (!empty($filters)) {
    $all_docs = searchDocumentsByCategory($filters);
    
    // Apply keyword filter if provided
    if (!empty($keyword)) {
        $keyword_lower = mb_strtolower($keyword);
        $all_docs = array_filter($all_docs, function($doc) use ($keyword_lower) {
            return mb_stripos($doc['original_name'], $keyword_lower) !== false ||
                   mb_stripos($doc['description'] ?? '', $keyword_lower) !== false;
        });
    }
    
    // Apply sorting
    if ($sort === 'popular') {
        usort($all_docs, function($a, $b) {
            return ($b['views'] + $b['downloads']) - ($a['views'] + $a['downloads']);
        });
    } elseif ($sort === 'recent') {
        usort($all_docs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    // Pagination
    $total = count($all_docs);
    $per_page = 20;
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    $results = array_slice($all_docs, $offset, $per_page);
    
    $search_results = [
        'results' => $results,
        'total' => $total,
        'total_pages' => $total_pages
    ];
} else {
    // Use regular search
    $search_results = searchDocuments($keyword, [], $sort, $page, 20, $user_id);
}

// Get education levels for filter dropdown
$education_levels = getEducationLevels();

$page_title = !empty($keyword) ? "Tìm kiếm: $keyword - DocShare" : "Tìm kiếm tài liệu - DocShare";
$current_page = 'search';
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/navbar.php'; ?>

<style>
    .search-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px 20px;
    }

    .search-header {
        margin-bottom: 30px;
    }

    .search-title {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
    }

    .search-meta {
        font-size: 14px;
        color: #666;
    }

    .search-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
    }

    /* Filters Sidebar */
    .filters-sidebar {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f3f4f6;
    }

    .filters-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }

    .filter-reset {
        background: none;
        border: none;
        color: #667eea;
        font-size: 13px;
        cursor: pointer;
        font-weight: 500;
    }

    .filter-reset:hover {
        text-decoration: underline;
    }

    .filter-group {
        margin-bottom: 16px;
    }

    .filter-group-title {
        font-size: 12px;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-cascade-arrow {
        text-align: center;
        color: #667eea;
        font-size: 12px;
        margin: 8px 0;
    }

    .filter-apply-btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 15px;
        transition: all 0.3s;
    }

    .filter-apply-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .active-filters {
        background: #f3f4f6;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 15px;
    }

    .active-filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #667eea;
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        margin: 2px;
    }

    /* Results Section */
    .results-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .results-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f3f4f6;
    }

    .sort-options {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .sort-label {
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }

    .sort-btn {
        padding: 8px 16px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
    }

    .sort-btn:hover {
        background: #f3f4f6;
    }

    .sort-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }

    /* Document Cards */
    .documents-grid {
        display: grid;
        gap: 20px;
    }

    .document-card {
        display: flex;
        gap: 20px;
        padding: 20px;
        background: #f9fafb;
        border-radius: 10px;
        transition: all 0.3s;
        border: 1px solid #e5e7eb;
    }

    .document-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background: white;
    }

    .document-thumbnail {
        width: 120px;
        height: 160px;
        background: #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
    }

    .document-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .document-icon {
        width: 120px;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f3f4f6;
        border-radius: 8px;
        font-size: 48px;
        flex-shrink: 0;
    }

    .document-info {
        flex: 1;
        min-width: 0;
    }

    .document-title {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 8px;
        text-decoration: none;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .document-title:hover {
        color: #667eea;
    }

    .document-category-path {
        font-size: 12px;
        color: #667eea;
        margin-bottom: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .document-category-path span {
        background: #ede9fe;
        padding: 2px 8px;
        border-radius: 4px;
    }

    .document-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 10px;
    }

    .document-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .document-description {
        font-size: 14px;
        color: #4b5563;
        line-height: 1.6;
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .document-stats {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #6b7280;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 30px;
    }

    .pagination a,
    .pagination span {
        padding: 8px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        text-decoration: none;
        color: #374151;
        font-size: 14px;
        transition: all 0.2s;
    }

    .pagination a:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .pagination .active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }

    /* No Results */
    .no-results {
        text-align: center;
        padding: 60px 20px;
    }

    .no-results-icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .no-results-title {
        font-size: 24px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
    }

    .no-results-text {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 20px;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .search-layout {
            grid-template-columns: 1fr;
        }

        .filters-sidebar {
            position: static;
        }

        .results-toolbar {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .sort-options {
            width: 100%;
            flex-wrap: wrap;
        }

        .document-card {
            flex-direction: column;
            gap: 15px;
        }

        .document-thumbnail,
        .document-icon {
            width: 100%;
            height: 180px;
        }
    }
</style>

<div class="search-container">
    <div class="search-header">
        <h1 class="search-title">
            <?php if (!empty($keyword)): ?>
                Kết quả tìm kiếm: "<?= htmlspecialchars($keyword) ?>"
            <?php else: ?>
                Tài liệu nổi bật
            <?php endif; ?>
        </h1>
        <div class="search-meta">
            <?php if ($search_results['total'] > 0): ?>
                Tìm thấy <strong><?= number_format($search_results['total']) ?></strong> tài liệu
                <?php if ($search_results['total_pages'] > 1): ?>
                    - Trang <?= $page ?>/<?= $search_results['total_pages'] ?>
                <?php endif; ?>
            <?php else: ?>
                Không tìm thấy kết quả
            <?php endif; ?>
        </div>
    </div>

    <div class="search-layout">
        <!-- Filters Sidebar -->
        <aside class="filters-sidebar">
            <div class="filters-header">
                <h2 class="filters-title">🔍 Lọc kết quả</h2>
                <?php if (!empty($filters)): ?>
                    <button class="filter-reset" onclick="resetFilters()">Xóa bộ lọc</button>
                <?php endif; ?>
            </div>

            <form method="GET" action="search.php" id="filterForm">
                <input type="hidden" name="q" value="<?= htmlspecialchars($keyword) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

                <!-- Cấp học -->
                <div class="filter-group">
                    <div class="filter-group-title">🎓 Cấp học</div>
                    <select name="education_level" id="filter_education_level" class="select select-bordered select-sm w-full">
                        <option value="">-- Tất cả cấp học --</option>
                        <?php foreach ($education_levels as $level): ?>
                            <option value="<?= $level['code'] ?>" <?= $category_filters['education_level'] === $level['code'] ? 'selected' : '' ?>>
                                <?= $level['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lớp (for phổ thông) -->
                <div class="filter-group hidden" id="filter_grade_container">
                    <div class="filter-cascade-arrow">↓</div>
                    <div class="filter-group-title">📚 Lớp</div>
                    <select name="grade_id" id="filter_grade_id" class="select select-bordered select-sm w-full">
                        <option value="">-- Tất cả lớp --</option>
                    </select>
                </div>

                <!-- Môn học (for phổ thông) -->
                <div class="filter-group hidden" id="filter_subject_container">
                    <div class="filter-cascade-arrow">↓</div>
                    <div class="filter-group-title">📖 Môn học</div>
                    <select name="subject_code" id="filter_subject_code" class="select select-bordered select-sm w-full">
                        <option value="">-- Tất cả môn --</option>
                    </select>
                </div>

                <!-- Nhóm ngành (for đại học) -->
                <div class="filter-group hidden" id="filter_major_group_container">
                    <div class="filter-cascade-arrow">↓</div>
                    <div class="filter-group-title">🎯 Nhóm ngành</div>
                    <select name="major_group_id" id="filter_major_group_id" class="select select-bordered select-sm w-full">
                        <option value="">-- Tất cả nhóm ngành --</option>
                    </select>
                </div>

                <!-- Ngành học (for đại học) -->
                <div class="filter-group hidden" id="filter_major_container">
                    <div class="filter-cascade-arrow">↓</div>
                    <div class="filter-group-title">📋 Ngành học</div>
                    <select name="major_code" id="filter_major_code" class="select select-bordered select-sm w-full">
                        <option value="">-- Tất cả ngành --</option>
                    </select>
                </div>

                <!-- Loại tài liệu -->
                <div class="filter-group hidden" id="filter_doc_type_container">
                    <div class="filter-cascade-arrow">↓</div>
                    <div class="filter-group-title">📄 Loại tài liệu</div>
                    <select name="doc_type_code" id="filter_doc_type_code" class="select select-bordered select-sm w-full">
                        <option value="">-- Tất cả loại --</option>
                    </select>
                </div>

                <button type="submit" class="filter-apply-btn">
                    🔍 Tìm kiếm
                </button>
            </form>
        </aside>

        <!-- Results Section -->
        <main class="results-section">
            <?php if (!empty($search_results['results'])): ?>
                <div class="results-toolbar">
                    <div class="sort-options">
                        <span class="sort-label">Sắp xếp:</span>
                        <button class="sort-btn <?= $sort === 'relevance' ? 'active' : '' ?>" onclick="changeSort('relevance')">
                            Phù hợp nhất
                        </button>
                        <button class="sort-btn <?= $sort === 'popular' ? 'active' : '' ?>" onclick="changeSort('popular')">
                            Phổ biến
                        </button>
                        <button class="sort-btn <?= $sort === 'recent' ? 'active' : '' ?>" onclick="changeSort('recent')">
                            Mới nhất
                        </button>
                    </div>
                </div>

                <div class="documents-grid">
                    <?php foreach ($search_results['results'] as $doc): 
                        $thumbnail = $doc['thumbnail'] ?? null;
                        $total_pages = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : null;
                        
                        // Get category info for this document
                        $doc_category = getDocumentCategoryWithNames($doc['id']);
                    ?>
                        <div class="document-card">
                            <?php if ($thumbnail && file_exists($thumbnail)): ?>
                                <div class="document-thumbnail">
                                    <img src="<?= htmlspecialchars($thumbnail) ?>" alt="Thumbnail">
                                    <?php if ($total_pages): ?>
                                        <span class="badge badge-primary" style="position: absolute; bottom: 8px; right: 8px; font-size: 10px;"><?= $total_pages ?> trang</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="document-icon">📄</div>
                            <?php endif; ?>
                            <div class="document-info">
                                <a href="view.php?id=<?= $doc['id'] ?>" class="document-title">
                                    <?= htmlspecialchars($doc['original_name']) ?>
                                </a>
                                
                                <?php if ($doc_category): ?>
                                    <div class="document-category-path">
                                        <span><?= htmlspecialchars($doc_category['education_level_name']) ?></span>
                                        <?php if (isset($doc_category['grade_name'])): ?>
                                            <span><?= htmlspecialchars($doc_category['grade_name']) ?></span>
                                            <span><?= htmlspecialchars($doc_category['subject_name']) ?></span>
                                        <?php elseif (isset($doc_category['major_name'])): ?>
                                            <span><?= htmlspecialchars($doc_category['major_group_name']) ?></span>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($doc_category['doc_type_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="document-meta">
                                    <span>👤 <?= htmlspecialchars($doc['username']) ?></span>
                                    <span>📅 <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                                    <?php 
                                    $points = $doc['points'] ?? 0;
                                    if ($points > 0): ?>
                                        <span>💰 <?= number_format($points) ?> điểm</span>
                                    <?php else: ?>
                                        <span style="color: #10b981;">🎁 Miễn phí</span>
                                    <?php endif; ?>
                                    <?php if ($total_pages): ?>
                                        <span>📄 <?= $total_pages ?> trang</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($doc['description'])): ?>
                                    <div class="document-description">
                                        <?= htmlspecialchars($doc['description']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="document-stats">
                                    <span class="stat-item">👁️ <?= number_format($doc['views'] ?? 0) ?> lượt xem</span>
                                    <span class="stat-item">⬇️ <?= number_format($doc['downloads'] ?? 0) ?> tải xuống</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($search_results['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                ← Trước
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($search_results['total_pages'], $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $search_results['total_pages']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                Sau →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- No Results -->
                <div class="no-results">
                    <div class="no-results-icon">🔍</div>
                    <h2 class="no-results-title">Không tìm thấy kết quả</h2>
                    <p class="no-results-text">
                        <?php if (!empty($keyword)): ?>
                            Không tìm thấy tài liệu nào cho từ khóa "<strong><?= htmlspecialchars($keyword) ?></strong>"
                        <?php else: ?>
                            Hãy thử tìm kiếm với từ khóa khác hoặc thay đổi bộ lọc
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    // Current filter values from PHP
    const currentFilters = <?= json_encode($category_filters) ?>;
    
    // Elements
    const educationLevelSelect = document.getElementById('filter_education_level');
    const gradeContainer = document.getElementById('filter_grade_container');
    const gradeSelect = document.getElementById('filter_grade_id');
    const subjectContainer = document.getElementById('filter_subject_container');
    const subjectSelect = document.getElementById('filter_subject_code');
    const majorGroupContainer = document.getElementById('filter_major_group_container');
    const majorGroupSelect = document.getElementById('filter_major_group_id');
    const majorContainer = document.getElementById('filter_major_container');
    const majorSelect = document.getElementById('filter_major_code');
    const docTypeContainer = document.getElementById('filter_doc_type_container');
    const docTypeSelect = document.getElementById('filter_doc_type_code');
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', async function() {
        educationLevelSelect.addEventListener('change', onEducationLevelChange);
        gradeSelect.addEventListener('change', onGradeChange);
        majorGroupSelect.addEventListener('change', onMajorGroupChange);
        
        // Load cascade from current filters
        if (currentFilters.education_level) {
            await loadCascadeFromFilters();
        }
    });
    
    async function loadCascadeFromFilters() {
        const level = currentFilters.education_level;
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
        
        if (isPhoThong) {
            showElement('filter_grade_container');
            await loadGrades(level);
            if (currentFilters.grade_id) {
                gradeSelect.value = currentFilters.grade_id;
                showElement('filter_subject_container');
                await loadSubjects(level, currentFilters.grade_id);
                if (currentFilters.subject_code) {
                    subjectSelect.value = currentFilters.subject_code;
                }
            }
        } else if (level === 'dai_hoc') {
            showElement('filter_major_group_container');
            await loadMajorGroups();
            if (currentFilters.major_group_id) {
                majorGroupSelect.value = currentFilters.major_group_id;
                showElement('filter_major_container');
                await loadMajors(currentFilters.major_group_id);
                if (currentFilters.major_code) {
                    majorSelect.value = currentFilters.major_code;
                }
            }
        }
        
        if (level) {
            showElement('filter_doc_type_container');
            await loadDocTypes(level);
            if (currentFilters.doc_type_code) {
                docTypeSelect.value = currentFilters.doc_type_code;
            }
        }
    }
    
    async function onEducationLevelChange(e) {
        const level = e.target.value;
        
        hideElement('filter_grade_container');
        hideElement('filter_subject_container');
        hideElement('filter_major_group_container');
        hideElement('filter_major_container');
        hideElement('filter_doc_type_container');
        
        // Reset values
        gradeSelect.value = '';
        subjectSelect.value = '';
        majorGroupSelect.value = '';
        majorSelect.value = '';
        docTypeSelect.value = '';
        
        if (!level) return;
        
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
        
        if (isPhoThong) {
            showElement('filter_grade_container');
            await loadGrades(level);
        } else {
            showElement('filter_major_group_container');
            await loadMajorGroups();
        }
        
        showElement('filter_doc_type_container');
        await loadDocTypes(level);
    }
    
    async function onGradeChange(e) {
        const gradeId = e.target.value;
        hideElement('filter_subject_container');
        subjectSelect.value = '';
        
        if (!gradeId) return;
        
        showElement('filter_subject_container');
        await loadSubjects(educationLevelSelect.value, gradeId);
    }
    
    async function onMajorGroupChange(e) {
        const groupId = e.target.value;
        hideElement('filter_major_container');
        majorSelect.value = '';
        
        if (!groupId) return;
        
        showElement('filter_major_container');
        await loadMajors(groupId);
    }
    
    // API loaders
    async function loadGrades(level) {
        try {
            const response = await fetch(`/handler/categories_api.php?action=grades&level=${level}`);
            const data = await response.json();
            if (data.success) {
                gradeSelect.innerHTML = '<option value="">-- Tất cả lớp --</option>' + 
                    data.data.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
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
                subjectSelect.innerHTML = '<option value="">-- Tất cả môn --</option>' + 
                    data.data.map(s => `<option value="${s.code}">${s.name}</option>`).join('');
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
                majorGroupSelect.innerHTML = '<option value="">-- Tất cả nhóm ngành --</option>' + 
                    data.data.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
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
                majorSelect.innerHTML = '<option value="">-- Tất cả ngành --</option>' + 
                    data.data.map(m => `<option value="${m.code}">${m.name}</option>`).join('');
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
                docTypeSelect.innerHTML = '<option value="">-- Tất cả loại --</option>' + 
                    data.data.map(d => `<option value="${d.code}">${d.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading doc types:', error);
        }
    }
    
    // Helpers
    function showElement(id) {
        document.getElementById(id)?.classList.remove('hidden');
    }
    
    function hideElement(id) {
        document.getElementById(id)?.classList.add('hidden');
    }
    
    function changeSort(sortType) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', sortType);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    function resetFilters() {
        window.location.href = 'search.php' + (currentFilters.q ? '?q=' + encodeURIComponent(currentFilters.q) : '');
    }
</script>

<?php include 'includes/footer.php'; ?>
