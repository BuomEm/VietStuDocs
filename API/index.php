<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VietStuDocs API Portal - Developer Documentation</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- UI Frameworks -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <!-- Code Highlighter -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>

    <style>
        :root {
            --vsd-red: #800000;
            --vsd-red-dark: #600000;
            --vsd-gold: #ffcc00;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0a0a0a;
            color: #e5e7eb;
            scroll-behavior: smooth;
        }

        .code-font {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Glassmorphism sidebar */
        .sidebar {
            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--glass-border);
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(128, 0, 0, 0.3);
            background: rgba(128, 0, 0, 0.02);
        }

        .method-badge {
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .method-get { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }
        .method-post { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }

        .endpoint-row {
            padding: 1rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .endpoint-row:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .active-link {
            background: rgba(128, 0, 0, 0.1) !important;
            color: var(--vsd-gold) !important;
            border-left: 4px solid var(--vsd-red);
        }

        pre[class*="language-"] {
            border-radius: 1rem !important;
            margin: 0 !important;
            background: #0f0f0f !important;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #222; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #333; }

        .gradient-text {
            background: linear-gradient(135deg, #fff 0%, #888 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .vsd-gradient {
            background: linear-gradient(135deg, var(--vsd-red) 0%, var(--vsd-red-dark) 100%);
        }
    </style>
</head>
<body class="min-h-screen">

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-80 h-screen sticky top-0 sidebar overflow-y-auto z-20 hidden lg:block">
            <div class="p-8">
                <div class="flex items-center gap-3 mb-10">
                    <div class="w-10 h-10 vsd-gradient rounded-xl flex items-center justify-center shadow-lg shadow-red-900/20">
                        <i class="fa-solid fa-code text-white"></i>
                    </div>
                    <div>
                        <h1 class="font-extrabold text-xl tracking-tight">VietStuDocs</h1>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">API Developer Portal</p>
                    </div>
                </div>

                <nav class="space-y-8">
                    <div>
                        <p class="text-[10px] font-black text-gray-600 uppercase tracking-[0.2em] mb-4">Mở đầu</p>
                        <ul class="space-y-1">
                            <li><a href="#introduction" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all active-link">Giới thiệu</a></li>
                            <li><a href="#authentication" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Xác thực (Session)</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-[10px] font-black text-gray-600 uppercase tracking-[0.2em] mb-4">VSD Specialized</p>
                        <ul class="space-y-1">
                            <li><a href="#auth-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Auth API</a></li>
                            <li><a href="#categories-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Categories</a></li>
                            <li><a href="#documents-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Documents</a></li>
                            <li><a href="#tutors-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Tutors</a></li>
                            <li><a href="#user-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">User & History</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-[10px] font-black text-gray-600 uppercase tracking-[0.2em] mb-4">Core System</p>
                        <ul class="space-y-1">
                            <li><a href="#notifications-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Thông báo</a></li>
                            <li><a href="#system-api" class="block p-3 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/5 transition-all">Hệ thống</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:p-12 lg:p-20 max-w-6xl mx-auto">
            
            <!-- Hero Section -->
            <section id="introduction" class="mb-24">
                <div class="badge badge-outline border-red-900/50 text-red-500 font-bold mb-4">v1.2.0 Released</div>
                <h2 class="text-5xl md:text-6xl font-black mb-6 gradient-text tracking-tighter">Documentation</h2>
                <p class="text-xl text-gray-400 leading-relaxed max-w-2xl">
                    Chào mừng bạn đến với cổng thông tin API của VietStuDocs. 
                    Chúng tôi cung cấp các bộ công cụ mạnh mẽ để bạn tích hợp dữ liệu tài liệu, gia sư và thông báo vào ứng dụng của mình.
                </p>
            </section>

            <!-- Auth Note -->
            <section id="authentication" class="mb-24">
                <div class="glass-card p-1">
                    <div class="bg-red-500/10 border border-red-500/20 rounded-[1.4rem] p-8">
                        <div class="flex items-start gap-4">
                            <i class="fa-solid fa-shield-halved text-red-500 text-2xl mt-1"></i>
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">Lưu ý về Xác thực</h3>
                                <p class="text-gray-400 text-sm leading-relaxed">
                                    Hầu hết các API yêu cầu đăng nhập sẽ sử dụng cơ chế **PHP Session**. 
                                    Đảm bảo rằng bạn đã gọi API Login thành công và duy trì Cookie Cookie `PHPSESSID` trong các yêu cầu tiếp theo.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Auth API Details -->
            <section id="auth-api" class="mb-32">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center border border-white/10">
                        <i class="fa-solid fa-user-lock text-gray-400"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-black text-white">Auth API</h3>
                        <p class="text-sm text-gray-500">Quản lý phiên làm việc và bảo mật</p>
                    </div>
                </div>

                <!-- Endpoint Item -->
                <div class="space-y-6">
                    <div class="glass-card p-8">
                        <div class="flex flex-wrap items-center gap-3 mb-6">
                            <span class="method-badge method-get">GET</span>
                            <code class="text-vsd-gold font-bold">/API/VSD/auth.php?action=status</code>
                        </div>
                        <p class="text-gray-400 text-sm mb-6">Kiểm tra trạng thái đăng nhập hiện tại và thông tin user cơ bản.</p>
                        <div class="bg-black/50 rounded-2xl p-4 overflow-hidden">
                             <pre><code class="language-json">{
  "status": "success",
  "logged_in": true,
  "user": { "id": 1, "username": "admin" }
}</code></pre>
                        </div>
                    </div>

                    <div class="glass-card p-8">
                        <div class="flex flex-wrap items-center gap-3 mb-6">
                            <span class="method-badge method-post">POST</span>
                            <code class="text-vsd-gold font-bold">/API/VSD/auth.php?action=login</code>
                        </div>
                        <p class="text-gray-400 text-sm mb-6">Gửi thông tin đăng nhập. Dữ liệu gửi đi dưới dạng JSON Body.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="p-4 bg-white/5 rounded-xl border border-white/5">
                                <p class="text-[10px] font-bold text-gray-500 uppercase mb-2">Request Body</p>
                                <pre><code class="language-json">{
  "email": "user@gmail.com",
  "password": "123"
}</code></pre>
                            </div>
                            <div class="p-4 bg-white/5 rounded-xl border border-white/5">
                                <p class="text-[10px] font-bold text-gray-500 uppercase mb-2">Response</p>
                                <pre><code class="language-json">{
  "status": "success",
  "message": "Logged in successfully"
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Categories API Details -->
            <section id="categories-api" class="mb-32">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center border border-white/10">
                        <i class="fa-solid fa-tags text-gray-400"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-black text-white">Categories API</h3>
                        <p class="text-sm text-gray-500">Dữ liệu phân loại môn học và ngành học</p>
                    </div>
                </div>

                <div class="glass-card p-8">
                    <div class="flex flex-wrap items-center gap-3 mb-6">
                        <span class="method-badge method-get">GET</span>
                        <code class="text-vsd-gold font-bold">/API/VSD/categories.php?action=all</code>
                    </div>
                    <p class="text-gray-400 text-sm mb-6">Trả về cấu trúc cây phân loại cho hệ thống phổ thông và đại học.</p>
                    <div class="bg-black/50 rounded-2xl p-4 overflow-hidden">
<pre><code class="language-json">{
  "status": "success",
  "data": {
    "education_levels": [...],
    "mon_hoc": {...},
    "nganh_hoc": {...},
    "loai_tai_lieu": {...}
  }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Documents API Details -->
            <section id="documents-api" class="mb-32">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center border border-white/10">
                        <i class="fa-solid fa-file-lines text-gray-400"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-black text-white">Documents API</h3>
                        <p class="text-sm text-gray-500">Tra cứu và lọc tài liệu</p>
                    </div>
                </div>

                <div class="glass-card p-8">
                    <div class="flex flex-wrap items-center gap-3 mb-6">
                        <span class="method-badge method-get">GET</span>
                        <code class="text-vsd-gold font-bold">/API/VSD/documents.php</code>
                    </div>
                    
                    <h5 class="text-sm font-bold text-white mb-4">Query Parameters</h5>
                    <div class="overflow-x-auto mb-8">
                        <table class="table table-compact w-full text-xs text-gray-400">
                            <thead>
                                <tr class="border-b border-white/10">
                                    <th class="text-vsd-gold">Parameter</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr><td><code class="text-blue-400">page</code></td><td>Integer</td><td>Trang hiện tại (Mặc định: 1)</td></tr>
                                <tr><td><code class="text-blue-400">limit</code></td><td>Integer</td><td>Số kết quả mỗi trang (Mặc định: 10)</td></tr>
                                <tr><td><code class="text-blue-400">search</code></td><td>String</td><td>Từ khóa tìm kiếm tiêu đề/mô tả</td></tr>
                                <tr><td><code class="text-blue-400">category</code></td><td>String</td><td>Lọc theo mã loại (ví dụ: mon_toan)</td></tr>
                                <tr><td><code class="text-blue-400">sort</code></td><td>Enum</td><td>`newest`, `popular`, `downloads`</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-black/50 rounded-2xl p-4 overflow-hidden">
<pre><code class="language-json">{
  "status": "success",
  "data": {
    "items": [
      {
        "id": "1",
        "original_name": "Đề thi Toán HK2",
        "thumbnail_url": "/uploads/thumbnails/t.jpg",
        "username": "tuan_anh",
        ...
      }
    ],
    "pagination": { "total_pages": 5, "current_page": 1, ... }
  }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Core Notifications -->
            <section id="notifications-api" class="mb-32">
                <h3 class="text-3xl font-black text-white mb-8">Core Notifications</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass-card p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="method-badge method-get">GET</span>
                            <code class="text-sm">/API/unread.php</code>
                        </div>
                        <p class="text-xs text-gray-400 italic">"Lấy số lượng thông báo chưa đọc và 5 tin gần nhất"</p>
                    </div>
                    <div class="glass-card p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="method-badge method-get">GET</span>
                            <code class="text-sm">/API/mark_read.php?id={id}</code>
                        </div>
                        <p class="text-xs text-gray-400 italic">"Gửi không kèm ID để đánh dấu đọc tất cả"</p>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="border-t border-white/5 pt-12 mt-32 text-center">
                <p class="text-gray-600 text-xs font-bold uppercase tracking-widest">&copy; 2026 VietStuDocs Development Team</p>
                <div class="flex justify-center gap-6 mt-6">
                    <a href="/" class="text-gray-500 hover:text-white transition-colors"><i class="fa-solid fa-globe"></i></a>
                    <a href="https://github.com" class="text-gray-500 hover:text-white transition-colors"><i class="fa-brands fa-github"></i></a>
                </div>
            </footer>
        </main>
    </div>

    <script>
        // Simple active link handler on scroll
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.sidebar nav a');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 150) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active-link');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active-link');
                }
            });
        });
    </script>
</body>
</html>
