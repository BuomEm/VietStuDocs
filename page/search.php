<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/search.php';
require_once '../config/categories.php';
require_once '../config/premium.php';

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
<?php include '../includes/head.php'; ?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .search-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    .search-header {
        margin-bottom: 40px;
        position: relative;
    }

    .search-title {
        font-size: 2.5rem;
        font-weight: 900;
        color: oklch(var(--bc));
        letter-spacing: -0.05em;
        line-height: 1.1;
        margin-bottom: 12px;
    }

    .search-meta {
        font-size: 0.875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--bc) / 0.4);
    }

    .search-layout {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 40px;
        align-items: start;
    }

    /* Filters Sidebar - Ultra Premium */
    .filters-sidebar {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 2.5rem;
        padding: 32px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 100px;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        z-index: 10;
    }

    .filters-title {
        font-size: 1.25rem;
        font-weight: 900;
        color: oklch(var(--bc));
        letter-spacing: -0.02em;
        margin-bottom: 8px;
    }

    .filter-reset {
        text-transform: uppercase;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.1em;
        color: oklch(var(--p));
        padding: 8px 16px;
        background: oklch(var(--p) / 0.05);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .filter-reset:hover {
        background: oklch(var(--p) / 0.1);
        transform: translateY(-1px);
    }

    .filter-group {
        margin-bottom: 24px;
        padding-bottom: 24px;
        border-bottom: 1px solid oklch(var(--bc) / 0.05);
    }

    .filter-group:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .filter-group-title {
        font-size: 0.7rem;
        font-weight: 900;
        color: oklch(var(--bc) / 0.3);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.15em;
    }

    .filter-select {
        width: 100%;
        height: 48px;
        background-color: oklch(var(--b2) / 0.5);
        border: 1px solid oklch(var(--bc) / 0.1);
        border-radius: 1rem;
        padding: 0 16px;
        font-weight: 700;
        font-size: 0.875rem;
        color: oklch(var(--bc));
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .filter-select:focus {
        outline: none;
        border-color: oklch(var(--p));
        background-color: oklch(var(--b1));
        box-shadow: 0 0 0 4px oklch(var(--p) / 0.1);
    }

    .btn-apply {
        width: 100%;
        height: 56px;
        background: oklch(var(--p));
        color: oklch(var(--pc));
        border: none;
        border-radius: 1rem;
        font-weight: 900;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 10px 15px -3px oklch(var(--p) / 0.3);
        transition: all 0.3s ease;
        margin-top: 16px;
    }

    .btn-apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px oklch(var(--p) / 0.4);
    }

    /* Results Sections */
    .results-section {
        min-width: 0;
    }

    .results-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .sort-group {
        display: flex;
        align-items: center;
        gap: 8px;
        background-color: oklch(var(--b2) / 0.5);
        padding: 6px;
        border-radius: 1rem;
        border: 1px solid oklch(var(--bc) / 0.05);
    }

    .sort-btn {
        height: 40px;
        padding: 0 20px;
        border: none;
        background: transparent;
        border-radius: 0.75rem;
        font-weight: 900;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: oklch(var(--bc) / 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .sort-btn.active {
        background: oklch(var(--p));
        color: oklch(var(--pc));
    }

    /* Document Cards - Premium List Style */
    .documents-grid {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .document-card {
        position: relative;
        display: flex;
        gap: 32px;
        padding: 24px;
        background-color: oklch(var(--b1));
        border-radius: 2.5rem;
        border: 1px solid oklch(var(--bc) / 0.05);
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    }

    .document-card:hover {
        transform: translateY(-4px);
        border-color: oklch(var(--p) / 0.2);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
    }

    .document-thumbnail {
        width: 160px;
        height: 220px;
        border-radius: 2rem;
        background-color: oklch(var(--b2));
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
        border: 1px solid oklch(var(--bc) / 0.05);
        z-index: 1;
    }

    .document-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.7s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .document-card:hover .document-thumbnail img {
        transform: scale(1.1);
    }

    .document-badge-pages {
        position: absolute;
        bottom: 12px;
        left: 12px;
        right: 12px;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        color: white;
        font-size: 10px;
        font-weight: 900;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        z-index: 2;
    }

    .document-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 4px 0;
        min-width: 0;
    }

    .document-title {
        font-size: 1.5rem;
        font-weight: 900;
        color: oklch(var(--bc));
        line-height: 1.2;
        margin-bottom: 12px;
        transition: color 0.3s ease;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .document-card:hover .document-title {
        color: oklch(var(--p));
    }

    .document-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    .tag-vsd {
        padding: 4px 12px;
        border-radius: 2rem;
        background: oklch(var(--p) / 0.05);
        border: 1px solid oklch(var(--p) / 0.1);
        font-size: 10px;
        font-weight: 900;
        color: oklch(var(--p));
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .document-meta-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 12px;
    }

    .meta-vsd {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 900;
        color: oklch(var(--bc) / 0.4);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .user-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        background-color: oklch(var(--b2) / 0.6);
        padding: 4px 10px;
        border-radius: 0.75rem;
        border: 1px solid oklch(var(--bc) / 0.05);
    }

    .user-avatar-small {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        overflow: hidden;
        background-color: oklch(var(--p) / 0.1);
    }

    .user-avatar-small img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .document-description {
        font-size: 0.875rem;
        font-weight: 500;
        color: oklch(var(--bc) / 0.5);
        line-height: 1.5;
        margin-bottom: 16px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .document-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 16px;
        border-top: 1px solid oklch(var(--bc) / 0.05);
        margin-top: auto;
    }

    .price-tag {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 0.75rem;
        font-weight: 900;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .price-free {
        background-color: oklch(var(--s) / 0.1);
        color: oklch(var(--s));
    }

    .price-points {
        background-color: oklch(var(--wa) / 0.1);
        color: oklch(var(--wa));
    }

    .stats-vsd {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stat-vsd-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 900;
        color: oklch(var(--bc) / 0.3);
        text-transform: uppercase;
    }

    /* Mobile Enhancements */
    @media (max-width: 1024px) {
        .search-layout {
            grid-template-columns: 1fr;
        }
        .filters-sidebar {
            position: static;
        }
    }

    @media (max-width: 640px) {
        .document-card {
            flex-direction: column;
            gap: 20px;
        }
        .document-thumbnail {
            width: 100%;
            height: 240px;
        }
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
</style>

<?php include '../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col min-h-screen">
    <?php include '../includes/navbar.php'; ?>
    
    <main class="flex-grow p-4 lg:p-6 pb-20">
        <div class="search-container">
    <div class="search-header">
        <div class="search-meta">
            <?php if ($search_results['total'] > 0): ?>
                ĐÃ TÌM THẤY <?= number_format($search_results['total']) ?> TÀI LIỆU PHÙ HỢP
            <?php else: ?>
                KHÔNG CÓ KẾT QUẢ NÀO
            <?php endif; ?>
        </div>
        <h1 class="search-title">
            <?php if (!empty($keyword)): ?>
                Kết quả cho <span class="text-primary">"<?= htmlspecialchars($keyword) ?>"</span>
            <?php else: ?>
                Tài liệu <span class="text-primary">Nổi bật</span>
            <?php endif; ?>
        </h1>
    </div>

    <div class="search-layout">
                <aside class="filters-sidebar">
                <h2 class="filters-title">BỘ LỌC TÌM KIẾM</h2>
                <?php if (!empty($filters)): ?>
                    <button class="filter-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left mr-1"></i> XÓA TẤT CẢ
                    </button>
                <?php endif; ?>

            <form method="GET" action="search.php" id="filterForm">
                <input type="hidden" name="q" value="<?= htmlspecialchars($keyword) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

                <!-- Cấp học -->
                <div class="filter-group">
                    <div class="filter-group-title">Cấp học</div>
                    <select name="education_level" id="filter_education_level" class="filter-select">
                        <option value="">Tất cả cấp học</option>
                        <?php foreach ($education_levels as $level): ?>
                            <option value="<?= $level['code'] ?>" <?= $category_filters['education_level'] === $level['code'] ? 'selected' : '' ?>>
                                <?= $level['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lớp (for phổ thông) -->
                <div class="filter-group hidden" id="filter_grade_container">
                    <div class="filter-group-title">Lớp</div>
                    <select name="grade_id" id="filter_grade_id" class="filter-select">
                        <option value="">Tất cả lớp</option>
                    </select>
                </div>

                <!-- Môn học (for phổ thông) -->
                <div class="filter-group hidden" id="filter_subject_container">
                    <div class="filter-group-title">Môn học</div>
                    <select name="subject_code" id="filter_subject_code" class="filter-select">
                        <option value="">Tất cả môn</option>
                    </select>
                </div>

                <!-- Nhóm ngành (for đại học) -->
                <div class="filter-group hidden" id="filter_major_group_container">
                    <div class="filter-group-title">Nhóm ngành</div>
                    <select name="major_group_id" id="filter_major_group_id" class="filter-select">
                        <option value="">Tất cả nhóm ngành</option>
                    </select>
                </div>

                <!-- Ngành học (for đại học) -->
                <div class="filter-group hidden" id="filter_major_container">
                    <div class="filter-group-title">Ngành học</div>
                    <select name="major_code" id="filter_major_code" class="filter-select">
                        <option value="">Tất cả ngành</option>
                    </select>
                </div>

                <!-- Loại tài liệu -->
                <div class="filter-group hidden" id="filter_doc_type_container">
                    <div class="filter-group-title">Loại tài liệu</div>
                    <select name="doc_type_code" id="filter_doc_type_code" class="filter-select">
                        <option value="">Tất cả loại</option>
                    </select>
                </div>

                <button type="submit" class="btn-apply">
                    <i class="fa-solid fa-filter mr-2"></i> LỌC KẾT QUẢ
                </button>
            </form>
        </aside>

        <!-- Results Section -->
        <main class="results-section">
            <?php if (!empty($search_results['results'])): ?>
                <div class="results-toolbar">
                    <div class="text-sm font-black text-base-content/30 uppercase tracking-[0.2em]">KẾT QUẢ TÌM THẤY</div>
                    <div class="sort-group">
                        <button class="sort-btn <?= $sort === 'relevance' ? 'active' : '' ?>" onclick="changeSort('relevance')">PHÙ HỢP</button>
                        <button class="sort-btn <?= $sort === 'popular' ? 'active' : '' ?>" onclick="changeSort('popular')">PHỔ BIẾN</button>
                        <button class="sort-btn <?= $sort === 'recent' ? 'active' : '' ?>" onclick="changeSort('recent')">MỚI NHẤT</button>
                    </div>
                </div>

                <div class="documents-grid">
                    <?php foreach ($search_results['results'] as $doc): 
                        $thumbnail = $doc['thumbnail'] ?? null;
                        $total_pages = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : null;
                        $doc_category = getDocumentCategoryWithNames($doc['id']);
                        $points = $doc['points'] ?? 0;
                    ?>
                        <div class="document-card group">
                            <!-- Thumbnail -->
                            <div class="document-thumbnail">
                                <?php if ($thumbnail && file_exists($thumbnail)): ?>
                                    <img src="<?= htmlspecialchars($thumbnail) ?>" alt="Thumbnail">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-primary/20">
                                        <i class="fa-solid fa-file-invoice text-5xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($total_pages): ?>
                                    <div class="document-badge-pages">
                                        <i class="fa-solid fa-file-lines mr-2 opacity-50"></i>
                                        <?= $total_pages ?> TRANG
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content Info -->
                            <div class="document-info">
                                <div>
                                    <div class="document-tags">
                                        <?php if ($doc_category): ?>
                                            <span class="tag-vsd"><?= htmlspecialchars($doc_category['education_level_name']) ?></span>
                                            <span class="tag-vsd"><?= htmlspecialchars($doc_category['doc_type_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="view.php?id=<?= $doc['id'] ?>" class="document-title">
                                        <?= htmlspecialchars($doc['original_name']) ?>
                                    </a>

                                    <div class="document-meta-row">
                                        <div class="user-badge">
                                            <div class="user-avatar-small bg-primary/10 flex items-center justify-center">
                                                <?php if(!empty($doc['avatar']) && file_exists('../uploads/avatars/' . $doc['avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $doc['avatar'] ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-user text-[8px] text-primary"></i>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[10px] font-black uppercase text-base-content/60"><?= htmlspecialchars($doc['username']) ?></span>
                                        </div>
                                        <div class="meta-vsd">
                                            <i class="fa-solid fa-calendar-day"></i>
                                            <?= date('d M, Y', strtotime($doc['created_at'])) ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($doc['description'])): ?>
                                        <p class="document-description">
                                            <?= htmlspecialchars($doc['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Footer -->
                                <div class="document-footer">
                                    <div class="price-tag <?= $points > 0 ? 'price-points' : 'price-free' ?>">
                                        <?php if ($points > 0): ?>
                                            <i class="fa-solid fa-coins"></i>
                                            <?= number_format($points) ?> <span class="opacity-50">ĐIỂM</span>
                                        <?php else: ?>
                                            <i class="fa-solid fa-gift"></i>
                                            MIỄN PHÍ
                                        <?php endif; ?>
                                    </div>

                                    <div class="stats-vsd">
                                        <div class="stat-vsd-item">
                                            <i class="fa-solid fa-eye"></i>
                                            <?= number_format($doc['views'] ?? 0) ?>
                                        </div>
                                        <div class="stat-vsd-item">
                                            <i class="fa-solid fa-download"></i>
                                            <?= number_format($doc['downloads'] ?? 0) ?>
                                        </div>
                                    </div>
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
                <div class="flex flex-col items-center justify-center py-20 px-4 text-center">
                    <div class="relative mb-12" style="animation: float 3s ease-in-out infinite;">
                        <div class="w-40 h-40 rounded-[3rem] bg-primary/5 flex items-center justify-center rotate-12 border border-primary/10">
                            <i class="fa-solid fa-box-open text-7xl text-primary/20 -rotate-12"></i>
                        </div>
                        <div class="absolute -bottom-4 -right-4 w-16 h-16 rounded-2xl bg-base-100 flex items-center justify-center shadow-2xl border border-base-200 text-primary">
                            <i class="fa-solid fa-magnifying-glass text-2xl"></i>
                        </div>
                    </div>
                    
                    <h2 class="text-4xl font-black text-base-content mb-4 tracking-tight uppercase">Hic! Không tìm thấy</h2>
                    <p class="text-base-content/50 max-w-md mx-auto mb-12 font-medium leading-relaxed">
                        <?php if (!empty($keyword)): ?>
                            Rất tiếc, hệ thống không tìm thấy tài liệu nào khớp với từ khóa <span class="text-primary font-black">"<?= htmlspecialchars($keyword) ?>"</span>.
                        <?php else: ?>
                            Các bộ lọc hiện tại của bạn không có tài liệu nào phù hợp. Hãy thử thay đổi tiêu chí nhé!
                        <?php endif; ?>
                    </p>
                    
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                        <button class="btn btn-primary h-14 px-10 rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-primary/20" onclick="resetFilters()">
                            <i class="fa-solid fa-rotate-left mr-2"></i> THỬ LẠI
                        </button>
                        <a href="dashboard.php" class="btn btn-ghost h-14 px-10 rounded-2xl font-black uppercase tracking-widest border-base-300">
                            <i class="fa-solid fa-house mr-2"></i> DASHBOARD
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div> <!-- Close search-layout -->
    </div> <!-- Close search-container -->
</main>
            
            <?php include '../includes/footer.php'; ?>
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
