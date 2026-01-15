<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/ai_prompts.php';

class AIReviewHandler {
    private $api_key;
    private $admin_key;
    private $organization_id;
    private $db;
    private $base_url = 'https://api.openai.com/v1/';

    public function __construct($db) {
        $this->db = $db;
        // API Key for chat/assistants
        $this->api_key = getSetting('ai_openai_key') ?: (getenv('GPT_KEY') ?: ($_ENV['GPT_KEY'] ?? null));
        // Admin Key for Usage/Costs (required by OpenAI docs)
        $this->admin_key = getSetting('ai_openai_admin_key') ?: (getenv('ADMIN_GPT_KEY') ?: ($_ENV['ADMIN_GPT_KEY'] ?? null));
        // Organization ID
        $this->organization_id = getSetting('ai_openai_org_id') ?: (getenv('GPT_ORG') ?: ($_ENV['GPT_ORG'] ?? null));
    }

    public function getApiKey() {
        return $this->api_key;
    }

    public function getAdminKey() {
        return $this->admin_key;
    }

    /**
     * Get Detailed OpenAI Usage (daily tokens)
     * Uses Official Organization Usage API with start_time/end_time (Unix seconds)
     * Requires Admin API Key
     */
    public function getDetailedUsage($date = null) {
        if (!$date) $date = date('Y-m-d');

        // Convert date to UTC timestamps
        $start_time = strtotime($date . ' 00:00:00 UTC');
        $end_time = strtotime($date . ' 23:59:59 UTC') + 1;

        try {
            // Official Organization Usage API (requires Admin Key)
            return $this->request(
                "organization/usage/completions?start_time={$start_time}&end_time={$end_time}&bucket_width=1d&limit=1",
                'GET',
                null,
                false,
                true // use admin key
            );
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Organization Costs (Official 2024/2025 Endpoint)
     * Returns costs with amount.value in USD
     * Requires Admin API Key
     */
    public function getOrganizationCosts($start_time = null, $end_time = null) {
        if (!$start_time) $start_time = strtotime(date('Y-m-01') . ' 00:00:00 UTC');
        if (!$end_time) $end_time = time();
        
        try {
            return $this->request(
                "organization/costs?start_time={$start_time}&end_time={$end_time}&bucket_width=1d&limit=31",
                'GET',
                null,
                false,
                true // use admin key
            );
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Total Billing Usage (from Organization Costs API)
     * Parses results[].amount.value (USD float)
     */
    public function getBillingUsage($start_date = null, $end_date = null) {
        if (!$start_date) $start_date = date('Y-m-01');
        if (!$end_date) $end_date = date('Y-m-t');

        $start_time = strtotime($start_date . ' 00:00:00 UTC');
        $end_time = strtotime($end_date . ' 23:59:59 UTC') + 1;
        
        try {
            $costs = $this->getOrganizationCosts($start_time, $end_time);

            // Parse correctly: results[].amount.value (USD float)
            if (isset($costs['data'])) {
                $total = 0.0;
                foreach ($costs['data'] as $bucket) {
                    if (isset($bucket['results'])) {
                        foreach ($bucket['results'] as $r) {
                            if (isset($r['amount']['value'])) {
                                $total += (float)$r['amount']['value'];
                            }
                        }
                    }
                }
                return ['success' => true, 'total_usage' => $total, 'source' => 'organization/costs'];
            }

            // If no data array, return error from API
            return ['success' => false, 'error' => $costs['error'] ?? 'No data returned'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Subscription Info - Note: dashboard/billing/* requires session key
     * This will likely fail with API keys, returning graceful error
     */
    public function getSubscriptionInfo() {
        try {
            return $this->request("dashboard/billing/subscription", 'GET');
        } catch (Exception $e) {
            // Expected to fail with API keys - dashboard endpoints require session key
            return ['success' => false, 'error' => 'Dashboard endpoints require session key (not supported)'];
        }
    }

    /**
     * Aggregated Balance/Usage info (Unified)
     * Note: amount.value is already USD, no division needed
     */
    public function getBalanceInfo() {
        try {
            $billing = $this->getBillingUsage();
            
            return [
                'success' => true,
                'total_usage' => $billing['total_usage'] ?? 0.0, // Already USD, no /100
                'source' => $billing['source'] ?? 'unknown',
                'has_admin_key' => !empty($this->admin_key)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Quy đổi điểm AI (0-100) thành giá VSD
     * Công thức: Dựa trên điểm overall và quyết định của Moderator
     * Tất cả giá trị có thể chỉnh sửa trong Admin Settings
     */
    public function calculateVSDPrice($score, $decision = 'APPROVED') {
        $score = intval($score);
        $is_conditional = ($decision === 'CONDITIONAL' || $decision === 'Xem Xét');
        
        // Lấy cấu hình từ settings (với giá trị mặc định)
        $threshold_reject = intval(getSetting('ai_price_threshold_reject', 45));
        $threshold_conditional = intval(getSetting('ai_price_threshold_conditional', 60));
        $threshold_good = intval(getSetting('ai_price_threshold_good', 75));
        $threshold_excellent = intval(getSetting('ai_price_threshold_excellent', 90));
        
        $price_conditional_min = intval(getSetting('ai_price_conditional_min', 1));
        $price_conditional_max = intval(getSetting('ai_price_conditional_max', 3));
        $price_standard_min = intval(getSetting('ai_price_standard_min', 5));
        $price_standard_max = intval(getSetting('ai_price_standard_max', 10));
        $price_good_min = intval(getSetting('ai_price_good_min', 12));
        $price_good_max = intval(getSetting('ai_price_good_max', 20));
        $price_excellent_min = intval(getSetting('ai_price_excellent_min', 25));
        $price_excellent_max = intval(getSetting('ai_price_excellent_max', 50));
        
        // 1. Tài liệu bị reject hoặc điểm quá thấp -> 0 VSD
        if ($decision === 'REJECTED' || $decision === 'Từ Chối' || $score < $threshold_reject) {
            return 0;
        }
        
        $base_price = 0;

        // 2. Tính giá cơ sở dựa trên các bậc điểm số (Standard -> Excellent)
        if ($score >= $threshold_excellent) {
            $range = 100 - $threshold_excellent;
            $price_range = $price_excellent_max - $price_excellent_min;
            $base_price = $price_excellent_min + ($score - $threshold_excellent) * ($price_range / max(1, $range));
        } elseif ($score >= $threshold_good) {
            $range = $threshold_excellent - $threshold_good;
            $price_range = $price_good_max - $price_good_min;
            $base_price = $price_good_min + ($score - $threshold_good) * ($price_range / max(1, $range));
        } elseif ($score >= $threshold_conditional) {
            $range = $threshold_good - $threshold_conditional;
            $price_range = $price_standard_max - $price_standard_min;
            $base_price = $price_standard_min + ($score - $threshold_conditional) * ($price_range / max(1, $range));
        } else {
            // Dưới ngưỡng standard nhưng trên ngưỡng reject
            $range = $threshold_conditional - $threshold_reject;
            $price_range = $price_conditional_max - $price_conditional_min;
            $base_price = $price_conditional_min + ($score - $threshold_reject) * ($price_range / max(1, $range));
        }

        // 3. Áp dụng giảm trừ nếu là CONDITIONAL (Thay vì chặn cứng)
        if ($is_conditional) {
            // Giảm 40% giá trị để khuyến khích người dùng hoàn thiện tài liệu
            // Nhưng vẫn đảm bảo không thấp hơn mức tối thiểu của conditional
            $base_price = max($price_conditional_min, $base_price * 0.8);
        }
        
        return round($base_price);
    }

    /**
     * Quy đổi giá VSD sang VNĐ
     */
    public function convertVSDtoVND($vsd, $rate = null) {
        if ($rate === null) {
            $rate = intval(getSetting('shop_vsd_rate', 500));
        }
        return intval($vsd) * $rate;
    }

    /**
     * Fetch all models from OpenAI and save to API/openai_models.json
     */
    public function refreshModelsList() {
        try {
            $modelsData = $this->request("models", 'GET');
            
            // Only keep relevant models (gpt-*)
            $filtered = array_filter($modelsData['data'], function($m) {
                return strpos($m['id'], 'gpt-') !== false;
            });
            
            // Sort by ID
            usort($filtered, function($a, $b) { return strcmp($a['id'], $b['id']); });

            $file_path = __DIR__ . '/../API/openai_models.json';
            if (!is_dir(dirname($file_path))) mkdir(dirname($file_path), 0777, true);
            
            file_put_contents($file_path, json_encode(['data' => array_values($filtered)], JSON_PRETTY_PRINT));
            
            return ['success' => true, 'count' => count($filtered)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function request($endpoint, $method = 'GET', $data = null, $is_file = false, $use_admin_key = false) {
        // Select appropriate key
        $key = $use_admin_key ? $this->admin_key : $this->api_key;
        if (!$key) {
            throw new Exception($use_admin_key ? "Missing ADMIN_GPT_KEY" : "Missing GPT_KEY");
        }

        $ch = curl_init($this->base_url . $endpoint);
        
        $headers = [
            'Authorization: Bearer ' . $key,
            'OpenAI-Beta: assistants=v2'
        ];

        if ($this->organization_id) {
            $headers[] = 'OpenAI-Organization: ' . $this->organization_id;
        }

        if (!$is_file) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data) {
            if ($is_file) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        $result = json_decode($response, true);
        if ($status >= 400) {
            $msg = 'Unknown OpenAI Error';
            if (is_array($result) && isset($result['error']['message'])) {
                $msg = $result['error']['message'];
            } elseif (!empty($response)) {
                $msg = substr(strip_tags($response), 0, 200);
            }
            throw new Exception("OpenAI API Error ($status): $msg");
        }

        if ($response !== '' && $result === null) {
            throw new Exception("OpenAI returned an invalid JSON response: " . substr($response, 0, 100));
        }

        return $result;
    }

    public function reviewDocument($document_id) {
        $file_id = null;
        try {
            $doc = $this->getDocumentDetails($document_id);
            if (!$doc) throw new Exception("Không tìm thấy tài liệu.");

            $this->updateStatus($document_id, 'processing');
            
            // Cấu hình môi trường để tránh Timeout trên Shared Hosting
            ignore_user_abort(true); // Tiếp tục chạy ngay cả khi user đóng tab
            @set_time_limit(300);    // Cố gắng set timeout PHP lên 5 phút
            
            $metadata = $this->formatMetadata($doc);

            // 1. Upload file trực tiếp lên OpenAI (Bắt buộc cho vòng 1)
            $file_result = $this->uploadFile($doc);
            $file_id = $file_result['id'];

            // Lấy model từ settings
            require_once __DIR__ . '/../config/settings.php';
            $model_judge = getSetting('ai_model_judge', 'gpt-4o');
            $model_moderator = getSetting('ai_model_moderator', 'gpt-4o-mini');

            // 2. Chạy Vòng 1: AI JUDGE (Dùng Assistants API để phân tích File)
            $judge_result = $this->runAIAssistant($model_judge, AI_JUDGE_PROMPT, [
                'METADATA' => $metadata,
                'DOCUMENT' => "Vui lòng phân tích file đính kèm để đánh giá tài liệu."
            ], $file_id);

            // [FIX] Kiểm tra kết quả Vòng 1 trước khi sang Vòng 2
            if (empty($judge_result) || !is_array($judge_result)) {
                throw new Exception("Lỗi Vòng 1: AI Judge không trả về kết quả hợp lệ (Null hoặc JSON lỗi).");
            }

            // 3. Chạy Vòng 2: AI MODERATOR (Dùng Chat Completion - Tiết kiệm, không cần attach file)
            $moderator_result = $this->runChatCompletion($model_moderator, AI_MODERATOR_PROMPT, [
                'METADATA' => $metadata,
                'DOCUMENT' => "[Đã được AI Vòng 1 phân tích]", // Không gửi lại nội dung để tiết kiệm token
                'AI_RESULT' => json_encode($judge_result, JSON_UNESCAPED_UNICODE)
            ]);

            // 4. Lưu kết quả
            $this->saveReviewResults($document_id, $judge_result, $moderator_result);

            // Xóa file trên OpenAI dọn dẹp
            $this->deleteFile($file_id);

            return ['success' => true, 'decision' => $moderator_result['decision'], 'score' => $moderator_result['final_scores']['overall']];

        } catch (Exception $e) {
            if ($file_id) $this->deleteFile($file_id);
            $this->updateStatus($document_id, 'failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function uploadFile($doc) {
        $file_path = __DIR__ . '/../uploads/' . $doc['file_name'];
        if (!file_exists($file_path)) throw new Exception("File không tồn tại: " . $doc['file_name']);

        // OpenAI requires a filename with a supported extension for retrieval (file_search)
        // Ensure the postname has the extension from the server's file_name
        $ext = pathinfo($doc['file_name'], PATHINFO_EXTENSION);
        $postname = $doc['original_name'];
        if (!empty($ext) && strtolower(pathinfo($postname, PATHINFO_EXTENSION)) !== strtolower($ext)) {
            $postname .= "." . $ext;
        }

        $cfile = new CURLFile($file_path, mime_content_type($file_path), $postname);
        $data = [
            'purpose' => 'assistants',
            'file' => $cfile
        ];

        return $this->request('files', 'POST', $data, true);
    }

    /**
     * Chạy Assistant v2 (Dùng cho vòng 1 - Cần đọc File)
     */
    private function runAIAssistant($model, $prompt_template, $vars, $file_id) {
        $prompt = $prompt_template;
        foreach ($vars as $key => $val) {
            $prompt = str_replace("{{" . $key . "}}", $val, $prompt);
        }

        // Tạo Assistant tạm (Stateless pattern)
        $assistant = $this->request('assistants', 'POST', [
            'model' => $model,
            'instructions' => 'Bạn là chuyên gia thẩm định tài liệu chuyên sâu.',
            'tools' => [['type' => 'file_search']]
        ]);
        $assistant_id = $assistant['id'];

        $thread_id = null;
        try {
            // Tạo Thread kèm file
            $thread = $this->request('threads', 'POST', [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                        'attachments' => [
                            [
                                'file_id' => $file_id,
                                'tools' => [['type' => 'file_search']]
                            ]
                        ]
                    ]
                ]
            ]);
            $thread_id = $thread['id'];

            // Chạy Run
            $run = $this->request("threads/$thread_id/runs", 'POST', [
                'assistant_id' => $assistant_id,
                'response_format' => ['type' => 'json_object']
            ]);
            $run_id = $run['id'];

            // Chờ kết quả polling (Tăng timeout lên 5 phút cho gói Shared Hosting)
            $start_time = time();
            $timeout_seconds = 300; // 5 phút
            $is_completed = false;

            while ((time() - $start_time) < $timeout_seconds) {
                $status_check = $this->request("threads/$thread_id/runs/$run_id", 'GET');
                $status = $status_check['status'] ?? 'unknown';

                if ($status === 'completed') {
                    $is_completed = true;
                    break;
                }
                
                if (in_array($status, ['failed', 'cancelled', 'expired'])) {
                    $error = $status_check['last_error']['message'] ?? 'Run failed';
                    throw new Exception("AI Assistant Error ($status): $error");
                }
                
                // Backoff strategy: Wait 1s first, then 2s, then 3s to be polite and save resources
                $elapsed = time() - $start_time;
                if ($elapsed < 10) sleep(1);
                else if ($elapsed < 30) sleep(2);
                else sleep(3);
            }

            if (!$is_completed) {
                throw new Exception("AI Timeout: Quá thời gian chờ xử lý ($timeout_seconds s) trên OpenAI.");
            }

            // Lấy tin nhắn cuối cùng của assistant
            $messages = $this->request("threads/$thread_id/messages", 'GET');
            $content = '';
            foreach ($messages['data'] as $msg) {
                if ($msg['role'] === 'assistant') {
                    $content = $msg['content'][0]['text']['value'] ?? '';
                    break;
                }
            }
            
            if (empty($content)) throw new Exception("Không nhận được kết quả từ AI Judge (Content Empty).");

            // Làm sạch nếu AI trả về dạng ```json ... ```
            $content = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
            
            $decoded = json_decode($content, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Lỗi Giải mã JSON Vòng 1. Raw Content: " . substr($content, 0, 2000));
            }

            return $decoded;

        } finally {
            // Dọn dẹp Assistant & Thread
            if ($thread_id) try { $this->request("threads/$thread_id", 'DELETE'); } catch (Exception $e) {}
            try { $this->request("assistants/$assistant_id", 'DELETE'); } catch (Exception $e) {}
        }
    }

    /**
     * Chạy Chat Completion (Dùng cho vòng 2 - Chỉ xử lý Text)
     */
    private function runChatCompletion($model, $prompt_template, $vars) {
        $prompt = $prompt_template;
        foreach ($vars as $key => $val) {
            $prompt = str_replace("{{" . $key . "}}", $val, $prompt);
        }

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Bạn là chuyên gia Moderator cấp cao, nhiệm vụ của bạn là kiểm soát chất lượng đánh giá tài liệu.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ];

        $result = $this->request('chat/completions', 'POST', $data);
        $content = $result['choices'][0]['message']['content'] ?? '';
        
        // Làm sạch nếu AI trả về dạng ```json ... ```
        $content = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
        
        $decoded = json_decode($content, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Lỗi Giải mã JSON Vòng 2. Raw Content: " . substr($content, 0, 2000));
        }
        
        return $decoded;
    }

    private function deleteFile($file_id) {
        try { $this->request("files/$file_id", 'DELETE'); } catch (Exception $e) {}
    }

    private function getDocumentDetails($id) {
        global $conn;
        $id = intval($id);
        $res = $conn->query("SELECT d.*, u.username FROM documents d JOIN users u ON d.user_id = u.id WHERE d.id = $id");
        if (!$res) return null;
        return $res->fetch_assoc();
    }

    private function formatMetadata($doc) {
        require_once __DIR__ . '/../config/categories.php';
        $cat = getDocumentCategoryWithNames($doc['id']);
        $meta = [
            'title' => $doc['original_name'],
            'description' => $doc['description'] ?? '',
            'level' => $cat['education_level_name'] ?? '',
            'type' => $cat['doc_type_name'] ?? ''
        ];
        if (isset($cat['grade_name'])) {
            $meta['grade'] = $cat['grade_name'];
            $meta['subject'] = $cat['subject_name'];
        } else if (isset($cat['major_name'])) {
            $meta['major_group'] = $cat['major_group_name'];
            $meta['major'] = $cat['major_name'];
        }
        return json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function updateStatus($id, $status, $error = null) {
        global $conn;
        $id = intval($id);
        $status = $conn->real_escape_string($status);
        $error_sql = $error ? ", error_message = '" . $conn->real_escape_string($error) . "'" : "";
        $conn->query("UPDATE documents SET ai_status = '$status' $error_sql WHERE id = $id");
    }

    private function saveReviewResults($document_id, $judge, $moderator) {
        global $conn;
        $document_id = intval($document_id);
        $judge_json = $conn->real_escape_string(json_encode($judge, JSON_UNESCAPED_UNICODE));
        $moderator_json = $conn->real_escape_string(json_encode($moderator, JSON_UNESCAPED_UNICODE));
        $score = intval($moderator['final_scores']['overall'] ?? 0);
        $decision = $conn->real_escape_string($moderator['decision'] ?? 'REJECTED');
        
        // Tính giá VSD tự động dựa trên điểm và quyết định
        $ai_price = $this->calculateVSDPrice($score, $moderator['decision'] ?? 'REJECTED');

        $conn->query("INSERT INTO ai_reviews (document_id, judge_result, moderator_result, score, decision, status) 
                      VALUES ($document_id, '$judge_json', '$moderator_json', $score, '$decision', 'completed')");

        // Cập nhật tài liệu với giá AI đề xuất
        $conn->query("UPDATE documents SET 
                      ai_status = 'completed', 
                      ai_score = $score, 
                      ai_decision = '$decision', 
                      ai_price = $ai_price,
                      ai_judge_result = '$judge_json', 
                      ai_moderator_result = '$moderator_json' 
                      WHERE id = $document_id");
    }
}
