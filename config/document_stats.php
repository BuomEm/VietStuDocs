<?php
// Document Statistics Functions - View & Download Tracking

require_once __DIR__ . '/db.php';

/**
 * Increment document view count
 * @param int $document_id
 * @return bool
 */
function incrementDocumentViews($document_id) {
    global $conn;
    
    $document_id = intval($document_id);
    
    $query = "UPDATE documents SET views = views + 1 WHERE id = $document_id";
    
    return mysqli_query($conn, $query);
}

/**
 * Increment document download count
 * @param int $document_id
 * @return bool
 */
function incrementDocumentDownloads($document_id) {
    global $conn;
    
    $document_id = intval($document_id);
    
    $query = "UPDATE documents SET downloads = downloads + 1 WHERE id = $document_id";
    
    return mysqli_query($conn, $query);
}

/**
 * Get document statistics
 * @param int $document_id
 * @return array|null
 */
function getDocumentStats($document_id) {
    global $conn;
    
    $document_id = intval($document_id);
    
    $query = "SELECT views, downloads FROM documents WHERE id = $document_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Check if user has viewed document in this session
 * Prevents duplicate view counts from the same session
 * @param int $document_id
 * @return bool
 */
function hasViewedInSession($document_id) {
    if (!isset($_SESSION['viewed_docs'])) {
        $_SESSION['viewed_docs'] = [];
    }
    
    return in_array($document_id, $_SESSION['viewed_docs']);
}

/**
 * Mark document as viewed in session
 * @param int $document_id
 */
function markViewedInSession($document_id) {
    if (!isset($_SESSION['viewed_docs'])) {
        $_SESSION['viewed_docs'] = [];
    }
    
    if (!in_array($document_id, $_SESSION['viewed_docs'])) {
        $_SESSION['viewed_docs'][] = $document_id;
    }
}

/**
 * Check if user has downloaded document in this session
 * Prevents duplicate download counts from the same session
 * @param int $document_id
 * @return bool
 */
function hasDownloadedInSession($document_id) {
    if (!isset($_SESSION['downloaded_docs'])) {
        $_SESSION['downloaded_docs'] = [];
    }
    
    return in_array($document_id, $_SESSION['downloaded_docs']);
}

/**
 * Mark document as downloaded in session
 * @param int $document_id
 */
function markDownloadedInSession($document_id) {
    if (!isset($_SESSION['downloaded_docs'])) {
        $_SESSION['downloaded_docs'] = [];
    }
    
    if (!in_array($document_id, $_SESSION['downloaded_docs'])) {
        $_SESSION['downloaded_docs'][] = $document_id;
    }
}

/**
 * Get top viewed documents
 * @param int $limit
 * @return array
 */
function getTopViewedDocuments($limit = 10) {
    global $conn;
    
    $limit = intval($limit);
    
    $query = "
        SELECT 
            d.*,
            u.username,
            CASE WHEN d.user_price IS NULL THEN COALESCE(dp.admin_points, 0) ELSE d.user_price END as points
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN docs_points dp ON d.id = dp.document_id
        WHERE d.status = 'approved' AND d.is_public = 1
        ORDER BY d.views DESC, d.downloads DESC
        LIMIT $limit
    ";
    
    $result = mysqli_query($conn, $query);
    $documents = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Get top downloaded documents
 * @param int $limit
 * @return array
 */
function getTopDownloadedDocuments($limit = 10) {
    global $conn;
    
    $limit = intval($limit);
    
    $query = "
        SELECT 
            d.*,
            u.username,
            CASE WHEN d.user_price IS NULL THEN COALESCE(dp.admin_points, 0) ELSE d.user_price END as points
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN docs_points dp ON d.id = dp.document_id
        WHERE d.status = 'approved' AND d.is_public = 1
        ORDER BY d.downloads DESC, d.views DESC
        LIMIT $limit
    ";
    
    $result = mysqli_query($conn, $query);
    $documents = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Get user's document statistics summary
 * @param int $user_id
 * @return array
 */
function getUserDocumentStats($user_id) {
    global $conn;
    
    $user_id = intval($user_id);
    
    $query = "
        SELECT 
            COUNT(*) as total_docs,
            SUM(views) as total_views,
            SUM(downloads) as total_downloads,
            AVG(views) as avg_views,
            AVG(downloads) as avg_downloads
        FROM documents
        WHERE user_id = $user_id AND status = 'approved'
    ";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return [
        'total_docs' => 0,
        'total_views' => 0,
        'total_downloads' => 0,
        'avg_views' => 0,
        'avg_downloads' => 0
    ];
}
?>
