<?php
/**
 * Test CloudConvert API for DOCX to PNG conversion
 */

require_once __DIR__ . '/config/file.php';

// Test file (use a small DOCX file if available)
$test_file = __DIR__ . '/AAA/docxtest.docx';
if (!file_exists($test_file)) {
    // Try to find any DOCX file in uploads
    $uploads_dir = __DIR__ . '/uploads/';
    $files = glob($uploads_dir . '*.docx');
    if (!empty($files)) {
        $test_file = $files[0];
    } else {
        die("No DOCX file found for testing. Please upload a DOCX file first.\n");
    }
}

echo "Testing CloudConvert API...\n";
echo "API Key: " . substr(CLOUDCONVERT_API_KEY, 0, 20) . "...\n";
echo "Test file: $test_file\n";
echo "File exists: " . (file_exists($test_file) ? "Yes" : "No") . "\n";
echo "File size: " . filesize($test_file) . " bytes\n\n";

$output_path = __DIR__ . '/uploads/thumbnails/test_output.jpg';

echo "Calling convertDocxToPng()...\n";
$result = convertDocxToPng($test_file, $output_path);

if ($result) {
    echo "✓ SUCCESS! Thumbnail created at: $output_path\n";
    if (file_exists($output_path)) {
        echo "✓ File exists: Yes\n";
        echo "✓ File size: " . filesize($output_path) . " bytes\n";
    } else {
        echo "✗ File not found after conversion!\n";
    }
} else {
    echo "✗ FAILED! Check error logs for details.\n";
    echo "\nRecent error log entries:\n";
    echo "----------------------------------------\n";
    // Try to read last few lines of error log
    $error_log = ini_get('error_log');
    if ($error_log && file_exists($error_log)) {
        $lines = file($error_log);
        $recent = array_slice($lines, -10);
        foreach ($recent as $line) {
            if (strpos($line, 'convertDocxToPng') !== false) {
                echo $line;
            }
        }
    } else {
        echo "Error log not found or not configured.\n";
    }
}

echo "\n";
echo "Test completed.\n";
?>
