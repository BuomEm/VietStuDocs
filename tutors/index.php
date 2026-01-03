<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

$page_title = "Tìm Gia Sư - VietStuDocs";
$current_page = 'tutors';
$tutors = getActiveTutors($_GET);
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
        --vsd-red: #991b1b;
        --vsd-red-light: #b91c1c;
        --red-gradient: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .tutors-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    /* Hero Section */
    .tutors-hero {
        position: relative;
        padding: 80px 40px;
        border-radius: 4rem;
        background: var(--red-gradient);
        color: white;
        overflow: hidden;
        margin-bottom: 60px;
        text-align: center;
        box-shadow: 0 40px 100px -20px rgba(153, 27, 27, 0.3);
    }

    .tutors-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        pointer-events: none;
    }

    .tutors-hero h1 {
        font-size: clamp(2.5rem, 6vw, 4.5rem);
        font-weight: 1000;
        letter-spacing: -0.05em;
        line-height: 1;
        margin-bottom: 24px;
        position: relative;
    }

    .tutors-hero p {
        font-size: 1.25rem;
        opacity: 0.9;
        max-width: 700px;
        margin: 0 auto;
        font-weight: 600;
        position: relative;
    }

    /* Layout */
    .tutors-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 40px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .tutors-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Glass Card Style */
    .glass-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid var(--glass-border);
        border-radius: 2.5rem;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
    }

    /* Sidebar Filter */
    .filter-sidebar {
        position: sticky;
        top: 100px;
        z-index: 10;
    }

    .filter-title {
        font-size: 0.85rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: oklch(var(--bc) / 0.4);
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .filter-title i {
        color: var(--vsd-red);
    }

    .vsd-input {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1.25rem !important;
        height: 56px !important;
        font-weight: 700 !important;
        transition: all 0.3s ease !important;
    }

    .vsd-input:focus {
        border-color: var(--vsd-red) !important;
        box-shadow: 0 0 0 4px rgba(153, 27, 27, 0.1) !important;
    }

    .vsd-btn-search {
        background: var(--vsd-red);
        color: white;
        border: none;
        height: 56px;
        border-radius: 1.25rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 25px -5px rgba(153, 27, 27, 0.3);
    }

    .vsd-btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px -8px rgba(153, 27, 27, 0.4);
        filter: brightness(1.1);
    }

    /* Tutor Cards */
    .tutor-card-vsd {
        display: flex;
        gap: 28px;
        padding: 28px;
        position: relative;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        margin-bottom: 20px;
    }

    .tutor-card-vsd:hover {
        transform: translateY(-5px);
        background: var(--glass-bg);
        border-color: rgba(153, 27, 27, 0.2);
        box-shadow: 0 30px 60px -15px rgba(0,0,0,0.08);
    }

    .tutor-avatar-vsd {
        width: 140px;
        height: 140px;
        flex-shrink: 0;
        position: relative;
        z-index: 5;
    }

    .tutor-img-wrapper {
        width: 100%;
        height: 100%;
        border-radius: 2.5rem;
        overflow: hidden;
        border: 4px solid white;
        box-shadow: 0 15px 30px -5px rgba(0,0,0,0.1);
        position: relative;
    }

    [data-theme="dark"] .tutor-img-wrapper {
        border-color: #1e293b;
    }

    [data-theme="dark"] .tutor-avatar-vsd {
        border-color: #1e293b;
    }

    .tutor-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .tutor-rating-badge {
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        padding: 6px 14px;
        border-radius: 100px;
        font-weight: 900;
        font-size: 11px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 6px;
        color: #1e293b;
        white-space: nowrap;
        border: 1px solid rgba(255, 255, 255, 0.8);
        z-index: 2;
    }

    [data-theme="dark"] .tutor-rating-badge {
        background: #1e293b;
        color: white;
        border-color: rgba(255,255,255,0.1);
    }

    .tutor-info-vsd {
        flex: 1;
    }

    .tutor-name-vsd {
        font-size: 1.75rem;
        font-weight: 1000;
        letter-spacing: -0.04em;
        margin-bottom: 4px;
        line-height: 1.2;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .tutor-status-vsd {
        font-size: 0.8rem;
        font-weight: 800;
        color: oklch(var(--bc) / 0.5);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .tutor-status-vsd i {
        color: #22c55e;
        font-size: 8px;
    }

    .subject-tag {
        padding: 6px 12px;
        border-radius: 100px;
        background: rgba(153, 27, 27, 0.05);
        color: var(--vsd-red);
        font-weight: 800;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border: 1px solid rgba(153, 27, 27, 0.1);
    }

    .tutor-bio-vsd {
        font-size: 0.9rem;
        line-height: 1.6;
        color: oklch(var(--bc) / 0.6);
        margin-bottom: 24px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Pricing Table inside Card */
    .tutor-pricing-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        background: oklch(var(--bc) / 0.03);
        padding: 16px;
        border-radius: 1.5rem;
        margin-bottom: 24px;
        max-width: 450px;
    }

    .pricing-item-vsd {
        text-align: center;
    }

    .pricing-label-vsd {
        font-size: 8px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--bc) / 0.4);
        margin-bottom: 2px;
    }

    .pricing-value-vsd {
        font-weight: 900;
        font-size: 0.95rem;
        color: oklch(var(--bc));
    }

    .vsd-btn-ask {
        background: var(--vsd-red);
        color: white;
        border: none;
        height: 52px;
        border-radius: 1.25rem;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding: 0 32px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 15px 30px -8px rgba(153, 27, 27, 0.3);
        font-size: 0.85rem;
    }

    .vsd-btn-ask:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 40px -12px rgba(153, 27, 27, 0.4);
        filter: brightness(1.1);
    }

    /* Modal Styling - Deep Red */
    .modal-box-vsd {
        border-radius: 3rem !important;
        padding: 0 !important;
        background: var(--glass-bg) !important;
        backdrop-filter: blur(50px) !important;
        border: 1px solid var(--glass-border) !important;
        overflow: visible !important;
    }

    .modal-header-vsd {
        padding: 48px 48px 24px;
        text-align: center;
    }

    .modal-body-vsd {
        padding: 0 48px 48px;
    }

    .form-label-vsd {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: oklch(var(--bc) / 0.4);
        margin-bottom: 8px;
        display: block;
    }

    .package-option {
        cursor: pointer;
        padding: 20px;
        border-radius: 1.5rem;
        border: 2px solid oklch(var(--bc) / 0.05);
        transition: all 0.3s ease;
        text-align: center;
    }

    .package-option:hover {
        background: oklch(var(--bc) / 0.02);
        border-color: oklch(var(--bc) / 0.1);
    }

    input[type="radio"]:checked + .package-option {
        border-color: var(--vsd-red);
        background: rgba(153, 27, 27, 0.03);
    }

    @media (max-width: 768px) {
        .tutor-card-vsd {
            flex-direction: column;
            gap: 24px;
            padding: 30px;
        }
        .tutor-avatar-vsd {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        .tutor-name-vsd {
            text-align: center;
            justify-content: center;
            font-size: 1.75rem;
        }
        .tutor-status-vsd {
            justify-content: center;
        }
        .vsd-btn-ask {
            width: 100%;
        }
    }
</style>

<body class="bg-base-100">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="tutors-container">
                
                <!-- Hero Section -->
                <div class="tutors-hero animate-in fade-in zoom-in-95 duration-700">
                    <h1>Gia Sư Ưu Tú</h1>
                    <p>Hỏi bài trực tiếp với những gia sư giỏi nhất. Nhận lời giải chi tiết và hỗ trợ 1-1 ngay lập tức.</p>
                </div>

                <div class="tutors-layout">
                    
                    <!-- Sidebar Filter -->
                    <aside class="filter-sidebar">
                        <div class="glass-card-vsd animate-in slide-in-from-left duration-700">
                            <h2 class="filter-title"><i class="fa-solid fa-sliders"></i> Bộ lọc gia sư</h2>
                            <form action="" method="GET" class="space-y-6">
                                <div class="space-y-2">
                                    <label class="form-label-vsd">Môn học cần tìm</label>
                                    <input type="text" name="subject" value="<?= htmlspecialchars($_GET['subject'] ?? '') ?>" placeholder="Toán, Lý, Tiếng Anh..." class="input vsd-input w-full" />
                                </div>
                                <div class="space-y-3 pt-4">
                                    <button type="submit" class="vsd-btn-search">Tìm kiếm gia sư</button>
                                    <a href="tutors" class="btn btn-ghost w-full rounded-2xl font-black text-[10px] tracking-widest opacity-40 hover:opacity-100 uppercase">Xóa bộ lọc</a>
                                </div>
                            </form>
                            
                            <?php 
                            $logged_in_id = getCurrentUserId();
                            if (!$logged_in_id || !isTutor($logged_in_id)): 
                            ?>
                            <div class="mt-12 pt-12 border-t border-base-content/5 text-center">
                                <p class="text-xs font-black opacity-30 uppercase tracking-widest mb-4">Bạn muốn dạy học?</p>
                                <a href="/tutors/apply" class="btn btn-outline border-2 border-primary/20 hover:bg-primary/5 rounded-2xl w-full font-black text-xs h-14 uppercase tracking-wider">Trở thành Gia Sư</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <!-- Tutor List -->
                    <div class="tutor-listing">
                        <div class="flex justify-between items-center mb-10">
                            <h2 class="text-xs font-black uppercase tracking-[0.3em] opacity-30">Đang hiển thị <?= count($tutors) ?> gia sư phù hợp</h2>
                        </div>
                        
                        <?php if(empty($tutors)): ?>
                            <div class="glass-card-vsd border-dashed border-2 flex flex-col items-center justify-center py-20 opacity-60">
                                <i class="fa-solid fa-magnifying-glass text-4xl mb-6"></i>
                                <h3 class="font-black text-xl">Không tìm thấy gia sư</h3>
                                <p class="text-sm font-bold opacity-60">Thử tìm kiếm với một môn học khác nhé!</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-8">
                                <?php foreach($tutors as $tutor): ?>
                                    <div class="glass-card-vsd tutor-card-vsd animate-in slide-in-from-bottom duration-500">
                                        <!-- Avatar Column -->
                                        <div class="tutor-avatar-vsd">
                                            <div class="tutor-img-wrapper">
                                                <?php if(!empty($tutor['avatar']) && file_exists('../uploads/avatars/' . $tutor['avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $tutor['avatar'] ?>" alt="Tutor" />
                                                <?php else: ?>
                                                    <div class="w-full h-full bg-primary/10 flex items-center justify-center">
                                                        <span class="text-4xl font-black text-primary"><?= strtoupper(substr($tutor['username'], 0, 1)) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="tutor-rating-badge">
                                                <i class="fa-solid fa-star text-yellow-500"></i> <?= $tutor['rating'] ?> <span>(<?= $tutor['completed_count'] ?>)</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Info Column -->
                                        <div class="tutor-info-vsd">
                                            <h3 class="tutor-name-vsd">
                                                <?= htmlspecialchars($tutor['username']) ?>
                                                <i class="fa-solid fa-circle-check text-blue-500 text-xl" title="Gia sư chính thức"></i>
                                            </h3>
                                            <div class="tutor-status-vsd">
                                                <i class="fa-solid fa-circle"></i> Đang hoạt động • Chuyên gia học thuật
                                            </div>
                                            
                                            <div class="flex flex-wrap gap-2 mb-6">
                                                <?php 
                                                $subjects = explode(',', $tutor['subjects']); 
                                                foreach($subjects as $subj): 
                                                ?>
                                                    <span class="subject-tag"><?= trim(htmlspecialchars($subj)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <p class="tutor-bio-vsd"><?= htmlspecialchars($tutor['bio']) ?></p>
                                            
                                            <div class="tutor-pricing-grid">
                                                <div class="pricing-item-vsd">
                                                    <div class="pricing-label-vsd">Cơ bản</div>
                                                    <div class="pricing-value-vsd text-success"><?= $tutor['price_basic'] ?> pts</div>
                                                </div>
                                                <div class="pricing-item-vsd border-x border-base-content/5">
                                                    <div class="pricing-label-vsd">Tiêu chuẩn</div>
                                                    <div class="pricing-value-vsd text-info"><?= $tutor['price_standard'] ?> pts</div>
                                                </div>
                                                <div class="pricing-item-vsd">
                                                    <div class="pricing-label-vsd">Cao cấp</div>
                                                    <div class="pricing-value-vsd text-warning"><?= $tutor['price_premium'] ?> pts</div>
                                                </div>
                                            </div>
                                            
                                            <button onclick="openRequestModal(<?= $tutor['user_id'] ?>, '<?= htmlspecialchars($tutor['username']) ?>')" class="vsd-btn-ask">
                                                Đặt câu hỏi <i class="fa-solid fa-arrow-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        
        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>

    <!-- Request Modal -->
    <dialog id="request_modal" class="modal">
        <div class="modal-box modal-box-vsd max-w-2xl">
            <div class="modal-header-vsd">
                <h3 class="font-black text-3xl uppercase tracking-tighter gradient-text-red">Đặt câu hỏi</h3>
                <p class="text-[10px] opacity-40 font-black uppercase tracking-widest mt-2">Dành cho gia sư: <span id="modal_tutor_name" class="text-primary"></span></p>
            </div>
            
            <form id="requestForm" class="modal-body-vsd space-y-6" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_request">
                <input type="hidden" name="tutor_id" id="modal_tutor_id">
                
                <div class="form-control">
                    <label class="form-label-vsd">Tiêu đề câu hỏi</label>
                    <input type="text" name="title" required class="input vsd-input w-full" placeholder="Ví dụ: Giúp em giải bài toán tích phân lớp 12">
                </div>
                
                <div class="form-control">
                    <label class="form-label-vsd">Nội dung chi tiết</label>
                    <textarea name="content" required class="textarea vsd-input h-32 py-4" placeholder="Mô tả chi tiết câu hỏi của bạn, càng chi tiết càng tốt..."></textarea>
                </div>
                
                <div class="form-control">
                    <label class="form-label-vsd mb-4">Gói câu hỏi</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="package_type" value="basic" class="hidden" checked />
                            <div class="package-option">
                                <div class="font-black text-sm text-success">BASIC</div>
                                <div class="text-[9px] font-bold opacity-40 mt-1">TRẢ LỜI NGẮN</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="package_type" value="standard" class="hidden" />
                            <div class="package-option">
                                <div class="font-black text-sm text-info">STANDARD</div>
                                <div class="text-[9px] font-bold opacity-40 mt-1">GIẢI CHI TIẾT</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="package_type" value="premium" class="hidden" />
                            <div class="package-option">
                                <div class="font-black text-sm text-warning">PREMIUM</div>
                                <div class="text-[9px] font-bold opacity-40 mt-1">GIẢI + FILE</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-control hidden" id="file_upload_div">
                    <label class="form-label-vsd">Đính kèm tài liệu (Ảnh/PDF/Word)</label>
                    <input type="file" name="attachment" class="file-input file-input-bordered w-full vsd-input !p-0" />
                    <p class="text-[9px] font-bold opacity-40 mt-2 uppercase tracking-tight">Tối đa 5MB. Chỉ dành cho gói Premium.</p>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" class="btn btn-ghost rounded-2xl flex-1 font-black text-xs uppercase tracking-widest opacity-30" onclick="request_modal.close()">Hủy</button>
                    <button type="submit" class="vsd-btn-ask flex-1 justify-center">Gửi yêu cầu</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
    function openRequestModal(tutorId, tutorName) {
        document.getElementById('modal_tutor_name').innerText = tutorName;
        document.getElementById('modal_tutor_id').value = tutorId;
        document.getElementById('request_modal').showModal();
    }

    const packageRadios = document.querySelectorAll('input[name="package_type"]');
    const fileUploadDiv = document.getElementById('file_upload_div');

    packageRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if(this.value === 'premium') {
                fileUploadDiv.classList.remove('hidden');
            } else {
                fileUploadDiv.classList.add('hidden');
                fileUploadDiv.querySelector('input').value = ''; 
            }
        });
    });

    document.getElementById('requestForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
            const res = await fetch('/handler/tutor_handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if(data.success) {
                window.location.href = '/tutors/request?id=' + data.request_id;
            } else {
                showAlert(data.message, 'error');
            }
        } catch(err) {
            showAlert('Có lỗi xảy ra', 'error');
        }
    });
    </script>
</body>
</html>
