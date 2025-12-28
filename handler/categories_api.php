<?php
/**
 * Categories API Endpoint
 * AJAX endpoint for cascade category selection
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/categories.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'education_levels':
            // Get all education levels
            $levels = getEducationLevels();
            echo json_encode([
                'success' => true,
                'data' => $levels
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'grades':
            // Get grades by education level
            $level = $_GET['level'] ?? '';
            if (empty($level)) {
                throw new Exception('Missing level parameter');
            }
            
            $grades = getGradesByLevel($level);
            echo json_encode([
                'success' => true,
                'data' => $grades
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'subjects':
            // Get subjects by level and grade_id
            $level = $_GET['level'] ?? '';
            $grade_id = intval($_GET['grade_id'] ?? 0);
            
            if (empty($level) || $grade_id <= 0) {
                throw new Exception('Missing level or grade_id parameter');
            }
            
            $subjects = getSubjectsByGrade($level, $grade_id);
            echo json_encode([
                'success' => true,
                'data' => $subjects
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'major_groups':
            // Get all major groups (nhóm ngành)
            $groups = getMajorGroups();
            echo json_encode([
                'success' => true,
                'data' => $groups
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'majors':
            // Get majors by group_id
            $group_id = intval($_GET['group_id'] ?? 0);
            
            if ($group_id <= 0) {
                throw new Exception('Missing group_id parameter');
            }
            
            $majors = getMajorsByGroup($group_id);
            echo json_encode([
                'success' => true,
                'data' => $majors
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'doc_types':
            // Get document types by education level
            $level = $_GET['level'] ?? '';
            
            if (empty($level)) {
                throw new Exception('Missing level parameter');
            }
            
            $doc_types = getDocTypes($level);
            echo json_encode([
                'success' => true,
                'data' => $doc_types
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'all_data':
            // Get all data for initial load (optimization)
            $level = $_GET['level'] ?? '';
            
            $response = [
                'success' => true,
                'data' => [
                    'education_levels' => getEducationLevels()
                ]
            ];
            
            if (!empty($level)) {
                if (isPhoThong($level)) {
                    $response['data']['grades'] = getGradesByLevel($level);
                } else {
                    $response['data']['major_groups'] = getMajorGroups();
                }
                $response['data']['doc_types'] = getDocTypes($level);
            }
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'validate':
            // Validate category selection
            $education_level = $_GET['education_level'] ?? '';
            $grade_id = $_GET['grade_id'] ?? null;
            $subject_code = $_GET['subject_code'] ?? null;
            $major_group_id = $_GET['major_group_id'] ?? null;
            $major_code = $_GET['major_code'] ?? null;
            $doc_type_code = $_GET['doc_type_code'] ?? '';
            
            $errors = [];
            
            // Validate education level
            if (empty($education_level) || !isset(EDUCATION_LEVELS[$education_level])) {
                $errors[] = 'Vui lòng chọn cấp học hợp lệ';
            }
            
            // Validate based on education type
            if (isPhoThong($education_level)) {
                if (empty($grade_id)) {
                    $errors[] = 'Vui lòng chọn lớp';
                }
                if (empty($subject_code)) {
                    $errors[] = 'Vui lòng chọn môn học';
                }
            } else if ($education_level === 'dai_hoc') {
                if (empty($major_group_id)) {
                    $errors[] = 'Vui lòng chọn nhóm ngành';
                }
                if (empty($major_code)) {
                    $errors[] = 'Vui lòng chọn ngành học';
                }
            }
            
            // Validate doc type
            if (empty($doc_type_code)) {
                $errors[] = 'Vui lòng chọn loại tài liệu';
            }
            
            echo json_encode([
                'success' => empty($errors),
                'errors' => $errors
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

