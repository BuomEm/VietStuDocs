<?php
/**
 * Categories Management Functions V2
 * Cascade selection: Cấp học → Lớp/Nhóm ngành → Môn học/Ngành → Loại tài liệu
 */

require_once __DIR__ . '/function.php';

// =====================================================
// CONSTANTS - Education Levels
// =====================================================

define('EDUCATION_LEVELS', [
    'tieu_hoc' => ['name' => 'Tiểu học', 'code' => 'TH', 'type' => 'pho_thong'],
    'thcs' => ['name' => 'THCS', 'code' => 'THCS', 'type' => 'pho_thong'],
    'thpt' => ['name' => 'THPT', 'code' => 'THPT', 'type' => 'pho_thong'],
    'dai_hoc' => ['name' => 'Đại học', 'code' => 'DH', 'type' => 'dai_hoc']
]);

// =====================================================
// JSON DATA LOADERS
// =====================================================

/**
 * Load JSON file from API directory
 */
function loadJsonApi($filename) {
    $path = __DIR__ . '/../API/' . $filename;
    if (!file_exists($path)) {
        error_log("API file not found: $path");
        return null;
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return $data['data'] ?? $data;
}

/**
 * Get subjects data from mon-hoc.json
 */
function getSubjectsData() {
    static $cache = null;
    if ($cache === null) {
        $cache = loadJsonApi('mon-hoc.json');
    }
    return $cache;
}

/**
 * Get majors data from nganh-hoc.json
 */
function getMajorsData() {
    static $cache = null;
    if ($cache === null) {
        $cache = loadJsonApi('nganh-hoc.json');
    }
    return $cache;
}

/**
 * Get document types from loai-tai-lieu.json
 */
function getDocTypesData() {
    static $cache = null;
    if ($cache === null) {
        $cache = loadJsonApi('loai-tai-lieu.json');
    }
    return $cache;
}

// =====================================================
// EDUCATION LEVELS
// =====================================================

/**
 * Get all education levels
 */
function getEducationLevels() {
    $levels = [];
    foreach (EDUCATION_LEVELS as $code => $info) {
        $levels[] = [
            'code' => $code,
            'name' => $info['name'],
            'type' => $info['type']
        ];
    }
    return $levels;
}

/**
 * Get education level info by code
 */
function getEducationLevelInfo($level_code) {
    return EDUCATION_LEVELS[$level_code] ?? null;
}

/**
 * Check if education level is "pho_thong" type
 */
function isPhoThong($level_code) {
    $info = getEducationLevelInfo($level_code);
    return $info && $info['type'] === 'pho_thong';
}

// =====================================================
// GRADES (For phổ thông levels)
// =====================================================

/**
 * Get grades by education level
 */
function getGradesByLevel($level_code) {
    $data = getSubjectsData();
    if (!$data || !isset($data['levels'])) {
        return [];
    }
    
    $level_map = [
        'tieu_hoc' => 1, // id in JSON
        'thcs' => 2,
        'thpt' => 3
    ];
    
    $level_id = $level_map[$level_code] ?? null;
    if (!$level_id) {
        return [];
    }
    
    foreach ($data['levels'] as $level) {
        if ($level['id'] == $level_id) {
            return $level['grades'] ?? [];
        }
    }
    
    return [];
}

/**
 * Get grade info by level and grade_id
 */
function getGradeInfo($level_code, $grade_id) {
    $grades = getGradesByLevel($level_code);
    foreach ($grades as $grade) {
        if ($grade['id'] == $grade_id) {
            return $grade;
        }
    }
    return null;
}

// =====================================================
// SUBJECTS (For phổ thông levels)
// =====================================================

/**
 * Get subjects by education level and grade_id
 */
function getSubjectsByGrade($level_code, $grade_id) {
    $grades = getGradesByLevel($level_code);
    foreach ($grades as $grade) {
        if ($grade['id'] == $grade_id) {
            return $grade['subjects'] ?? [];
        }
    }
    return [];
}

/**
 * Get subject info by code and grade
 */
function getSubjectInfo($level_code, $grade_id, $subject_code) {
    $subjects = getSubjectsByGrade($level_code, $grade_id);
    foreach ($subjects as $subject) {
        if ($subject['code'] == $subject_code) {
            return $subject;
        }
    }
    return null;
}

/**
 * Get subject name by code (search all grades)
 */
function getSubjectNameByCode($subject_code) {
    $data = getSubjectsData();
    if (!$data || !isset($data['levels'])) {
        return $subject_code;
    }
    
    foreach ($data['levels'] as $level) {
        foreach ($level['grades'] ?? [] as $grade) {
            foreach ($grade['subjects'] ?? [] as $subject) {
                if ($subject['code'] == $subject_code) {
                    return $subject['name'];
                }
            }
        }
    }
    
    return $subject_code;
}

// =====================================================
// MAJOR GROUPS (For đại học)
// =====================================================

/**
 * Get all major groups (nhóm ngành)
 */
function getMajorGroups() {
    $data = getMajorsData();
    if (!$data || !isset($data['groups'])) {
        return [];
    }
    
    $groups = [];
    foreach ($data['groups'] as $group) {
        $groups[] = [
            'id' => $group['id'],
            'name' => $group['name'],
            'code' => $group['code']
        ];
    }
    
    return $groups;
}

/**
 * Get major group info by ID
 */
function getMajorGroupInfo($group_id) {
    $data = getMajorsData();
    if (!$data || !isset($data['groups'])) {
        return null;
    }
    
    foreach ($data['groups'] as $group) {
        if ($group['id'] == $group_id) {
            return $group;
        }
    }
    
    return null;
}

// =====================================================
// MAJORS (For đại học)
// =====================================================

/**
 * Get majors by group ID
 */
function getMajorsByGroup($group_id) {
    $data = getMajorsData();
    if (!$data || !isset($data['groups'])) {
        return [];
    }
    
    foreach ($data['groups'] as $group) {
        if ($group['id'] == $group_id) {
            return $group['majors'] ?? [];
        }
    }
    
    return [];
}

/**
 * Get major info by code
 */
function getMajorInfo($major_code) {
    $data = getMajorsData();
    if (!$data || !isset($data['groups'])) {
        return null;
    }
    
    foreach ($data['groups'] as $group) {
        foreach ($group['majors'] ?? [] as $major) {
            if ($major['code'] == $major_code) {
                return array_merge($major, ['group_id' => $group['id'], 'group_name' => $group['name']]);
            }
        }
    }
    
    return null;
}

/**
 * Get major name by code
 */
function getMajorNameByCode($major_code) {
    $info = getMajorInfo($major_code);
    return $info ? $info['name'] : $major_code;
}

// =====================================================
// DOCUMENT TYPES
// =====================================================

/**
 * Get document types by education level type
 */
function getDocTypes($level_code) {
    $data = getDocTypesData();
    if (!$data) {
        return [];
    }
    
    $level_info = getEducationLevelInfo($level_code);
    if (!$level_info) {
        return [];
    }
    
    $type = $level_info['type'];
    return $data[$type] ?? [];
}

/**
 * Get document type name by code
 */
function getDocTypeName($doc_type_code, $level_code = null) {
    $data = getDocTypesData();
    if (!$data) {
        return $doc_type_code;
    }
    
    // Search in both types
    foreach (['pho_thong', 'dai_hoc'] as $type) {
        foreach ($data[$type] ?? [] as $doc_type) {
            if ($doc_type['code'] == $doc_type_code) {
                return $doc_type['name'];
            }
        }
    }
    
    return $doc_type_code;
}

// =====================================================
// DOCUMENT CATEGORIES (Database Operations)
// =====================================================

/**
 * Save document category
 */
function saveDocumentCategory($document_id, $education_level, $grade_id = null, $subject_code = null, $major_group_id = null, $major_code = null, $doc_type_code = null) {
    $document_id = intval($document_id);
    $education_level = db_escape($education_level);
    $grade_id = $grade_id ? intval($grade_id) : 'NULL';
    $subject_code = $subject_code ? "'" . db_escape($subject_code) . "'" : 'NULL';
    $major_group_id = $major_group_id ? intval($major_group_id) : 'NULL';
    $major_code = $major_code ? "'" . db_escape($major_code) . "'" : 'NULL';
    $doc_type_code = db_escape($doc_type_code);
    
    // Delete existing category for this document
    db_query("DELETE FROM document_categories WHERE document_id = $document_id");
    
    // Insert new category
    $query = "INSERT INTO document_categories 
              (document_id, education_level, grade_id, subject_code, major_group_id, major_code, doc_type_code)
              VALUES 
              ($document_id, '$education_level', $grade_id, $subject_code, $major_group_id, $major_code, '$doc_type_code')";
    
    return db_query($query);
}

/**
 * Get document category
 */
function getDocumentCategory($document_id) {
    $document_id = intval($document_id);
    $query = "SELECT * FROM document_categories WHERE document_id = $document_id";
    return db_get_row($query);
}

/**
 * Get document category with full names
 */
function getDocumentCategoryWithNames($document_id) {
    $cat = getDocumentCategory($document_id);
    if (!$cat) {
        return null;
    }
    
    $result = [
        'education_level' => $cat['education_level'],
        'education_level_name' => EDUCATION_LEVELS[$cat['education_level']]['name'] ?? $cat['education_level'],
        'doc_type_code' => $cat['doc_type_code'],
        'doc_type_name' => getDocTypeName($cat['doc_type_code'])
    ];
    
    if (isPhoThong($cat['education_level'])) {
        $grade_info = getGradeInfo($cat['education_level'], $cat['grade_id']);
        $result['grade_id'] = $cat['grade_id'];
        $result['grade_name'] = $grade_info ? $grade_info['name'] : "Lớp " . $cat['grade_id'];
        $result['subject_code'] = $cat['subject_code'];
        $result['subject_name'] = getSubjectNameByCode($cat['subject_code']);
    } else {
        $group_info = getMajorGroupInfo($cat['major_group_id']);
        $major_info = getMajorInfo($cat['major_code']);
        
        $result['major_group_id'] = $cat['major_group_id'];
        $result['major_group_name'] = $group_info ? $group_info['name'] : '';
        $result['major_code'] = $cat['major_code'];
        $result['major_name'] = $major_info ? $major_info['name'] : '';
    }
    
    return $result;
}

/**
 * Delete document category
 */
function deleteDocumentCategory($document_id) {
    $document_id = intval($document_id);
    return db_query("DELETE FROM document_categories WHERE document_id = $document_id");
}

// =====================================================
// SEARCH & FILTER
// =====================================================

/**
 * Search documents by category filters
 */
function searchDocumentsByCategory($filters = []) {
    $where = ["d.status = 'approved'", "d.is_public = 1"];
    
    if (!empty($filters['education_level'])) {
        $level = db_escape($filters['education_level']);
        $where[] = "dc.education_level = '$level'";
    }
    
    if (!empty($filters['grade_id'])) {
        $grade = intval($filters['grade_id']);
        $where[] = "dc.grade_id = $grade";
    }
    
    if (!empty($filters['subject_code'])) {
        $subject = db_escape($filters['subject_code']);
        $where[] = "dc.subject_code = '$subject'";
    }
    
    if (!empty($filters['major_group_id'])) {
        $group = intval($filters['major_group_id']);
        $where[] = "dc.major_group_id = $group";
    }
    
    if (!empty($filters['major_code'])) {
        $major = db_escape($filters['major_code']);
        $where[] = "dc.major_code = '$major'";
    }
    
    if (!empty($filters['doc_type_code'])) {
        $doc_type = db_escape($filters['doc_type_code']);
        $where[] = "dc.doc_type_code = '$doc_type'";
    }
    
    $where_str = implode(' AND ', $where);
    
    $query = "
        SELECT DISTINCT d.*, u.username, dc.*
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN document_categories dc ON d.id = dc.document_id
        WHERE $where_str
        ORDER BY d.created_at DESC
    ";
    
    return db_get_results($query);
}

// =====================================================
// LEGACY COMPATIBILITY (For old code)
// =====================================================

function getCategoriesByType($type, $active_only = true) {
    return [];
}

function getAllCategoriesGrouped($active_only = true) {
    return [];
}

function getCategoryTypeLabel($type) {
    $labels = [
        'education_level' => 'Cấp học',
        'grade' => 'Lớp',
        'subject' => 'Môn học',
        'major_group' => 'Nhóm ngành',
        'major' => 'Ngành học',
        'doc_type' => 'Loại tài liệu'
    ];
    return $labels[$type] ?? $type;
}

function getDocumentCategories($document_id) {
    $cat = getDocumentCategoryWithNames($document_id);
    return $cat ? [$cat] : [];
}

function getDocumentCategoriesGrouped($document_id) {
    $cat = getDocumentCategoryWithNames($document_id);
    if (!$cat) return [];
    
    $result = [
        'level' => [['name' => $cat['education_level_name']]],
        'doc_type' => [['name' => $cat['doc_type_name']]]
    ];
    
    if (isset($cat['grade_name'])) {
        $result['grade'] = [['name' => $cat['grade_name']]];
        $result['subject'] = [['name' => $cat['subject_name']]];
    } else {
        $result['major_group'] = [['name' => $cat['major_group_name']]];
        $result['major'] = [['name' => $cat['major_name']]];
    }
    
    return $result;
}

function addDocumentCategories($document_id, $category_ids) {
    return true;
}

function updateDocumentCategories($document_id, $category_ids) {
    return true;
}

function removeDocumentCategories($document_id) {
    return deleteDocumentCategory($document_id);
}
?>
