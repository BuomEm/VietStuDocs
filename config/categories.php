<?php
// Categories Management Functions

require_once __DIR__ . '/db.php';

/**
 * Get all categories by type
 * @param string $type - field, subject, level, curriculum, doc_type
 * @param bool $active_only - only get active categories
 * @return array
 */
function getCategoriesByType($type, $active_only = true) {
    global $conn;
    $type = mysqli_real_escape_string($conn, $type);
    
    $where = "type = '$type'";
    if($active_only) {
        $where .= " AND is_active = 1";
    }
    
    $query = "SELECT * FROM categories WHERE $where ORDER BY sort_order ASC, name ASC";
    $result = mysqli_query($conn, $query);
    
    $categories = [];
    while($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    
    return $categories;
}

/**
 * Get all categories grouped by type
 * @param bool $active_only
 * @return array
 */
function getAllCategoriesGrouped($active_only = true) {
    $types = ['field', 'subject', 'level', 'curriculum', 'doc_type'];
    $grouped = [];
    
    foreach($types as $type) {
        $grouped[$type] = getCategoriesByType($type, $active_only);
    }
    
    return $grouped;
}

/**
 * Get category type label in Vietnamese
 * @param string $type
 * @return string
 */
function getCategoryTypeLabel($type) {
    $labels = [
        'field' => 'Lĩnh vực',
        'subject' => 'Môn học',
        'level' => 'Cấp học',
        'curriculum' => 'Chương trình',
        'doc_type' => 'Loại tài liệu'
    ];
    
    return $labels[$type] ?? $type;
}

/**
 * Get category by ID
 * @param int $category_id
 * @return array|null
 */
function getCategoryById($category_id) {
    global $conn;
    $category_id = intval($category_id);
    
    $query = "SELECT * FROM categories WHERE id = $category_id";
    $result = mysqli_query($conn, $query);
    
    return mysqli_fetch_assoc($result);
}

/**
 * Get categories for a document
 * @param int $document_id
 * @return array
 */
function getDocumentCategories($document_id) {
    global $conn;
    $document_id = intval($document_id);
    
    $query = "
        SELECT c.*, dc.id as dc_id
        FROM document_categories dc
        JOIN categories c ON dc.category_id = c.id
        WHERE dc.document_id = $document_id
        ORDER BY c.type, c.sort_order
    ";
    
    $result = mysqli_query($conn, $query);
    $categories = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    
    return $categories;
}

/**
 * Get categories for a document, grouped by type
 * @param int $document_id
 * @return array
 */
function getDocumentCategoriesGrouped($document_id) {
    $categories = getDocumentCategories($document_id);
    $grouped = [];
    
    foreach($categories as $cat) {
        $type = $cat['type'];
        if(!isset($grouped[$type])) {
            $grouped[$type] = [];
        }
        $grouped[$type][] = $cat;
    }
    
    return $grouped;
}

/**
 * Add categories to a document
 * @param int $document_id
 * @param array $category_ids
 * @return bool
 */
function addDocumentCategories($document_id, $category_ids) {
    global $conn;
    $document_id = intval($document_id);
    
    if(empty($category_ids) || !is_array($category_ids)) {
        return true; // No categories to add
    }
    
    // Remove old categories first
    mysqli_query($conn, "DELETE FROM document_categories WHERE document_id = $document_id");
    
    // Insert new categories
    $values = [];
    foreach($category_ids as $cat_id) {
        $cat_id = intval($cat_id);
        if($cat_id > 0) {
            $values[] = "($document_id, $cat_id)";
        }
    }
    
    if(empty($values)) {
        return true;
    }
    
    $query = "INSERT INTO document_categories (document_id, category_id) VALUES " . implode(', ', $values);
    return mysqli_query($conn, $query);
}

/**
 * Update categories for a document
 * @param int $document_id
 * @param array $category_ids
 * @return bool
 */
function updateDocumentCategories($document_id, $category_ids) {
    return addDocumentCategories($document_id, $category_ids);
}

/**
 * Remove all categories from a document
 * @param int $document_id
 * @return bool
 */
function removeDocumentCategories($document_id) {
    global $conn;
    $document_id = intval($document_id);
    
    return mysqli_query($conn, "DELETE FROM document_categories WHERE document_id = $document_id");
}

/**
 * Create new category
 * @param string $name
 * @param string $type
 * @param string $description
 * @param int $sort_order
 * @return int|false - category ID or false
 */
function createCategory($name, $type, $description = '', $sort_order = 0) {
    global $conn;
    
    $name = mysqli_real_escape_string($conn, $name);
    $type = mysqli_real_escape_string($conn, $type);
    $description = mysqli_real_escape_string($conn, $description);
    $sort_order = intval($sort_order);
    
    $query = "
        INSERT INTO categories (name, type, description, sort_order, is_active)
        VALUES ('$name', '$type', '$description', $sort_order, 1)
    ";
    
    if(mysqli_query($conn, $query)) {
        return mysqli_insert_id($conn);
    }
    
    return false;
}

/**
 * Update category
 * @param int $category_id
 * @param string $name
 * @param string $description
 * @param int $sort_order
 * @param int $is_active
 * @return bool
 */
function updateCategory($category_id, $name, $description = '', $sort_order = 0, $is_active = 1) {
    global $conn;
    
    $category_id = intval($category_id);
    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);
    $sort_order = intval($sort_order);
    $is_active = intval($is_active);
    
    $query = "
        UPDATE categories 
        SET name = '$name',
            description = '$description',
            sort_order = $sort_order,
            is_active = $is_active
        WHERE id = $category_id
    ";
    
    return mysqli_query($conn, $query);
}

/**
 * Delete category
 * @param int $category_id
 * @return bool
 */
function deleteCategory($category_id) {
    global $conn;
    $category_id = intval($category_id);
    
    // First remove from document_categories
    mysqli_query($conn, "DELETE FROM document_categories WHERE category_id = $category_id");
    
    // Then delete category
    return mysqli_query($conn, "DELETE FROM categories WHERE id = $category_id");
}

/**
 * Toggle category active status
 * @param int $category_id
 * @return bool
 */
function toggleCategoryStatus($category_id) {
    global $conn;
    $category_id = intval($category_id);
    
    $query = "UPDATE categories SET is_active = NOT is_active WHERE id = $category_id";
    return mysqli_query($conn, $query);
}

/**
 * Get document count by category
 * @param int $category_id
 * @return int
 */
function getDocumentCountByCategory($category_id) {
    global $conn;
    $category_id = intval($category_id);
    
    $query = "SELECT COUNT(*) as count FROM document_categories WHERE category_id = $category_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    return intval($row['count']);
}

/**
 * Search documents by categories
 * @param array $category_ids
 * @param string $match_type - 'any' or 'all'
 * @return array
 */
function searchDocumentsByCategories($category_ids, $match_type = 'any') {
    global $conn;
    
    if(empty($category_ids) || !is_array($category_ids)) {
        return [];
    }
    
    $ids = array_map('intval', $category_ids);
    $ids_str = implode(',', $ids);
    
    if($match_type === 'all') {
        // Document must have ALL specified categories
        $count = count($ids);
        $query = "
            SELECT d.*, COUNT(DISTINCT dc.category_id) as match_count
            FROM documents d
            JOIN document_categories dc ON d.id = dc.document_id
            WHERE dc.category_id IN ($ids_str)
            GROUP BY d.id
            HAVING match_count = $count
            ORDER BY d.created_at DESC
        ";
    } else {
        // Document must have ANY of the specified categories
        $query = "
            SELECT DISTINCT d.*
            FROM documents d
            JOIN document_categories dc ON d.id = dc.document_id
            WHERE dc.category_id IN ($ids_str)
            ORDER BY d.created_at DESC
        ";
    }
    
    $result = mysqli_query($conn, $query);
    $documents = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    
    return $documents;
}
?>
