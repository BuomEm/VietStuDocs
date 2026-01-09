<?php
/**
 * API Playground - Postman-like interface for testing API
 * Chỉ cho phép user có API key truy cập
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';

// Check if user has API key
$has_api_key = false;
if (isUserLoggedIn()) {
    global $VSD;
    $user_id = getCurrentUserId();
    
    // Use prepared statement
    $stmt = $VSD->prepare("SELECT COUNT(*) as count FROM api_keys WHERE user_id = ? AND status = 'active'");
    if ($stmt) {
        $stmt->execute([$user_id]);
        $result = $stmt->fetch_assoc();
        $has_api_key = intval($result['count'] ?? 0) > 0;
        $stmt->close();
    } else {
        // Fallback: check without prepared statement (for compatibility)
        $user_id_escaped = $VSD->escape($user_id);
        $result = $VSD->get_row("SELECT COUNT(*) as count FROM api_keys WHERE user_id = '$user_id_escaped' AND status = 'active'");
        $has_api_key = intval($result['count'] ?? 0) > 0;
    }
}

// Redirect if no API key
if (!isUserLoggedIn()) {
    header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

if (!$has_api_key) {
    header('Location: /admin/api-keys.php?error=no_api_key&message=Bạn cần tạo API key để sử dụng API Playground');
    exit;
}

$page_title = "API Playground - Test API";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$api_base_url = $base_url . '/API/v1';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Highlight.js for JSON syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/json.min.js"></script>
    
    <style>
        .hljs { padding: 1rem; border-radius: 0.5rem; }
        .method-badge {
            min-width: 80px;
            text-align: center;
        }
        .method-GET { @apply bg-blue-500; }
        .method-POST { @apply bg-green-500; }
        .method-PUT { @apply bg-yellow-500; }
        .method-PATCH { @apply bg-orange-500; }
        .method-DELETE { @apply bg-red-500; }
        .response-success { border-left: 4px solid #10b981; }
        .response-error { border-left: 4px solid #ef4444; }
        .endpoint-item:hover { @apply bg-base-200; }
    </style>
</head>
<body class="bg-base-300 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="bg-base-100 shadow-xl rounded-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">
                        <i class="fa-solid fa-flask"></i>
                        API Playground
                    </h1>
                    <p class="text-base-content/70">Test API endpoints giống Postman</p>
                </div>
                <div class="text-right">
                    <div class="badge badge-success badge-lg">API Key Ready</div>
                    <div class="text-sm text-base-content/70 mt-2">
                        Base URL: <code class="bg-base-200 px-2 py-1 rounded"><?= $api_base_url ?></code>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Panel: Endpoints & Config -->
            <div class="lg:col-span-1 space-y-4">
                <!-- API Key Input -->
                <div class="bg-base-100 shadow-xl rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-3">
                        <i class="fa-solid fa-key"></i> API Key
                    </h2>
                    <div class="form-control">
                        <input type="password" 
                               id="apiKeyInput" 
                               placeholder="Nhập API key của bạn"
                               class="input input-bordered w-full font-mono text-sm">
                        <label class="label">
                            <span class="label-text-alt text-base-content/70">
                                Lưu vào browser để không cần nhập lại
                            </span>
                        </label>
                    </div>
                    <button onclick="saveApiKey()" class="btn btn-sm btn-primary w-full mt-2">
                        <i class="fa-solid fa-save"></i> Lưu API Key
                    </button>
                </div>

                <!-- Pre-configured Endpoints -->
                <div class="bg-base-100 shadow-xl rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-3">
                        <i class="fa-solid fa-bookmark"></i> Endpoints
                    </h2>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Auth Endpoints -->
                        <div class="mb-2">
                            <div class="text-xs font-semibold text-base-content/70 uppercase mb-1">Authentication</div>
                            <div class="endpoint-item p-2 rounded cursor-pointer" onclick="loadEndpoint('GET', '/auth/profile')">
                                <span class="badge method-badge method-GET text-white">GET</span>
                                <span class="text-sm ml-2">/auth/profile</span>
                            </div>
                        </div>

                        <!-- Document Endpoints -->
                        <div class="mb-2">
                            <div class="text-xs font-semibold text-base-content/70 uppercase mb-1">Documents</div>
                            <div class="endpoint-item p-2 rounded cursor-pointer" onclick="loadEndpoint('GET', '/documents')">
                                <span class="badge method-badge method-GET text-white">GET</span>
                                <span class="text-sm ml-2">/documents</span>
                            </div>
                            <div class="endpoint-item p-2 rounded cursor-pointer" onclick="loadEndpoint('GET', '/documents?page=1&limit=10&status=approved&sort=popular')">
                                <span class="badge method-badge method-GET text-white">GET</span>
                                <span class="text-sm ml-2">/documents (with filters)</span>
                            </div>
                            <div class="endpoint-item p-2 rounded cursor-pointer" onclick="loadEndpoint('GET', '/documents/1')">
                                <span class="badge method-badge method-GET text-white">GET</span>
                                <span class="text-sm ml-2">/documents/{id}</span>
                            </div>
                        </div>

                        <!-- Examples -->
                        <div class="mb-2">
                            <div class="text-xs font-semibold text-base-content/70 uppercase mb-1">Examples</div>
                            <div class="endpoint-item p-2 rounded cursor-pointer" onclick="loadExample('list_documents')">
                                <i class="fa-solid fa-code"></i>
                                <span class="text-sm ml-2">List Documents với Search</span>
                            </div>
                            <div class="endpoint-item p-2 rounded cursor-pointer" onclick="loadExample('get_document')">
                                <i class="fa-solid fa-code"></i>
                                <span class="text-sm ml-2">Get Single Document</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Request Builder & Response -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Request Builder -->
                <div class="bg-base-100 shadow-xl rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4">
                        <i class="fa-solid fa-code"></i> Request
                    </h2>
                    
                    <!-- Method & URL -->
                    <div class="flex gap-2 mb-4">
                        <select id="methodSelect" class="select select-bordered method-badge">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="PATCH">PATCH</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                        <input type="text" 
                               id="urlInput" 
                               value="/documents"
                               placeholder="/documents"
                               class="input input-bordered flex-1 font-mono">
                        <button onclick="sendRequest()" class="btn btn-primary">
                            <i class="fa-solid fa-paper-plane"></i>
                            Send
                        </button>
                    </div>

                    <!-- Tabs: Headers, Body -->
                    <div class="tabs tabs-bordered mb-4">
                        <a class="tab tab-active" onclick="switchTab('headers')">Headers</a>
                        <a class="tab" onclick="switchTab('body')">Body</a>
                        <a class="tab" onclick="switchTab('params')">Query Params</a>
                    </div>

                    <!-- Headers Tab -->
                    <div id="headersTab" class="tab-content">
                        <div class="form-control mb-2">
                            <div class="flex gap-2">
                                <input type="text" id="headerKey" placeholder="Header name" class="input input-bordered flex-1">
                                <input type="text" id="headerValue" placeholder="Header value" class="input input-bordered flex-1">
                                <button onclick="addHeader()" class="btn btn-sm">Add</button>
                            </div>
                        </div>
                        <div id="headersList" class="space-y-2">
                            <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                                <span class="font-mono text-sm"><strong>Authorization:</strong> Bearer YOUR_API_KEY</span>
                                <button onclick="removeHeader(this)" class="btn btn-xs btn-error">Remove</button>
                            </div>
                            <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                                <span class="font-mono text-sm"><strong>Content-Type:</strong> application/json</span>
                                <button onclick="removeHeader(this)" class="btn btn-xs btn-error">Remove</button>
                            </div>
                        </div>
                    </div>

                    <!-- Body Tab -->
                    <div id="bodyTab" class="tab-content hidden">
                        <textarea id="bodyInput" 
                                  class="textarea textarea-bordered w-full h-48 font-mono text-sm"
                                  placeholder='{"key": "value"}'></textarea>
                        <label class="label">
                            <span class="label-text-alt text-base-content/70">
                                JSON format
                            </span>
                        </label>
                    </div>

                    <!-- Query Params Tab -->
                    <div id="paramsTab" class="tab-content hidden">
                        <div class="form-control mb-2">
                            <div class="flex gap-2">
                                <input type="text" id="paramKey" placeholder="Parameter name" class="input input-bordered flex-1">
                                <input type="text" id="paramValue" placeholder="Parameter value" class="input input-bordered flex-1">
                                <button onclick="addParam()" class="btn btn-sm">Add</button>
                            </div>
                        </div>
                        <div id="paramsList" class="space-y-2"></div>
                    </div>
                </div>

                <!-- Response Viewer -->
                <div class="bg-base-100 shadow-xl rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4">
                        <i class="fa-solid fa-server"></i> Response
                        <span id="responseStatus" class="badge badge-sm ml-2"></span>
                        <span id="responseTime" class="text-xs text-base-content/70 ml-2"></span>
                    </h2>
                    <div id="responseContainer" class="bg-base-200 rounded p-4 min-h-[400px]">
                        <p class="text-base-content/70 text-center">Response sẽ hiển thị ở đây...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // API Configuration
        const API_BASE_URL = '<?= $api_base_url ?>';
        const headers = {
            'Authorization': 'Bearer ',
            'Content-Type': 'application/json'
        };
        const queryParams = {};

        // Load saved API key
        window.addEventListener('DOMContentLoaded', () => {
            const savedKey = localStorage.getItem('api_playground_key');
            if (savedKey) {
                document.getElementById('apiKeyInput').value = savedKey;
                headers['Authorization'] = 'Bearer ' + savedKey;
            }
        });

        function saveApiKey() {
            const key = document.getElementById('apiKeyInput').value.trim();
            if (key) {
                localStorage.setItem('api_playground_key', key);
                headers['Authorization'] = 'Bearer ' + key;
                alert('API key đã được lưu!');
            }
        }

        function loadEndpoint(method, endpoint) {
            document.getElementById('methodSelect').value = method;
            document.getElementById('urlInput').value = endpoint;
            
            // Update Authorization header if API key exists
            const savedKey = localStorage.getItem('api_playground_key');
            if (savedKey) {
                headers['Authorization'] = 'Bearer ' + savedKey;
                updateHeadersDisplay();
            }
        }

        function loadExample(exampleName) {
            const examples = {
                'list_documents': {
                    method: 'GET',
                    url: '/documents?page=1&limit=10&status=approved&sort=popular&search=math',
                    params: {
                        'page': '1',
                        'limit': '10',
                        'status': 'approved',
                        'sort': 'popular',
                        'search': 'math'
                    }
                },
                'get_document': {
                    method: 'GET',
                    url: '/documents/1',
                    params: {}
                }
            };

            if (examples[exampleName]) {
                const ex = examples[exampleName];
                document.getElementById('methodSelect').value = ex.method;
                document.getElementById('urlInput').value = ex.url.split('?')[0];
                
                // Load query params
                queryParams = {};
                if (ex.params) {
                    Object.keys(ex.params).forEach(key => {
                        queryParams[key] = ex.params[key];
                        addParamDisplay(key, ex.params[key]);
                    });
                }
            }
        }

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('tab-active');
            });

            // Show selected tab
            document.getElementById(tabName + 'Tab').classList.remove('hidden');
            event.target.classList.add('tab-active');
        }

        function addHeader() {
            const key = document.getElementById('headerKey').value.trim();
            const value = document.getElementById('headerValue').value.trim();
            if (key && value) {
                headers[key] = value;
                addHeaderDisplay(key, value);
                document.getElementById('headerKey').value = '';
                document.getElementById('headerValue').value = '';
            }
        }

        function addHeaderDisplay(key, value) {
            const div = document.createElement('div');
            div.className = 'flex justify-between items-center p-2 bg-base-200 rounded';
            div.innerHTML = `
                <span class="font-mono text-sm"><strong>${key}:</strong> ${value}</span>
                <button onclick="removeHeader(this)" class="btn btn-xs btn-error">Remove</button>
            `;
            document.getElementById('headersList').appendChild(div);
        }

        function removeHeader(btn) {
            const div = btn.parentElement;
            const text = div.querySelector('strong').textContent.replace(':', '');
            delete headers[text];
            div.remove();
        }

        function updateHeadersDisplay() {
            document.getElementById('headersList').innerHTML = '';
            Object.keys(headers).forEach(key => {
                addHeaderDisplay(key, headers[key]);
            });
        }

        function addParam() {
            const key = document.getElementById('paramKey').value.trim();
            const value = document.getElementById('paramValue').value.trim();
            if (key) {
                queryParams[key] = value || '';
                addParamDisplay(key, value);
                document.getElementById('paramKey').value = '';
                document.getElementById('paramValue').value = '';
            }
        }

        function addParamDisplay(key, value) {
            const div = document.createElement('div');
            div.className = 'flex justify-between items-center p-2 bg-base-200 rounded';
            div.innerHTML = `
                <span class="font-mono text-sm"><strong>${key}:</strong> ${value || ''}</span>
                <button onclick="removeParam(this)" class="btn btn-xs btn-error">Remove</button>
            `;
            document.getElementById('paramsList').appendChild(div);
        }

        function removeParam(btn) {
            const div = btn.parentElement;
            const text = div.querySelector('strong').textContent.replace(':', '');
            delete queryParams[text];
            div.remove();
        }

        async function sendRequest() {
            const method = document.getElementById('methodSelect').value;
            let url = document.getElementById('urlInput').value.trim();
            
            // Build query string
            const params = new URLSearchParams(queryParams).toString();
            if (params) {
                url += (url.includes('?') ? '&' : '?') + params;
            }

            // Get body for POST/PUT/PATCH
            let body = null;
            if (['POST', 'PUT', 'PATCH'].includes(method)) {
                const bodyText = document.getElementById('bodyInput').value.trim();
                if (bodyText) {
                    try {
                        body = JSON.parse(bodyText);
                        body = JSON.stringify(body, null, 2);
                    } catch (e) {
                        alert('Invalid JSON in body: ' + e.message);
                        return;
                    }
                }
            }

            // Prepare headers
            const requestHeaders = {...headers};
            
            // Remove Content-Type if no body
            if (!body) {
                delete requestHeaders['Content-Type'];
            }

            // Display loading
            const responseContainer = document.getElementById('responseContainer');
            responseContainer.innerHTML = '<p class="text-center">Đang gửi request...</p>';
            
            const startTime = performance.now();

            try {
                const response = await fetch(API_BASE_URL + url, {
                    method: method,
                    headers: requestHeaders,
                    body: body
                });

                const endTime = performance.now();
                const duration = Math.round(endTime - startTime);

                const responseText = await response.text();
                let responseJson;
                try {
                    responseJson = JSON.parse(responseText);
                } catch (e) {
                    responseJson = { raw: responseText };
                }

                // Display response
                const formattedJson = JSON.stringify(responseJson, null, 2);
                responseContainer.innerHTML = `<pre><code class="language-json">${escapeHtml(formattedJson)}</code></pre>`;
                
                // Highlight syntax
                hljs.highlightElement(responseContainer.querySelector('code'));

                // Update status badge
                const statusBadge = document.getElementById('responseStatus');
                statusBadge.textContent = response.status + ' ' + response.statusText;
                statusBadge.className = 'badge badge-sm ' + (response.ok ? 'badge-success' : 'badge-error');

                // Update response time
                document.getElementById('responseTime').textContent = `${duration}ms`;

                // Add border color based on status
                responseContainer.className = `bg-base-200 rounded p-4 min-h-[400px] ${response.ok ? 'response-success' : 'response-error'}`;

            } catch (error) {
                responseContainer.innerHTML = `<pre class="text-error"><code>Error: ${escapeHtml(error.message)}</code></pre>`;
                document.getElementById('responseStatus').textContent = 'Error';
                document.getElementById('responseStatus').className = 'badge badge-sm badge-error';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize
        updateHeadersDisplay();
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>

