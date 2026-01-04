<?php
session_start();
require_once __DIR__ . '/../config/auth.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$page_title = "Đăng ký làm Gia Sư - VietStuDocs";
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.75);
        --glass-border: rgba(255, 255, 255, 0.2);
        --vsd-red: #991b1b;
        --vsd-red-light: #b91c1c;
        --red-gradient: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .apply-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    /* Hero Branding */
    .apply-hero-vsd {
        text-align: center;
        margin-bottom: 60px;
        position: relative;
    }

    .apply-hero-vsd h1 {
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 1000;
        letter-spacing: -0.05em;
        line-height: 1;
        margin-bottom: 20px;
        background: var(--red-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .apply-hero-vsd p {
        font-size: 1.1rem;
        font-weight: 600;
        max-width: 600px;
        margin: 0 auto;
        opacity: 0.5;
    }

    /* Modern Progress Tracker */
    .apply-steps-vsd {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 48px;
    }

    .step-dot-vsd {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: oklch(var(--bc) / 0.1);
        transition: all 0.5s ease;
    }

    .step-dot-vsd.active {
        width: 40px;
        border-radius: 10px;
        background: var(--vsd-red);
        box-shadow: 0 0 20px rgba(153, 27, 27, 0.3);
    }

    /* Glass Form Card */
    .glass-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(40px);
        -webkit-backdrop-filter: blur(40px);
        border: 1px solid var(--glass-border);
        border-radius: 3.5rem;
        padding: 60px;
        box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
    }

    .glass-card-vsd::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: var(--red-gradient);
    }

    .vsd-form-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        opacity: 0.3;
        margin-bottom: 12px;
        display: block;
    }

    .vsd-input {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1.5rem !important;
        height: 64px;
        font-weight: 750 !important;
        padding: 0 28px !important;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1) !important;
        font-size: 1rem !important;
    }

    .vsd-textarea {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1.5rem !important;
        padding: 28px !important;
        font-weight: 600 !important;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1) !important;
        font-size: 1rem !important;
        line-height: 1.6;
    }

    .vsd-input:focus, .vsd-textarea:focus {
        border-color: var(--vsd-red) !important;
        background: oklch(var(--b1)) !important;
        box-shadow: 0 0 0 5px rgba(153, 27, 27, 0.08) !important;
        transform: translateY(-2px);
    }

    /* Pricing Section */
    .pricing-header-vsd {
        margin: 48px 0 24px;
        padding-bottom: 12px;
        border-bottom: 1px solid oklch(var(--bc) / 0.05);
    }

    .pricing-grid-vsd {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }

    .price-box-vsd {
        background: oklch(var(--bc) / 0.03);
        border: 1px solid oklch(var(--bc) / 0.05);
        padding: 24px;
        border-radius: 2rem;
        text-align: center;
        transition: all 0.3s ease;
    }

    .price-box-vsd:focus-within {
        background: oklch(var(--b1));
        border-color: var(--vsd-red);
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
    }

    .price-tag-vsd {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 16px;
        display: block;
    }

    /* Action Button */
    .vsd-btn-submit {
        background: var(--red-gradient);
        color: white;
        height: 72px;
        border-radius: 1.5rem;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: 0.25em;
        font-size: 0.95rem;
        width: 100%;
        border: none;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        box-shadow: 0 20px 40px -10px rgba(153, 27, 27, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .vsd-btn-submit:hover {
        transform: translateY(-4px) scale(1.01);
        filter: brightness(1.1);
        box-shadow: 0 30px 60px -12px rgba(153, 27, 27, 0.5);
    }

    .vsd-btn-submit i {
        transition: transform 0.3s ease;
    }

    .vsd-btn-submit:hover i {
        transform: translateX(5px);
    }

    .info-alert-vsd {
        background: rgba(153, 27, 27, 0.04);
        border-radius: 1.5rem;
        padding: 24px;
        display: flex;
        gap: 16px;
        align-items: center;
        color: var(--vsd-red);
        font-weight: 800;
        font-size: 0.85rem;
        border: 1px solid rgba(153, 27, 27, 0.08);
    }

    @media (max-width: 768px) {
        .glass-card-vsd { padding: 40px 24px; border-radius: 2rem; }
        .pricing-grid-vsd { grid-template-columns: 1fr; }
        .page-title-vsd { font-size: 2.5rem; }
    }
</style>

<body class="bg-base-100">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="apply-container animate-in fade-in duration-700">
                
                <!-- Hero Section -->
                <div class="apply-hero-vsd">
                    <h1 class="animate-in slide-in-from-top duration-700">Gia Nhập Đội Ngũ</h1>
                    <p class="animate-in slide-in-from-top duration-1000 delay-100">Chia sẻ giá trị, kết nối tri thức và xây dựng cộng đồng học thuật chuyên nghiệp.</p>
                </div>

                <div class="max-w-3xl mx-auto">
                    <!-- Progress Dots -->
                    <div class="apply-steps-vsd">
                        <div class="step-dot-vsd active"></div>
                        <div class="step-dot-vsd"></div>
                        <div class="step-dot-vsd"></div>
                    </div>

                    <!-- Main Form Card -->
                    <div class="glass-card-vsd animate-in zoom-in-95 duration-1000">
                        <form id="applyForm" class="space-y-10">
                            <input type="hidden" name="action" value="register_tutor">
                            
                            <!-- Expertise Section -->
                            <div class="space-y-8">
                                <div class="form-control">
                                    <label class="vsd-form-label">Chuyên môn hỗ trợ</label>
                                    <input type="text" name="subjects" required 
                                           placeholder="Toán Cao Cấp, Lập Trình Python, Marketing..." 
                                           class="input vsd-input w-full" />
                                    <p class="text-[9px] font-black uppercase tracking-tight opacity-20 mt-3 pl-1">Phân cách các môn học bằng dấu phẩy (,)</p>
                                </div>
                                
                                <div class="form-control">
                                    <label class="vsd-form-label">Kinh nghiệm & Giới thiệu</label>
                                    <textarea name="bio" required class="textarea vsd-textarea h-48 w-full" 
                                              placeholder="Hãy mô tả ngắn gọn về trình độ học vấn, các thành tích và phong cách hướng dẫn của bạn..."></textarea>
                                </div>
                            </div>

                            <!-- Pricing Configuration -->
                            <div class="space-y-4">
                                <div class="pricing-header-vsd">
                                    <h3 class="text-xs font-black uppercase tracking-widest opacity-40">Biểu phí đề xuất (Points)</h3>
                                </div>
                                
                                <div class="pricing-grid-vsd">
                                    <div class="price-box-vsd">
                                        <label class="price-tag-vsd text-success/60">Cơ bản</label>
                                        <div class="relative">
                                            <input type="number" name="price_basic" value="20" min="10" 
                                                   class="input vsd-input !h-12 w-full text-center pr-10" />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black opacity-20">VSD</span>
                                        </div>
                                    </div>
                                    <div class="price-box-vsd">
                                        <label class="price-tag-vsd text-info/60">Tiêu chuẩn</label>
                                        <div class="relative">
                                            <input type="number" name="price_standard" value="50" min="20" 
                                                   class="input vsd-input !h-12 w-full text-center pr-10" />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black opacity-20">VSD</span>
                                        </div>
                                    </div>
                                    <div class="price-box-vsd">
                                        <label class="price-tag-vsd text-warning/60">Cao cấp</label>
                                        <div class="relative">
                                            <input type="number" name="price_premium" value="100" min="50" 
                                                   class="input vsd-input !h-12 w-full text-center pr-10" />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black opacity-20">VSD</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Disclaimer Alert -->
                            <div class="info-alert-vsd">
                                <i class="fa-solid fa-shield-halved text-2xl"></i>
                                <div>
                                    <h4 class="font-black uppercase text-[10px] tracking-widest mb-1">Quy trình xét duyệt</h4>
                                    <p class="opacity-70 text-sm">Hồ sơ sẽ được ban quản trị kiểm tra thông tin trong 24h trước khi kích hoạt chính thức.</p>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="pt-6">
                                <button type="submit" class="vsd-btn-submit">
                                    Gửi Hồ Sơ Xét Duyệt <i class="fa-solid fa-arrow-right-long"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>
        
        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>

    <script>
    document.getElementById('applyForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Đang xử lý...';

        const formData = new FormData(this);
        
        try {
            const res = await fetch('/handler/tutor_handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if(data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => window.location.href = '/tutors/', 1500);
            } else {
                showAlert(data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch(err) {
            showAlert('Có lỗi xảy ra trong quá trình kết nối', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
    </script>
</body>
</html>
