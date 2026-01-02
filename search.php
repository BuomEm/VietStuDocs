<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/search.php';
require_once 'config/categories.php';
require_once 'config/premium.php';

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
        font-weight: 800;
        color: oklch(var(--bc));
        margin-bottom: 10px;
        letter-spacing: -0.5px;
    }

    .search-meta {
        font-size: 14px;
        color: oklch(var(--bc) / 0.7);
    }

    .search-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 30px;
    }

    /* Filters Sidebar */
    .filters-sidebar {
        background: oklch(var(--b1));
        border-radius: var(--rounded-box);
        padding: 24px;
        border: 1px solid oklch(var(--b3));
        box-shadow: 0 4px 20px -10px rgba(0,0,0,0.05);
        height: fit-content;
        position: sticky;
        top: 100px;
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid oklch(var(--b3));
    }

    .filters-title {
        font-size: 18px;
        font-weight: 700;
        color: oklch(var(--bc));
    }

    .filter-reset {
        background: none;
        border: none;
        color: oklch(var(--p));
        font-size: 13px;
        cursor: pointer;
        font-weight: 600;
    }

    .filter-reset:hover {
        text-decoration: underline;
    }

    .filter-group {
        margin-bottom: 20px;
    }

    .filter-group-title {
        font-size: 11px;
        font-weight: 800;
        color: oklch(var(--p));
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .filter-cascade-arrow {
        text-align: center;
        color: oklch(var(--p));
        opacity: 0.5;
        font-size: 12px;
        margin: 8px 0;
    }

    .filter-apply-btn {
        width: 100%;
        margin-top: 10px;
    }

    /* Results Section */
    .results-section {
        background: oklch(var(--b1));
        border-radius: var(--rounded-box);
        padding: 24px;
        border: 1px solid oklch(var(--b3));
        box-shadow: 0 4px 20px -10px rgba(0,0,0,0.05);
    }

    .results-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid oklch(var(--b3));
    }

    .sort-options {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .sort-label {
        font-size: 13px;
        color: oklch(var(--bc) / 0.6);
        font-weight: 600;
        margin-right: 4px;
    }

    /* Document Cards */
    .documents-grid {
        display: grid;
        gap: 20px;
    }

    .document-card {
        display: flex;
        gap: 24px;
        padding: 20px;
        background: oklch(var(--b2) / 0.3);
        border-radius: var(--rounded-box);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid oklch(var(--b3));
    }

    .document-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -10px rgba(0,0,0,0.1);
        background: oklch(var(--b1));
        border-color: oklch(var(--p) / 0.3);
    }

    .document-thumbnail {
        width: 140px;
        height: 190px;
        background: oklch(var(--b3));
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .document-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .document-icon {
        width: 140px;
        height: 190px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: oklch(var(--b3) / 0.5);
        border-radius: 8px;
        font-size: 56px;
        flex-shrink: 0;
        color: oklch(var(--p) / 0.3);
    }

    .document-info {
        flex: 1;
        min-width: 0;
        padding-top: 4px;
    }

    .document-title {
        font-size: 20px;
        font-weight: 700;
        color: oklch(var(--bc));
        margin-bottom: 8px;
        text-decoration: none;
        display: block;
        line-height: 1.3;
        transition: color 0.2s;
    }

    .document-title:hover {
        color: oklch(var(--p));
    }

    .document-category-path {
        font-size: 11px;
        font-weight: 700;
        color: oklch(var(--p));
        margin-bottom: 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .document-category-path span {
        background: oklch(var(--p) / 0.1);
        padding: 2px 10px;
        border-radius: 20px;
        border: 1px solid oklch(var(--p) / 0.1);
    }

    .document-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        font-size: 13px;
        color: oklch(var(--bc) / 0.7);
        margin-bottom: 16px;
    }

    .document-meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .document-description {
        font-size: 14px;
        color: oklch(var(--bc) / 0.8);
        line-height: 1.6;
        margin-bottom: 16px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .document-stats {
        display: flex;
        gap: 20px;
        font-size: 12px;
        font-weight: 600;
        color: oklch(var(--bc) / 0.5);
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Mobile Responsive */
    @media (max-width: 1024px) {
        .search-layout {
            grid-template-columns: 1fr;
        }

        .filters-sidebar {
            position: static;
            margin-bottom: 20px;
        }
    }

    @media (max-width: 640px) {
        .document-card {
            flex-direction: column;
            gap: 16px;
        }

        .document-thumbnail,
        .document-icon {
            width: 100%;
            height: 200px;
        }
        
        .results-toolbar {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .sort-options {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 8px;
        }
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    .animate-float {
        animation: float 3s ease-in-out infinite;
    }
</style>

<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col min-h-screen">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-grow p-4 lg:p-6 pb-20">
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
                <aside class="filters-sidebar">
            <div class="filters-header">
                <h2 class="filters-title flex items-center gap-2">
                    <i class="fa-solid fa-filter text-primary"></i> Lọc kết quả
                </h2>
                <?php if (!empty($filters)): ?>
                    <button class="filter-reset flex items-center gap-1 text-primary hover:opacity-80 transition-opacity" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i> Xóa lọc
                    </button>
                <?php endif; ?>
            </div>

            <form method="GET" action="search.php" id="filterForm">
                <input type="hidden" name="q" value="<?= htmlspecialchars($keyword) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

                <!-- Cấp học -->
                <div class="filter-group">
                    <div class="filter-group-title flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap"></i> Cấp học
                    </div>
                    <select name="education_level" id="filter_education_level" class="select select-bordered select-sm w-full bg-base-100">
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
                    <div class="filter-group-title flex items-center gap-2">
                        <i class="fa-solid fa-layer-group"></i> Lớp
                    </div>
                    <select name="grade_id" id="filter_grade_id" class="select select-bordered select-sm w-full bg-base-100">
                        <option value="">-- Tất cả lớp --</option>
                    </select>
                </div>

                <!-- Môn học (for phổ thông) -->
                <div class="filter-group hidden" id="filter_subject_container">
                    <div class="filter-group-title flex items-center gap-2">
                        <i class="fa-solid fa-book"></i> Môn học
                    </div>
                    <select name="subject_code" id="filter_subject_code" class="select select-bordered select-sm w-full bg-base-100">
                        <option value="">-- Tất cả môn --</option>
                    </select>
                </div>

                <!-- Nhóm ngành (for đại học) -->
                <div class="filter-group hidden" id="filter_major_group_container">
                    <div class="filter-group-title flex items-center gap-2">
                        <i class="fa-solid fa-users-rectangle"></i> Nhóm ngành
                    </div>
                    <select name="major_group_id" id="filter_major_group_id" class="select select-bordered select-sm w-full bg-base-100">
                        <option value="">-- Tất cả nhóm ngành --</option>
                    </select>
                </div>

                <!-- Ngành học (for đại học) -->
                <div class="filter-group hidden" id="filter_major_container">
                    <div class="filter-group-title flex items-center gap-2">
                        <i class="fa-solid fa-briefcase"></i> Ngành học
                    </div>
                    <select name="major_code" id="filter_major_code" class="select select-bordered select-sm w-full bg-base-100">
                        <option value="">-- Tất cả ngành --</option>
                    </select>
                </div>

                <!-- Loại tài liệu -->
                <div class="filter-group hidden" id="filter_doc_type_container">
                    <div class="filter-group-title flex items-center gap-2">
                        <i class="fa-solid fa-file-contract"></i> Loại tài liệu
                    </div>
                    <select name="doc_type_code" id="filter_doc_type_code" class="select select-bordered select-sm w-full bg-base-100">
                        <option value="">-- Tất cả loại --</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block shadow-lg mt-4">
                    <i class="fa-solid fa-magnifying-glass"></i> Tìm kiếm
                </button>
            </form>
        </aside>

        <!-- Results Section -->
        <main class="results-section">
            <?php if (!empty($search_results['results'])): ?>
                <div class="results-toolbar">
                    <div class="sort-options">
                        <span class="sort-label text-base-content/50 uppercase tracking-widest text-[10px] mr-2">Sắp xếp:</span>
                        <div class="join shadow-sm border border-base-300">
                            <button class="btn btn-sm join-item <?= $sort === 'relevance' ? 'btn-primary' : 'btn-ghost bg-base-100' ?>" onclick="changeSort('relevance')">
                                <i class="fa-solid fa-bolt-lightning mr-1 opacity-70"></i> Phù hợp nhất
                            </button>
                            <button class="btn btn-sm join-item <?= $sort === 'popular' ? 'btn-primary' : 'btn-ghost bg-base-100' ?>" onclick="changeSort('popular')">
                                <i class="fa-solid fa-fire mr-1 opacity-70"></i> Phổ biến
                            </button>
                            <button class="btn btn-sm join-item <?= $sort === 'recent' ? 'btn-primary' : 'btn-ghost bg-base-100' ?>" onclick="changeSort('recent')">
                                <i class="fa-solid fa-clock mr-1 opacity-70"></i> Mới nhất
                            </button>
                        </div>
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
                                <div class="document-icon"><i class="fa-solid fa-file-lines text-primary/30"></i></div>
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
                                
                                <div class="document-meta" style="flex-wrap: wrap; gap: 10px;">
                                    <span class="flex items-center gap-1.5 bg-base-100 px-2 py-0.5 rounded-full border border-base-200">
                                        <div class="avatar">
                                            <div class="w-4 h-4 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center">
                                                <?php if(!empty($doc['avatar']) && file_exists('uploads/avatars/' . $doc['avatar'])): ?>
                                                    <img src="uploads/avatars/<?= $doc['avatar'] ?>" alt="Avatar" />
                                                <?php else: ?>
                                                    <i class="fa-solid fa-user text-[6px] text-primary"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="text-[11px] font-medium text-base-content/70"><?= htmlspecialchars($doc['username']) ?></span>
                                    </span>
                                    <span class="flex items-center gap-1 text-[11px] text-base-content/50"><i class="fa-solid fa-calendar-days text-[10px]"></i> <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                                    <?php 
                                    $points = $doc['points'] ?? 0;
                                    if ($points > 0): ?>
                                        <span><i class="fa-solid fa-coins mr-1 text-xs"></i> <?= number_format($points) ?> điểm</span>
                                    <?php else: ?>
                                         <span style="color: #10b981;"><i class="fa-solid fa-gift mr-1 text-xs"></i> Miễn phí</span>
                                    <?php endif; ?>
                                    <?php if ($total_pages): ?>
                                        <span><i class="fa-solid fa-file-lines mr-1 text-xs"></i> <?= $total_pages ?> trang</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($doc['description'])): ?>
                                    <div class="document-description">
                                        <?= htmlspecialchars($doc['description']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="document-stats">
                                    <span class="stat-item"><i class="fa-solid fa-eye mr-1 text-xs"></i> <?= number_format($doc['views'] ?? 0) ?> lượt xem</span>
                                    <span class="stat-item"><i class="fa-solid fa-download mr-1 text-xs"></i> <?= number_format($doc['downloads'] ?? 0) ?> tải xuống</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($search_results['total_pages'] > 1): ?>
                    <div class="flex justify-center mt-12">
                        <div class="join shadow-sm border border-base-300">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="join-item btn btn-sm bg-base-100 border-r border-base-300">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($search_results['total_pages'], $page + 2); $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="join-item btn btn-sm <?= $i == $page ? 'btn-primary' : 'bg-base-100 hover:bg-base-200' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $search_results['total_pages']): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="join-item btn btn-sm bg-base-100 border-l border-base-300">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- No Results Premium View -->
                <div class="no-results flex flex-col items-center justify-center py-20 px-4 text-center">
                    <div class="relative mb-8 animate-float">
                        <div class="w-32 h-32 rounded-3xl bg-primary/5 flex items-center justify-center rotate-12">
                            <i class="fa-solid fa-box-open text-6xl text-primary/20 -rotate-12"></i>
                        </div>
                        <div class="absolute -bottom-4 -right-4 w-14 h-14 rounded-2xl bg-base-100 flex items-center justify-center shadow-xl border border-base-200 text-primary">
                            <i class="fa-solid fa-magnifying-glass text-xl"></i>
                        </div>
                    </div>
                    
                    <h2 class="text-3xl font-extrabold text-base-content mb-3 tracking-tight">Hic! Không tìm thấy kết quả</h2>
                    <p class="text-base-content/60 max-w-md mx-auto mb-10 leading-relaxed text-lg">
                        <?php if (!empty($keyword)): ?>
                            Rất tiếc, chúng mình không tìm thấy tài liệu nào khớp với từ khóa <span class="text-primary font-bold">"<?= htmlspecialchars($keyword) ?>"</span>.
                        <?php else: ?>
                            Có vẻ như các bộ lọc hiện tại của bạn không có tài liệu nào phù hợp với các tiêu chí tìm kiếm.
                        <?php endif; ?>
                    </p>
                    
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <button class="btn btn-primary btn-wide shadow-xl shadow-primary/20 h-14" onclick="resetFilters()">
                            <i class="fa-solid fa-rotate-left mr-2"></i> Thử lại từ đầu
                        </button>
                        <a href="dashboard.php" class="btn btn-ghost btn-wide h-14 border-base-300 hover:bg-base-200 transition-all">
                            <i class="fa-solid fa-house mr-2"></i> Về Dashboard
                        </a>
                    </div>
                    
                    <div class="mt-16 pt-8 border-t border-base-300/50 w-full max-w-lg">
                        <p class="text-xs uppercase tracking-widest text-base-content/40 font-bold mb-4 italic">Gợi ý cho bạn:</p>
                        <ul class="text-sm text-base-content/60 space-y-2">
                            <li>• Kiểm tra lại lỗi chính tả của từ khóa</li>
                            <li>• Thử sử dụng các từ khóa ngắn gọn hơn</li>
                            <li>• Tháo bớt các bộ lọc để có nhiều kết quả hơn</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div> <!-- Close search-layout -->
    </div> <!-- Close search-container -->
</main>
            
            <?php include 'includes/footer.php'; ?>
        </div> <!-- Close drawer-content -->
    </div> <!-- Close drawer from sidebar.php -->

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
