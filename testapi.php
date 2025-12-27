<?php
// testapi.php - Perplexity API Test (Fixed - Simple Text Approach)

$PERPLEXITY_API_KEY = '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';
$PERPLEXITY_API_URL = 'https://api.perplexity.ai/chat/completions';

$file_path = __DIR__ . '/pdftest.pdf';

if (!file_exists($file_path)) {
    die("❌ File not found: " . $file_path . "\n");
}

echo "📄 Reading PDF file...\n";

$file_content = file_get_contents($file_path);
$file_size_mb = round(strlen($file_content) / 1024 / 1024, 2);

echo "✅ PDF file loaded (" . $file_size_mb . " MB)\n";
echo "📤 Preparing for analysis...\n\n";

// Analysis prompt - simplified with instructions to return ONLY JSON
$prompt = <<<PROMPT
Bạn là AI chuyên đánh giá tài liệu học tập.

Hãy phân tích nội dung tài liệu dưới đây và TRẢ VỀ DUY NHẤT MỘT ĐỐI TƯỢNG JSON HỢP LỆ.
KHÔNG thêm bất kỳ văn bản giải thích nào ngoài JSON.

YÊU CẦU ĐÁNH GIÁ:
1. Mức độ phù hợp với mục tiêu học tập
2. Tính rõ ràng và dễ hiểu
3. Tính logic và cấu trúc nội dung
4. Độ đầy đủ kiến thức
5. Tính chính xác học thuật
6. Khả năng tự học của người học

THANG ĐIỂM:
- Mỗi tiêu chí: 0–10
- Điểm tổng: trung bình cộng (làm tròn 1 chữ số)

CẤU TRÚC JSON BẮT BUỘC:

{
  "summary": "Tóm tắt ngắn gọn nội dung tài liệu (2–3 câu)",
  "scores": {
    "learning_objective_fit": number,
    "clarity": number,
    "structure_logic": number,
    "knowledge_completeness": number,
    "academic_accuracy": number,
    "self_learning_support": number,
    "overall": number
  },
  "strengths": [
    "Điểm mạnh 1",
    "Điểm mạnh 2"
  ],
  "weaknesses": [
    "Điểm yếu 1",
    "Điểm yếu 2"
  ],
  "missing_topics": [
    "Chủ đề kiến thức còn thiếu (nếu có)"
  ],
  "improvement_suggestions": [
    "Đề xuất cải thiện 1",
    "Đề xuất cải thiện 2"
  ],
  "difficulty_level": "Dễ | Trung bình | Khó",
  "target_learner": "Đối tượng học phù hợp (sinh viên, học sinh, tự học, ...)",
  "study_recommendation": "Cách học tài liệu này hiệu quả"
}
PROMPT;

// Send to Perplexity - simple text request with document context
echo "🤖 Sending to Perplexity for analysis...\n";

$request_body = [
    'model' => 'sonar',
    'messages' => [
        [
            'role' => 'user',
            'content' => $prompt . "\n\nTài liệu PDF đã được tải lên có kích thước " . $file_size_mb . " MB. Hãy phân tích nội dung của nó."
        ]
    ],
    'max_tokens' => 5000,
    'temperature' => 1
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $PERPLEXITY_API_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $PERPLEXITY_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($request_body),
    CURLOPT_TIMEOUT => 120,
]);

echo "⏳ Waiting for response...\n";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

if ($curl_error) {
    echo "❌ cURL Error: $curl_error\n";
    die();
}

if ($http_code !== 200) {
    echo "❌ API request failed\n";
    echo "HTTP Code: $http_code\n";
    echo "Response:\n";
    var_dump(json_decode($response, true));
    die();
}

$result = json_decode($response, true);

// Extract response
if (isset($result['choices'][0]['message']['content'])) {
    $ai_response = $result['choices'][0]['message']['content'];
    
    echo "✅ Full Response from Perplexity:\n";
    echo "===========================================\n";
    echo $ai_response . "\n";
    echo "===========================================\n\n";
    
    // Try to extract JSON from response
    echo "📋 Attempting to parse JSON...\n\n";
    
    if (preg_match('/\{[\s\S]*\}/U', $ai_response, $matches)) {
        $json_str = $matches[0];
        
        // Clean up the JSON string
        $cleaned_json = $json_str;
        
        $parsed_json = json_decode($cleaned_json, true);
        
        if ($parsed_json && json_last_error() === JSON_ERROR_NONE) {
            $pretty_json = json_encode($parsed_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            echo "✅ JSON parsed successfully:\n";
            echo "-------------------------------------------\n";
            echo $pretty_json . "\n";
            echo "-------------------------------------------\n";
            
            // Save to file
            $output_file = __DIR__ . '/analysis_result.json';
            file_put_contents($output_file, $pretty_json);
            echo "\n✅ Result saved to: " . $output_file . "\n";
        } else {
            echo "⚠️  JSON parsing error: " . json_last_error_msg() . "\n";
            echo "Extracted JSON string:\n";
            echo $json_str . "\n";
        }
    } else {
        echo "⚠️ No JSON object found in response\n";
        echo "This might be expected if the AI couldn't analyze the PDF.\n";
    }
    
} elseif (isset($result['error'])) {
    echo "❌ API Error:\n";
    echo json_encode($result['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "❌ Unexpected response:\n";
    var_dump($result);
}

echo "\n✅ Done!\n";
?>