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

// Get category filters
$filters = [];
if (isset($_GET['filters']) && is_array($_GET['filters'])) {
    $filters = array_filter(array_map('intval', $_GET['filters']));
}

// Perform search
$search_results = searchDocuments($keyword, $filters, $sort, $page, 20, $user_id);

// Get all categories for filters
$all_categories = getAllCategoriesGrouped(true);

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
        grid-template-columns: 280px 1fr;
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
        margin-bottom: 20px;
    }

    .filter-group-title {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-group-icon {
        font-size: 16px;
    }

    .filter-options {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-option:hover {
        background: #f9fafb;
    }

    .filter-option input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .filter-option label {
        font-size: 13px;
        color: #4b5563;
        cursor: pointer;
        flex: 1;
    }

    .filter-apply-btn {
        width: 100%;
        padding: 10px;
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

    .document-icon {
        font-size: 48px;
        opacity: 0.7;
    }

    .document-info {
        flex: 1;
    }

    .document-title {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 8px;
        text-decoration: none;
        display: block;
    }

    .document-title:hover {
        color: #667eea;
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

    .no-results-suggestions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
        margin-top: 20px;
    }

    .suggestion-tag {
        padding: 8px 16px;
        background: #f3f4f6;
        border-radius: 20px;
        font-size: 13px;
        color: #4b5563;
        text-decoration: none;
        transition: all 0.2s;
    }

    .suggestion-tag:hover {
        background: #667eea;
        color: white;
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

        .document-icon {
            font-size: 36px;
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

                <?php
                $type_labels = [
                    'field' => ['icon' => '🎓', 'label' => 'Lĩnh vực'],
                    'subject' => ['icon' => '📚', 'label' => 'Môn học'],
                    'level' => ['icon' => '🎯', 'label' => 'Cấp học'],
                    'curriculum' => ['icon' => '📖', 'label' => 'Chương trình'],
                    'doc_type' => ['icon' => '📄', 'label' => 'Loại tài liệu']
                ];

                foreach ($all_categories as $type => $cats):
                    if (empty($cats)) continue;
                    $type_info = $type_labels[$type] ?? ['icon' => '📌', 'label' => $type];
                ?>
                    <div class="filter-group">
                        <div class="filter-group-title">
                            <span class="filter-group-icon"><?= $type_info['icon'] ?></span>
                            <?= $type_info['label'] ?>
                        </div>
                        <div class="filter-options">
                            <?php foreach ($cats as $cat): ?>
                                <div class="filter-option">
                                    <input 
                                        type="checkbox" 
                                        name="filters[]" 
                                        value="<?= $cat['id'] ?>"
                                        id="cat-<?= $cat['id'] ?>"
                                        <?= in_array($cat['id'], $filters) ? 'checked' : '' ?>
                                    >
                                    <label for="cat-<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="filter-apply-btn">Áp dụng bộ lọc</button>
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
                    ?>
                        <div class="document-card">
                            <?php if ($thumbnail && file_exists('uploads/' . $thumbnail)): ?>
                                <div class="document-thumbnail">
                                    <img src="uploads/<?= htmlspecialchars($thumbnail) ?>" alt="Thumbnail" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px 8px 0 0;">
                                    <?php if ($total_pages): ?>
                                        <span class="badge badge-primary" style="position: absolute; bottom: 8px; right: 8px;"><?= $total_pages ?> trang</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="document-icon">📄</div>
                            <?php endif; ?>
                            <div class="document-info">
                                <a href="view.php?id=<?= $doc['id'] ?>" class="document-title">
                                    <?= htmlspecialchars($doc['original_name']) ?>
                                </a>
                                <div class="document-meta">
                                    <span>👤 <?= htmlspecialchars($doc['username']) ?></span>
                                    <span>📅 <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                                    <?php if ($doc['points'] > 0): ?>
                                        <span>💰 <?= number_format($doc['points']) ?> điểm</span>
                                    <?php else: ?>
                                        <span style="color: #10b981;">🎁 Miễn phí</span>
                                    <?php endif; ?>
                                    <?php if ($total_pages): ?>
                                        <span>📄 <?= $total_pages ?> trang</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($doc['description'])): ?>
                                    <div class="document-description">
                                        <?= htmlspecialchars(mb_substr($doc['description'], 0, 150)) ?>
                                        <?= mb_strlen($doc['description']) > 150 ? '...' : '' ?>
                                    </div>
                                <?php endif; ?>
                                <div class="document-stats">
                                    <span class="stat-item">👁️ <?= number_format($doc['views']) ?> lượt xem</span>
                                    <span class="stat-item">⬇️ <?= number_format($doc['downloads']) ?> tải xuống</span>
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
                            Hãy thử tìm kiếm với từ khóa khác
                        <?php endif; ?>
                    </p>
                    
                    <?php 
                    $popular_searches = getPopularSearches(5);
                    if (!empty($popular_searches)):
                    ?>
                        <p style="margin-top: 20px; color: #6b7280; font-size: 14px;">Tìm kiếm phổ biến:</p>
                        <div class="no-results-suggestions">
                            <?php foreach ($popular_searches as $ps): ?>
                                <a href="search.php?q=<?= urlencode($ps['keyword']) ?>" class="suggestion-tag">
                                    🔥 <?= htmlspecialchars($ps['keyword']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    function changeSort(sortType) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', sortType);
        url.searchParams.set('page', '1'); // Reset to page 1
        window.location.href = url.toString();
    }

    function resetFilters() {
        const url = new URL(window.location.href);
        url.searchParams.delete('filters[]');
        url.searchParams.delete('filters');
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }
</script>

<?php include 'includes/footer.php'; ?>
