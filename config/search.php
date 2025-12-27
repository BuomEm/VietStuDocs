<?php
// Search System Functions

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/categories.php';

/**
 * Main search function with filters
 * @param string $keyword - search keyword
 * @param array $filters - category filters
 * @param string $sort - sort method (relevance, popular, recent)
 * @param int $page - page number
 * @param int $per_page - results per page
 * @param int $user_id - current user ID (optional)
 * @return array - search results and metadata
 */
function searchDocuments($keyword = '', $filters = [], $sort = 'relevance', $page = 1, $per_page = 20, $user_id = null) {
    global $conn;
    
    $keyword = trim($keyword);
    $offset = ($page - 1) * $per_page;
    
    // Base query - only approved documents
    $where_clauses = ["d.status = 'approved'"];
    $join_clauses = [];
    $having_clauses = [];
    
    // Search keyword with FULLTEXT
    if (!empty($keyword) && strlen($keyword) >= 2) {
        $keyword_escaped = mysqli_real_escape_string($conn, $keyword);
        
        // FULLTEXT search
        $where_clauses[] = "MATCH(d.original_name, d.description) AGAINST('$keyword_escaped' IN NATURAL LANGUAGE MODE)";
        
        // Calculate relevance score
        $relevance_score = "MATCH(d.original_name, d.description) AGAINST('$keyword_escaped' IN NATURAL LANGUAGE MODE)";
    } else {
        // No keyword - show featured documents
        $relevance_score = "0";
    }
    
    // Category filters
    if (!empty($filters) && is_array($filters)) {
        $category_ids = array_filter(array_map('intval', $filters));
        if (!empty($category_ids)) {
            $ids_str = implode(',', $category_ids);
            $category_count = count($category_ids);
            
            // Join with document_categories
            $join_clauses[] = "INNER JOIN document_categories dc ON d.id = dc.document_id";
            $where_clauses[] = "dc.category_id IN ($ids_str)";
            $having_clauses[] = "COUNT(DISTINCT dc.category_id) = $category_count";
        }
    }
    
    // Build JOIN string
    $join_sql = implode(' ', $join_clauses);
    
    // Build WHERE string
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    
    // Build ORDER BY based on sort method
    switch($sort) {
        case 'popular':
            $order_by = "ORDER BY (d.views + d.downloads * 2) DESC, d.created_at DESC";
            break;
        case 'recent':
            $order_by = "ORDER BY d.created_at DESC";
            break;
        case 'relevance':
        default:
            if (!empty($keyword)) {
                // Boost: title exact match > description match > points > views
                $order_by = "ORDER BY 
                    (CASE WHEN d.original_name LIKE '%$keyword_escaped%' THEN 100 ELSE 0 END) DESC,
                    $relevance_score DESC,
                    (SELECT COALESCE(admin_points, user_price, 0) FROM docs_points WHERE document_id = d.id LIMIT 1) DESC,
                    (d.views + d.downloads * 2) DESC";
            } else {
                // No keyword: sort by points + popularity
                $order_by = "ORDER BY 
                    (SELECT COALESCE(admin_points, user_price, 0) FROM docs_points WHERE document_id = d.id LIMIT 1) DESC,
                    (d.views + d.downloads * 2) DESC,
                    d.created_at DESC";
            }
            break;
    }
    
    // Get total count
    $count_query = "
        SELECT COUNT(DISTINCT d.id) as total
        FROM documents d
        $join_sql
        $where_sql
    ";
    
    if (!empty($having_clauses)) {
        $count_query = "
            SELECT COUNT(*) as total FROM (
                SELECT d.id
                FROM documents d
                $join_sql
                $where_sql
                GROUP BY d.id
                HAVING " . implode(' AND ', $having_clauses) . "
            ) as subquery
        ";
    }
    
    $count_result = mysqli_query($conn, $count_query);
    $total_results = mysqli_fetch_assoc($count_result)['total'];
    
    // Get search results
    $search_query = "
        SELECT DISTINCT
            d.*,
            u.username,
            (SELECT COALESCE(admin_points, user_price, 0) FROM docs_points WHERE document_id = d.id LIMIT 1) as points,
            " . ($relevance_score ? "$relevance_score as relevance_score," : "") . "
            (d.views + d.downloads * 2) as popularity_score
        FROM documents d
        JOIN users u ON d.user_id = u.id
        $join_sql
        $where_sql
        " . (!empty($having_clauses) ? "GROUP BY d.id HAVING " . implode(' AND ', $having_clauses) : "") . "
        $order_by
        LIMIT $per_page OFFSET $offset
    ";
    
    $result = mysqli_query($conn, $search_query);
    
    $documents = [];
    while($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    
    // Log search history
    if (!empty($keyword)) {
        logSearchHistory($keyword, $filters, $total_results, $user_id);
    }
    
    return [
        'results' => $documents,
        'total' => $total_results,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_results / $per_page),
        'keyword' => $keyword,
        'filters' => $filters,
        'sort' => $sort
    ];
}

/**
 * Log search history
 */
function logSearchHistory($keyword, $filters, $results_count, $user_id = null) {
    global $conn;
    
    $keyword_escaped = mysqli_real_escape_string($conn, $keyword);
    $filters_json = mysqli_real_escape_string($conn, json_encode($filters));
    $user_id_sql = $user_id ? intval($user_id) : 'NULL';
    
    // Insert into search_history
    mysqli_query($conn, "
        INSERT INTO search_history (user_id, keyword, filters, results_count)
        VALUES ($user_id_sql, '$keyword_escaped', '$filters_json', $results_count)
    ");
    
    // Update search_suggestions
    mysqli_query($conn, "
        INSERT INTO search_suggestions (keyword, search_count, results_count_avg)
        VALUES ('$keyword_escaped', 1, $results_count)
        ON DUPLICATE KEY UPDATE
            search_count = search_count + 1,
            results_count_avg = (results_count_avg + $results_count) / 2,
            last_searched = NOW()
    ");
}

/**
 * Get search suggestions (autocomplete)
 */
function getSearchSuggestions($partial_keyword, $limit = 10) {
    global $conn;
    
    $partial_keyword = mysqli_real_escape_string($conn, trim($partial_keyword));
    
    if (strlen($partial_keyword) < 2) {
        return [];
    }
    
    $query = "
        SELECT keyword, search_count, results_count_avg
        FROM search_suggestions
        WHERE keyword LIKE '$partial_keyword%'
        ORDER BY search_count DESC, last_searched DESC
        LIMIT $limit
    ";
    
    $result = mysqli_query($conn, $query);
    $suggestions = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $suggestions[] = $row;
    }
    
    return $suggestions;
}

/**
 * Get popular searches
 */
function getPopularSearches($limit = 10) {
    global $conn;
    
    $query = "
        SELECT keyword, search_count, results_count_avg
        FROM search_suggestions
        WHERE results_count_avg > 0
        ORDER BY search_count DESC, last_searched DESC
        LIMIT $limit
    ";
    
    $result = mysqli_query($conn, $query);
    $popular = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $popular[] = $row;
    }
    
    return $popular;
}

/**
 * Get user's recent searches
 */
function getUserRecentSearches($user_id, $limit = 10) {
    global $conn;
    
    $user_id = intval($user_id);
    
    $query = "
        SELECT DISTINCT keyword, MAX(created_at) as last_searched
        FROM search_history
        WHERE user_id = $user_id
        GROUP BY keyword
        ORDER BY last_searched DESC
        LIMIT $limit
    ";
    
    $result = mysqli_query($conn, $query);
    $recent = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $recent[] = $row;
    }
    
    return $recent;
}

/**
 * Get featured documents (when no search keyword)
 */
function getFeaturedDocuments($limit = 20) {
    global $conn;
    
    $query = "
        SELECT 
            d.*,
            u.username,
            (SELECT COALESCE(admin_points, user_price, 0) FROM docs_points WHERE document_id = d.id LIMIT 1) as points,
            (d.views + d.downloads * 2) as popularity_score
        FROM documents d
        JOIN users u ON d.user_id = u.id
        WHERE d.status = 'approved'
        ORDER BY 
            (SELECT COALESCE(admin_points, user_price, 0) FROM docs_points WHERE document_id = d.id LIMIT 1) DESC,
            (d.views + d.downloads * 2) DESC,
            d.created_at DESC
        LIMIT $limit
    ";
    
    $result = mysqli_query($conn, $query);
    $documents = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Sanitize search keyword
 */
function sanitizeSearchKeyword($keyword) {
    $keyword = trim($keyword);
    $keyword = strip_tags($keyword);
    $keyword = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
    return $keyword;
}

/**
 * Check if search keyword is valid
 */
function isValidSearchKeyword($keyword) {
    $keyword = trim($keyword);
    return strlen($keyword) >= 2 && strlen($keyword) <= 255;
}
?>
