<?php
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('THUMBNAIL_DIR', __DIR__ . '/../uploads/thumbnails/');
define('MAX_FILE_SIZE', 200 * 1024 * 1024); // 200MB
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'zip']);
define('VIEWABLE_TYPES', ['pdf', 'txt', 'jpg', 'jpeg', 'png']);

// Create thumbnail directory if not exists
if(!is_dir(THUMBNAIL_DIR)) {
    mkdir(THUMBNAIL_DIR, 0755, true);
}

if(!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

function validateFile($file) {
    if($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'Upload error occurred'];
    }
    
    if($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'message' => 'File is too large (max 200MB)'];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if(!in_array($file_ext, ALLOWED_TYPES)) {
        return ['valid' => false, 'message' => 'File type not allowed'];
    }
    
    return ['valid' => true];
}

function uploadFile($file) {
    $validation = validateFile($file);
    
    if(!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = UPLOAD_DIR . $unique_name;
    
    if(move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'file_name' => $unique_name,
            'original_name' => $file['name']
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
}

function deleteFile($file_name) {
    $file_path = UPLOAD_DIR . $file_name;
    if(file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

function isViewableFile($file_extension) {
    return in_array(strtolower($file_extension), VIEWABLE_TYPES);
}

function getFileMimeType($file_extension) {
    $mime_types = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    return $mime_types[strtolower($file_extension)] ?? 'application/octet-stream';
}

/**
 * Count pages in a document
 * NOTE: PDF and DOCX page counting is now handled client-side using PDF.js
 * This function returns 0 for PDFs/DOCX to signal client-side processing needed
 * 
 * @param string $file_path Full path to the file
 * @param string $file_ext File extension
 * @return int Number of pages (0 for PDF/DOCX means client should handle)
 */
function countPages($file_path, $file_ext) {
    $file_ext = strtolower($file_ext);
    
    if (!file_exists($file_path)) {
        error_log("countPages: File not found: $file_path");
        return 0;
    }
    
    if ($file_ext === 'pdf') {
        // PDF page counting delegated to client-side PDF.js
        error_log("countPages: PDF file - client-side processing with PDF.js required");
        return 0;
    } elseif (in_array($file_ext, ['docx', 'doc'])) {
        // DOCX page counting delegated to client-side PDF.js (after conversion to PDF)
        error_log("countPages: DOCX file - client-side processing with PDF.js required");
        return 0;
    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        // Images are single page
        return 1;
    }
    
    return 0;
}

/**
 * Generate thumbnail from first page of PDF or DOCX
 * NOTE: PDF and DOCX thumbnail generation is now handled client-side using PDF.js
 * This function returns false for PDFs/DOCX to signal client-side processing needed
 * 
 * @param string $file_path Full path to the file
 * @param string $file_ext File extension
 * @param int $doc_id Document ID for unique filename
 * @return string|false Thumbnail filename on success, false on failure (false for PDF/DOCX means client should handle)
 */
function generateThumbnail($file_path, $file_ext, $doc_id) {
    $file_ext = strtolower($file_ext);
    
    if (!file_exists($file_path)) {
        error_log("generateThumbnail: File not found: $file_path");
        return false;
    }
    
    if ($file_ext === 'pdf') {
        // PDF thumbnail generation delegated to client-side PDF.js
        error_log("generateThumbnail: PDF file - client-side processing with PDF.js required");
        return false;
    } elseif (in_array($file_ext, ['docx', 'doc'])) {
        // DOCX thumbnail generation delegated to client-side PDF.js (after conversion to PDF)
        error_log("generateThumbnail: DOCX file - client-side processing with PDF.js required");
        return false;
    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        // For images, create a resized thumbnail using GD library
        $thumbnail_name = 'thumb_' . $doc_id . '_' . time() . '.jpg';
        $thumbnail_path = THUMBNAIL_DIR . $thumbnail_name;
        
        if (extension_loaded('gd')) {
            try {
                $image = null;
                switch ($file_ext) {
                    case 'jpg':
                    case 'jpeg':
                        $image = imagecreatefromjpeg($file_path);
                        break;
                    case 'png':
                        $image = imagecreatefrompng($file_path);
                        break;
                    case 'gif':
                        $image = imagecreatefromgif($file_path);
                        break;
                }
                
                if ($image) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    $new_width = 400;
                    $new_height = (int)($height * ($new_width / $width));
                    
                    $thumbnail = imagecreatetruecolor($new_width, $new_height);
                    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagejpeg($thumbnail, $thumbnail_path, 85);
                    imagedestroy($image);
                    imagedestroy($thumbnail);
                    
                    error_log("generateThumbnail: Successfully created image thumbnail: $thumbnail_path");
                    return 'thumbnails/' . $thumbnail_name;
                }
            } catch (Exception $e) {
                error_log("generateThumbnail: Image thumbnail error: " . $e->getMessage());
                return false;
            }
        }
        
        error_log("generateThumbnail: GD library not available for image thumbnail");
        return false;
    }
    
    return false;
}

/**
 * Convert DOCX to PDF using Adobe PDF Services API
 * @param string $docx_path Path to DOCX file
 * @param string $output_path Path to save PDF file
 * @param string &$error_msg Optional variable to store error message
 * @return array|false Returns array with 'pdf_path' (full path) and 'pdf_url' (relative URL) on success, false on failure
 */
function convertDocxToPdf_Adobe($docx_path, $output_path, &$error_msg = '') {
    if (!file_exists($docx_path)) {
        $error_msg = "File DOCX không tồn tại: " . basename($docx_path);
        error_log("convertDocxToPdf_Adobe: File not found: $docx_path");
        return false;
    }
    
    if (!function_exists('curl_init')) {
        $error_msg = "PHP cURL extension chưa được bật (Yêu cầu Adobe API)";
        error_log("convertDocxToPdf_Adobe: cURL not available");
        return false;
    }
    
    $credentials_path = __DIR__ . '/../API/pdfservices-api-credentials.json';
    if (!file_exists($credentials_path)) {
        $error_msg = "Thiếu file cấu hình Adobe: API/pdfservices-api-credentials.json (Có thể bạn quên upload file này lên cPanel?)";
        error_log("convertDocxToPdf_Adobe: Credentials file not found: $credentials_path");
        return false;
    }
    
    try {
        // Load credentials
        $credentials = json_decode(file_get_contents($credentials_path), true);
        if (!isset($credentials['client_credentials'])) {
            error_log("convertDocxToPdf_Adobe: Invalid credentials format");
            return false;
        }
        
        $clientId = $credentials['client_credentials']['client_id'];
        $clientSecret = $credentials['client_credentials']['client_secret'];
        
        // Authenticate
        $ch = curl_init('https://ims-na1.adobelogin.com/ims/token/v3');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Safeguard for cPanel/Old OpenSSL
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'openid AdobeID DCAPI'
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error_data = json_decode($response, true);
            $error_msg = "Adobe Auth Error ($httpCode): " . ($error_data['error_description'] ?? 'Không xác định');
            error_log("convertDocxToPdf_Adobe: OAuth error ($httpCode): " . substr($response, 0, 200));
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            error_log("convertDocxToPdf_Adobe: Access token not found in response");
            return false;
        }
        
        $accessToken = $data['access_token'];
        
        // Helper function for Adobe API calls
        $adobeCall = function($url, $accessToken, $clientId, $method = 'POST', $body = null, array $headers = [], $includeHeaders = false) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_SSL_VERIFYPEER => false, // Safeguard for cPanel
                CURLOPT_HTTPHEADER => array_merge([
                    "Authorization: Bearer $accessToken",
                    "x-api-key: $clientId"
                ], $headers)
            ]);
            
            if ($includeHeaders) {
                curl_setopt($ch, CURLOPT_HEADER, true);
            }
            
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($includeHeaders) {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $responseHeaders = substr($response, 0, $headerSize);
                $responseBody = substr($response, $headerSize);
                curl_close($ch);
                return [$httpCode, $responseBody, $responseHeaders];
            } else {
                curl_close($ch);
                return [$httpCode, $response];
            }
        };
        
        // PRE-SIGNED URL REQUEST
        $templateData = file_get_contents($docx_path);
        $templateType = mime_content_type($docx_path);
        
        list($code, $body) = $adobeCall(
            'https://pdf-services.adobe.io/assets',
            $accessToken,
            $clientId,
            'POST',
            json_encode(['mediaType' => $templateType]),
            ['Content-Type: application/json']
        );
        
        if ($code < 200 || $code >= 300) {
            error_log("convertDocxToPdf_Adobe: Pre-signed URL request error ($code): " . substr($body, 0, 200));
            return false;
        }
        
        $info = json_decode($body, true);
        if (!isset($info['uploadUri']) || !isset($info['assetID'])) {
            error_log("convertDocxToPdf_Adobe: Invalid upload response: " . substr($body, 0, 200));
            return false;
        }
        
        // UPLOAD FILE
        $ch = curl_init($info['uploadUri']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_SSL_VERIFYPEER => false, // Safeguard for cPanel
            CURLOPT_HTTPHEADER => ["Content-Type: $templateType"],
            CURLOPT_POSTFIELDS => $templateData
        ]);
        
        $resp = curl_exec($ch);
        $upCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($upCode < 200 || $upCode >= 300) {
            error_log("convertDocxToPdf_Adobe: Upload file error ($upCode): " . substr($resp, 0, 200));
            return false;
        }
        
        // PDF DOCUMENT GENERATION (Create PDF)
        list($code, $body, $headers) = $adobeCall(
            'https://pdf-services.adobe.io/operation/createpdf',
            $accessToken,
            $clientId,
            'POST',
            json_encode(['assetID' => $info['assetID']]),
            ['Content-Type: application/json'],
            true // Include headers to get location header
        );
        
        if ($code < 200 || $code >= 300) {
            error_log("convertDocxToPdf_Adobe: PDF document generation error ($code): " . substr($body, 0, 200));
            return false;
        }
        
        // Extract location header from response headers
        $pollUrl = null;
        if (preg_match('/location:\s*(.+)/i', $headers, $matches)) {
            $pollUrl = trim($matches[1]);
        } elseif (preg_match('/Location:\s*(.+)/i', $headers, $matches)) {
            $pollUrl = trim($matches[1]);
        } else {
            // Fallback: try to find in response body
            $responseData = json_decode($body, true);
            if (isset($responseData['statusUri'])) {
                $pollUrl = $responseData['statusUri'];
            } elseif (isset($responseData['location'])) {
                $pollUrl = $responseData['location'];
            } else {
                error_log("convertDocxToPdf_Adobe: No polling URL found in response");
                return false;
            }
        }
        
        // POLLING - Wait for conversion
        $maxAttempts = 60; // 2 minutes max
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            sleep(2);
            $attempt++;
            
            list($statusCode, $statusBody) = $adobeCall($pollUrl, $accessToken, $clientId, 'GET');
            
            if ($statusCode < 200 || $statusCode >= 300) {
                error_log("convertDocxToPdf_Adobe: Status check error ($statusCode): " . substr($statusBody, 0, 200));
                continue;
            }
            
            $statusData = json_decode($statusBody, true);
            
            if (!isset($statusData['status'])) {
                error_log("convertDocxToPdf_Adobe: Invalid status response: " . substr($statusBody, 0, 200));
                continue;
            }
            
            $status = $statusData['status'];
            
            if ($status === 'done' || $status === 'completed' || $status === 'succeeded') {
                if (!isset($statusData['asset']['downloadUri'])) {
                    error_log("convertDocxToPdf_Adobe: No download URI in response");
                    return false;
                }
                
                $downloadUri = $statusData['asset']['downloadUri'];
                
                // Download PDF - Pre-signed URL không cần Authorization header
                $ch = curl_init($downloadUri);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_SSL_VERIFYPEER => false // Safeguard for cPanel
                ]);
                
                $pdfContent = curl_exec($ch);
                $downloadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    error_log("convertDocxToPdf_Adobe: Download cURL error: $curlError");
                    return false;
                }
                
                if ($downloadCode < 200 || $downloadCode >= 300) {
                    error_log("convertDocxToPdf_Adobe: Download error ($downloadCode): " . substr($pdfContent, 0, 200));
                    return false;
                }
                
                // Ensure output directory exists
                $output_dir = dirname($output_path);
                if (!is_dir($output_dir)) {
                    mkdir($output_dir, 0755, true);
                }
                
                if (file_put_contents($output_path, $pdfContent) === false) {
                    error_log("convertDocxToPdf_Adobe: Failed to save PDF to $output_path");
                    return false;
                }
                
                error_log("convertDocxToPdf_Adobe: Successfully converted DOCX to PDF: $output_path");
                
                // Return relative URL
                $relative_path = str_replace(__DIR__ . '/../', '', $output_path);
                $relative_path = str_replace('\\', '/', $relative_path);
                
                return [
                    'pdf_path' => $output_path,
                    'pdf_url' => $relative_path,
                    'pdf_name' => basename($output_path)
                ];
                
            } elseif ($status === 'failed' || $status === 'error') {
                error_log("convertDocxToPdf_Adobe: Conversion failed: " . json_encode($statusData));
                return false;
            }
        }
        
        error_log("convertDocxToPdf_Adobe: Timeout - conversion took too long (max $maxAttempts attempts)");
        return false;
        
    } catch (Exception $e) {
        $error_msg = "Adobe API Exception: " . $e->getMessage();
        error_log("convertDocxToPdf_Adobe: Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert DOCX to PDF using CloudConvert API
 * @param string $docx_path Path to DOCX file
 * @param string $output_path Path to save PDF file
 * @param string &$error_msg Optional variable to store error message
 * @return array|false Returns array with 'pdf_path' (full path) and 'pdf_url' (relative URL) on success, false on failure
 */
function convertDocxToPdf_CloudConvert($docx_path, $output_path, &$error_msg = '') {
    if (!file_exists($docx_path)) {
        $error_msg = "File DOCX không tồn tại";
        return false;
    }
    
    $apiKey = getSetting('cloudconvert_api_key', defined('CLOUDCONVERT_API_KEY') ? CLOUDCONVERT_API_KEY : '');
    if (empty($apiKey)) {
        $error_msg = "Chưa cấu hình CloudConvert API Key";
        return false;
    }

    try {
        // 1. Create Job
        $ch = curl_init("https://api.cloudconvert.com/v2/jobs");
        $payload = json_encode([
            "tasks" => [
                "import-1" => ["operation" => "import/upload"],
                "convert-1" => [
                    "operation" => "convert",
                    "input" => "import-1",
                    "output_format" => "pdf",
                    "engine" => "office"
                ],
                "export-1" => [
                    "operation" => "export/url",
                    "input" => "convert-1"
                ]
            ]
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey", "Content-Type: application/json"],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 400) {
            $data = json_decode($response, true);
            $error_msg = "CloudConvert Job Error: " . ($data['message'] ?? 'Unknown');
            return false;
        }

        $data = json_decode($response, true);
        $jobId = $data['data']['id'];
        $uploadTask = null;
        foreach ($data['data']['tasks'] as $task) {
            if ($task['name'] === 'import-1') { $uploadTask = $task; break; }
        }

        // 2. Upload
        $ch = curl_init($uploadTask['result']['form']['url']);
        $postData = $uploadTask['result']['form']['parameters'] ?? [];
        $postData['file'] = new CURLFile($docx_path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        curl_exec($ch);
        curl_close($ch);

        // 3. Wait & Download
        $max_wait = 30;
        while ($max_wait-- > 0) {
            sleep(2);
            $ch = curl_init("https://api.cloudconvert.com/v2/jobs/$jobId");
            curl_setopt($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey"],
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $statusRes = curl_exec($ch);
            curl_close($ch);
            $statusData = json_decode($statusRes, true);
            
            if ($statusData['data']['status'] === 'finished') {
                foreach ($statusData['data']['tasks'] as $task) {
                    if ($task['name'] === 'export-1') {
                        $downloadUrl = $task['result']['files'][0]['url'];
                        $pdfContent = file_get_contents($downloadUrl);
                        file_put_contents($output_path, $pdfContent);
                        
                        $relative_path = str_replace(__DIR__ . '/../', '', $output_path);
                        $relative_path = str_replace('\\', '/', $relative_path);
                        return [
                            'pdf_path' => $output_path,
                            'pdf_url' => $relative_path,
                            'pdf_name' => basename($output_path)
                        ];
                    }
                }
            } elseif ($statusData['data']['status'] === 'error') {
                $error_msg = "CloudConvert conversion failed";
                return false;
            }
        }
        $error_msg = "CloudConvert timeout";
        return false;
    } catch (Exception $e) {
        $error_msg = "CloudConvert Exception: " . $e->getMessage();
        return false;
    }
}

/**
 * Convert DOCX to PDF (tries Adobe API first, then fallback to CloudConvert/LibreOffice)
 * @param string $docx_path Path to DOCX file
 * @param string|null $output_dir Optional output directory (defaults to UPLOAD_DIR)
 * @param string|null $output_filename Optional output filename (defaults to auto-generated)
 * @param string &$error_msg Optional variable to store error message
 * @return array|false Returns array with 'pdf_path' (full path) and 'pdf_url' (relative URL) on success, false on failure
 */
function convertDocxToPdf($docx_path, $output_dir = null, $output_filename = null, &$error_msg = '') {
    if (!file_exists($docx_path)) {
        error_log("convertDocxToPdf: File not found: $docx_path");
        return false;
    }
    
    // Set default output directory to UPLOAD_DIR (not THUMBNAIL_DIR) to keep PDFs
    if ($output_dir === null) {
        $output_dir = UPLOAD_DIR;
    }
    
    // Generate output filename if not provided
    if ($output_filename === null) {
        $output_filename = pathinfo($docx_path, PATHINFO_FILENAME) . '_converted_' . time() . '.pdf';
    }
    
    $pdf_path = $output_dir . $output_filename;
    
    // Ensure output directory exists
    if (!is_dir($output_dir)) {
        mkdir($output_dir, 0755, true);
    }
    
    // Try Adobe API first
    error_log("convertDocxToPdf: Attempting Adobe API conversion...");
    $adobe_error = '';
    $adobe_result = convertDocxToPdf_Adobe($docx_path, $pdf_path, $adobe_error);
    if ($adobe_result !== false && file_exists($pdf_path) && filesize($pdf_path) > 0) {
        error_log("convertDocxToPdf: Successfully converted using Adobe API");
        return $adobe_result;
    }
    
    error_log("convertDocxToPdf: Adobe API failed ($adobe_error), trying CloudConvert fallback...");
    $cc_error = '';
    $cc_result = convertDocxToPdf_CloudConvert($docx_path, $pdf_path, $cc_error);
    if ($cc_result !== false && file_exists($pdf_path) && filesize($pdf_path) > 0) {
        error_log("convertDocxToPdf: Successfully converted using CloudConvert");
        return $cc_result;
    }
    
    error_log("convertDocxToPdf: CloudConvert failed ($cc_error), trying LibreOffice fallback...");
    
    // Fallback: Try LibreOffice
    $error_log_msg = "Adobe: $adobe_error. CC: $cc_error. ";
    if (function_exists('shell_exec')) {
        // ... (LibreOffice code) ...
        $libreoffice_paths = [
            'soffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'
        ];
        
        // Clean up existing PDF if exists
        if (file_exists($pdf_path)) {
            @unlink($pdf_path);
        }
        
        $conversion_success = false;
        foreach ($libreoffice_paths as $lo_path) {
            $libreoffice_cmd = "\"$lo_path\" --headless --convert-to pdf --outdir \"" . $output_dir . "\" \"" . $docx_path . "\" 2>&1";
            $output = @shell_exec($libreoffice_cmd);
            
            // Check if PDF was created (LibreOffice uses original filename)
            $libreoffice_pdf = $output_dir . pathinfo($docx_path, PATHINFO_FILENAME) . '.pdf';
            if (file_exists($libreoffice_pdf) && filesize($libreoffice_pdf) > 0) {
                // Rename to our desired filename
                if ($libreoffice_pdf !== $pdf_path) {
                    rename($libreoffice_pdf, $pdf_path);
                }
                error_log("convertDocxToPdf: Successfully converted using $lo_path");
                $conversion_success = true;
                break;
            } else {
                error_log("convertDocxToPdf: Failed with $lo_path, output: " . substr($output, 0, 200));
            }
        }
        
        if ($conversion_success && file_exists($pdf_path)) {
            // Return both full path and relative URL
            $relative_path = str_replace(__DIR__ . '/../', '', $pdf_path);
            $relative_path = str_replace('\\', '/', $relative_path);
            
            return [
                'pdf_path' => $pdf_path,
                'pdf_url' => $relative_path,
                'pdf_name' => $output_filename
            ];
        }
    } else {
        error_log("convertDocxToPdf: shell_exec is disabled (LibreOffice fallback unavailable)");
        $error_log_msg .= "LibreOffice: shell_exec bị tắt. ";
    }
    
    $error_msg = $error_log_msg . "Vui lòng kiểm tra cấu hình Adobe hoặc CloudConvert API.";
    error_log("convertDocxToPdf: All conversion attempts failed for $docx_path. Error: $error_msg");
    return false;
}

/**
 * Convert DOCX to PNG (first page only) using CloudConvert API
 * Based on AAA/dctopd.php
 * @param string $docx_path Path to DOCX file
 * @param string $output_path Path to save PNG file
 * @return bool Returns true on success, false on failure
 */
function convertDocxToPng($docx_path, $output_path) {
    if (!file_exists($docx_path)) {
        error_log("convertDocxToPng: File not found: $docx_path");
        return false;
    }
    
    if (!function_exists('curl_init')) {
        error_log("convertDocxToPng: cURL not available");
        return false;
    }
    
    $apiKey = CLOUDCONVERT_API_KEY;
    if (empty($apiKey)) {
        error_log("convertDocxToPng: CloudConvert API key not configured");
        return false;
    }
    
    try {
        // 1. Create conversion job
        $url = "https://api.cloudconvert.com/v2/jobs";
        $payload = json_encode([
            "tasks" => [
                "import-1" => [
                    "operation" => "import/upload"
                ],
                "convert-1" => [
                    "operation" => "convert",
                    "input" => "import-1",
                    "output_format" => "png",
                    "engine" => "office",
                    "pages" => "1" // Only first page
                ],
                "export-1" => [
                    "operation" => "export/url",
                    "input" => "convert-1"
                ]
            ]
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey", "Content-Type: application/json"],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("convertDocxToPng: cURL error: $curl_error");
            return false;
        }
        
        // CloudConvert API returns 201 (Created) on successful job creation, 200 is also valid
        if ($http_code !== 200 && $http_code !== 201) {
            $error_msg = "Job creation failed: HTTP $http_code";
            if ($response) {
                $error_data = json_decode($response, true);
                if (isset($error_data['message'])) {
                    $error_msg .= ", Message: " . $error_data['message'];
                }
                if (isset($error_data['code'])) {
                    $error_msg .= ", Code: " . $error_data['code'];
                }
                if (isset($error_data['errors'])) {
                    $error_msg .= ", Errors: " . json_encode($error_data['errors']);
                }
            }
            error_log("convertDocxToPng: $error_msg, Response: " . substr($response, 0, 500));
            
            // Handle rate limiting - wait and retry once
            if ($http_code === 402 || $http_code === 429) {
                error_log("convertDocxToPng: Rate limit hit, waiting 5 seconds before retry...");
                sleep(5);
                // Retry once
                return convertDocxToPng($docx_path, $output_path);
            }
            
            return false;
        }
        
        $response_data = json_decode($response, true);
        if (!$response_data || !isset($response_data['data']['id'])) {
            error_log("convertDocxToPng: Invalid job response. Response: " . substr($response, 0, 500));
            return false;
        }
        
        $jobId = $response_data['data']['id'];
        
        // 2. Upload file DOCX to import task
        $uploadTask = null;
        foreach ($response_data['data']['tasks'] as $task) {
            if ($task['name'] == 'import-1') {
                $uploadTask = $task;
                break;
            }
        }
        
        if (!$uploadTask || !isset($uploadTask['result']['form']['url'])) {
            error_log("convertDocxToPng: Upload task not found. Response data: " . json_encode($response_data['data']['tasks']));
            return false;
        }
        
        $uploadUrl = $uploadTask['result']['form']['url'];
        $parameters = isset($uploadTask['result']['form']['parameters']) ? $uploadTask['result']['form']['parameters'] : [];
        
        $postData = $parameters;
        $postData['file'] = new CURLFile($docx_path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', basename($docx_path));
        
        $chUpload = curl_init($uploadUrl);
        curl_setopt($chUpload, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chUpload, CURLOPT_POST, true);
        curl_setopt($chUpload, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($chUpload, CURLOPT_TIMEOUT, 300); // 5 minutes timeout for large files
        $uploadResponse = curl_exec($chUpload);
        $uploadHttpCode = curl_getinfo($chUpload, CURLINFO_HTTP_CODE);
        $uploadError = curl_error($chUpload);
        curl_close($chUpload);
        
        if ($uploadError) {
            error_log("convertDocxToPng: Upload cURL error: $uploadError");
            return false;
        }
        
        if ($uploadHttpCode !== 200 && $uploadHttpCode !== 204) {
            error_log("convertDocxToPng: File upload failed: HTTP $uploadHttpCode, Response: " . substr($uploadResponse, 0, 200));
            return false;
        }
        
        // 3. Wait for conversion and get download link
        $max_wait = 120; // 120 seconds timeout
        $wait_time = 0;
        $downloadUrl = null;
        
        while ($wait_time < $max_wait) {
            sleep(2);
            $wait_time += 2;
            
            $chStatus = curl_init("https://api.cloudconvert.com/v2/jobs/$jobId");
            curl_setopt($chStatus, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chStatus, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey"]);
            $statusResponse = curl_exec($chStatus);
            $statusHttpCode = curl_getinfo($chStatus, CURLINFO_HTTP_CODE);
            curl_close($chStatus);
            
            if ($statusHttpCode !== 200) {
                error_log("convertDocxToPng: Status check failed: HTTP $statusHttpCode");
                continue;
            }
            
            $jobStatus = json_decode($statusResponse, true);
            
            if (!$jobStatus || !isset($jobStatus['data']['status'])) {
                error_log("convertDocxToPng: Invalid status response: " . substr($statusResponse, 0, 200));
                continue;
            }
            
            $status = $jobStatus['data']['status'];
            
            if ($status == 'finished') {
                foreach ($jobStatus['data']['tasks'] as $task) {
                    if ($task['name'] == 'export-1' && isset($task['result']['files'][0]['url'])) {
                        $downloadUrl = $task['result']['files'][0]['url'];
                        break 2;
                    }
                }
            } elseif ($status == 'error') {
                $error_msg = "Conversion failed";
                if (isset($jobStatus['data']['message'])) {
                    $error_msg .= ": " . $jobStatus['data']['message'];
                }
                if (isset($jobStatus['data']['tasks'])) {
                    foreach ($jobStatus['data']['tasks'] as $task) {
                        if (isset($task['status']) && $task['status'] == 'error' && isset($task['message'])) {
                            $error_msg .= " (Task: " . $task['name'] . " - " . $task['message'] . ")";
                        }
                    }
                }
                error_log("convertDocxToPng: $error_msg");
                return false;
            }
        }
        
        if (!$downloadUrl) {
            error_log("convertDocxToPng: Timeout waiting for conversion (waited {$wait_time}s)");
            return false;
        }
        
        // 4. Download PNG file
        $chDownload = curl_init($downloadUrl);
        curl_setopt($chDownload, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chDownload, CURLOPT_TIMEOUT, 60);
        curl_setopt($chDownload, CURLOPT_FOLLOWLOCATION, true);
        $pngContent = curl_exec($chDownload);
        $downloadHttpCode = curl_getinfo($chDownload, CURLINFO_HTTP_CODE);
        $downloadError = curl_error($chDownload);
        curl_close($chDownload);
        
        if ($downloadError) {
            error_log("convertDocxToPng: Download cURL error: $downloadError");
            return false;
        }
        
        if ($downloadHttpCode !== 200 || $pngContent === false || strlen($pngContent) === 0) {
            error_log("convertDocxToPng: Failed to download PNG from $downloadUrl (HTTP $downloadHttpCode, Size: " . strlen($pngContent) . ")");
            return false;
        }
        
        // Ensure output directory exists
        $output_dir = dirname($output_path);
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        // Save PNG file
        if (file_put_contents($output_path, $pngContent) === false) {
            error_log("convertDocxToPng: Failed to save PNG to $output_path");
            return false;
        }
        
        // Convert PNG to JPG for consistency (thumbnails are stored as JPG)
        $jpg_path = str_replace('.png', '.jpg', $output_path);
        if (function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
            $png_image = imagecreatefrompng($output_path);
            if ($png_image !== false) {
                // Create white background
                $jpg_image = imagecreatetruecolor(imagesx($png_image), imagesy($png_image));
                $white = imagecolorallocate($jpg_image, 255, 255, 255);
                imagefill($jpg_image, 0, 0, $white);
                imagecopy($jpg_image, $png_image, 0, 0, 0, 0, imagesx($png_image), imagesy($png_image));
                imagejpeg($jpg_image, $jpg_path, 85);
                imagedestroy($png_image);
                imagedestroy($jpg_image);
                
                // Delete PNG, keep JPG
                @unlink($output_path);
                $output_path = $jpg_path;
            }
        }
        
        error_log("convertDocxToPng: Successfully converted DOCX to thumbnail: $output_path");
        return true;
        
    } catch (Exception $e) {
        error_log("convertDocxToPng: Exception: " . $e->getMessage());
        return false;
    }
}
