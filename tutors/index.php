<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

$page_title = "Tìm Gia Sư - VietStuDocs";
$current_page = 'tutors';
$tutors = getActiveTutors($_GET);

// SEO
$page_keywords = "gia sư, tìm gia sư, dạy kèm, toán, lý, hóa, anh văn, luyện thi, " . htmlspecialchars($_GET['subject'] ?? '');
$page_description = "Tìm kiếm gia sư giỏi, uy tín. Hỗ trợ giải bài tập, ôn thi đại học, cấp 3, cấp 2 nhanh chóng tại DocShare.";

// Get SLA settings for display
$sla_basic = floatval(getSetting('tutor_sla_basic', 0.5));
$sla_standard = floatval(getSetting('tutor_sla_standard', 1));
$sla_premium = floatval(getSetting('tutor_sla_premium', 6));

// Format SLA for display
function formatSLA($hours) {
    if ($hours < 1) return intval($hours * 60) . 'p';
    return intval($hours) . 'h';
}
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
        padding: 40px 24px;
        border-radius: 2.5rem;
        background: var(--red-gradient);
        color: white;
        overflow: hidden;
        margin-bottom: 40px;
        text-align: center;
        box-shadow: 0 20px 50px -15px rgba(153, 27, 27, 0.3);
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
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 1000;
        letter-spacing: -0.05em;
        line-height: 1;
        margin-bottom: 12px;
        position: relative;
    }

    .tutors-hero p {
        font-size: 1rem;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto;
        font-weight: 600;
        position: relative;
    }

    /* Layout */
    .tutors-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
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
        border-radius: 2rem;
        padding: 30px;
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

    /* Compact Input for Modal */
    .vsd-input-compact {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1rem !important;
        height: 44px !important;
        font-weight: 700 !important;
        font-size: 0.9rem !important;
        transition: all 0.3s ease !important;
    }
    
    .vsd-input-compact:focus {
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
        padding: 25px 30px 15px;
        text-align: center;
    }

    .modal-body-vsd {
        padding: 0 30px 30px;
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
        padding: 12px 8px;
        border-radius: 1rem;
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
        .tutors-container {
            padding: 20px 16px;
        }
        .filter-sidebar {
            position: relative;
            top: 0;
            z-index: 1;
            margin-bottom: 30px;
        }
        .tutor-card-vsd {
            flex-direction: column;
            gap: 24px;
            padding: 24px;
        }
        .tutor-avatar-vsd {
            width: 100px;
            height: 100px;
            margin: 0 auto;
        }
        .tutor-name-vsd {
            text-align: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .tutor-status-vsd {
            justify-content: center;
        }
        .vsd-btn-ask {
            width: 100%;
        }
        
        /* Modal Mobile Fixes */
        .modal-box-vsd {
            width: 95% !important;
            max-width: 95% !important;
        }
        .modal-header-vsd {
            padding: 20px 20px 10px;
        }
        .modal-body-vsd {
            padding: 0 20px 20px;
        }
        .package-option .font-black.text-sm {
            font-size: 0.7rem;
        }
        .package-option .text-[9px] {
            font-size: 0.55rem;
            margin-top: 2px;
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
                    <?php if(isset($_SESSION['user_id']) && isTutor($_SESSION['user_id'])): ?>
                        <div class="mt-6">
                            <a href="/tutors/withdraw" class="btn bg-white text-vsd-red hover:bg-white/90 border-none rounded-2xl font-black px-8">
                                <i class="fa-solid fa-money-bill-transfer mr-2"></i> RÚT TIỀN GIA SƯ
                            </a>
                        </div>
                    <?php endif; ?>
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
                                <button onclick="checkLoginAndAction('become_tutor')" class="btn btn-outline border-2 border-primary/20 hover:bg-primary/5 rounded-2xl w-full font-black text-xs h-14 uppercase tracking-wider">Trở thành Gia Sư</button>
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
                                            <?php 
                                            // Status Check
                                            $online_status = getOnlineStatusString($tutor['last_activity'] ?? null);
                                            $dot_color = ($online_status['status'] === 'online') ? 'text-success' : 'text-base-300';
                                            ?>
                                            <h3 class="tutor-name-vsd">
                                                <?= htmlspecialchars($tutor['username']) ?>
                                                <?php if(!empty($tutor['is_verified_tutor'])): ?>
                                                    <i class="fa-solid fa-circle-check text-blue-500 text-xl" title="Gia sư chính thức"></i>
                                                <?php endif; ?>
                                            </h3>
                                            <div class="tutor-status-vsd">
                                                <i class="fa-solid fa-circle <?= $dot_color ?>"></i> <?= $online_status['text'] ?>
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
                                                    <div class="pricing-label-vsd">Normal</div>
                                                    <div class="pricing-value-vsd text-success"><?= $tutor['price_basic'] ?? 25 ?> VSD</div>
                                                </div>
                                                <div class="pricing-item-vsd border-x border-base-content/5">
                                                    <div class="pricing-label-vsd">Medium</div>
                                                    <div class="pricing-value-vsd text-info"><?= $tutor['price_standard'] ?? 35 ?> VSD</div>
                                                </div>
                                                <div class="pricing-item-vsd">
                                                    <div class="pricing-label-vsd">VIP</div>
                                                    <div class="pricing-value-vsd text-warning"><?= $tutor['price_premium'] ?? 60 ?> VSD</div>
                                                </div>
                                            </div>
                                            
                                            <button onclick='checkLoginAndAction("ask_question", <?= $tutor['user_id'] ?>, "<?= htmlspecialchars($tutor['username']) ?>", <?= json_encode([
                                                "normal" => $tutor['price_basic'] ?? 25,
                                                "medium" => $tutor['price_standard'] ?? 35,
                                                "vip" => $tutor['price_premium'] ?? 60
                                            ]) ?>)' class="vsd-btn-ask">
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
        <div class="modal-box modal-box-vsd max-w-2xl flex flex-col max-h-[90vh]">
            <div class="modal-header-vsd shrink-0">
                <h3 class="font-black text-2xl uppercase tracking-tighter gradient-text-red">Đặt câu hỏi</h3>
                <p class="text-[10px] opacity-40 font-black uppercase tracking-widest mt-1">Dành cho gia sư: <span id="modal_tutor_name" class="text-primary"></span></p>
            </div>
            
            <form id="requestForm" class="modal-body-vsd space-y-3 overflow-y-auto flex-1 min-h-0" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_request">
                <input type="hidden" name="tutor_id" id="modal_tutor_id">
                
                <div class="form-control">
                    <label class="form-label-vsd !mb-1">Tiêu đề câu hỏi</label>
                    <input type="text" name="title" required class="input vsd-input-compact w-full" placeholder="Ví dụ: Giúp em giải bài toán tích phân lớp 12">
                </div>
                
                <div class="form-control">
                    <label class="form-label-vsd !mb-1">Nội dung chi tiết</label>
                    <textarea name="content" required class="textarea vsd-input-compact h-24 py-2 !leading-tight text-sm" placeholder="Mô tả chi tiết câu hỏi của bạn, càng chi tiết càng tốt..."></textarea>
                </div>
                
                <div class="form-control">
                    <label class="form-label-vsd !mb-1">Chọn gói & Mức điểm</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <label class="cursor-pointer group">
                            <input type="radio" name="package_type" value="normal" class="hidden" checked onchange="updatePriceDisplay('normal')" />
                            <div class="package-option h-full flex flex-col justify-center py-4 border-2">
                                <div class="font-black text-sm text-success">NORMAL</div>
                                <div class="text-[10px] font-bold opacity-40 mt-1">SLA: <?= formatSLA($sla_basic) ?> • <span id="price_display_normal"></span> pts</div>
                            </div>
                        </label>
                        <label class="cursor-pointer group">
                            <input type="radio" name="package_type" value="medium" class="hidden" onchange="updatePriceDisplay('medium')" />
                            <div class="package-option h-full flex flex-col justify-center py-4 border-2">
                                <div class="font-black text-sm text-info">MEDIUM</div>
                                <div class="text-[10px] font-bold opacity-40 mt-1">SLA: <?= formatSLA($sla_standard) ?> • <span id="price_display_medium"></span> pts</div>
                            </div>
                        </label>
                        <label class="cursor-pointer group">
                            <input type="radio" name="package_type" value="vip" class="hidden" onchange="updatePriceDisplay('vip')" />
                            <div class="package-option h-full flex flex-col justify-center py-4 border-2">
                                <div class="font-black text-sm text-warning">VIP</div>
                                <div class="text-[10px] font-bold opacity-40 mt-1">SLA: <?= formatSLA($sla_premium) ?> • <span id="price_display_vip"></span> pts</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-control">
                    <label class="form-label-vsd !mb-1 flex justify-between">
                        <span>Số điểm cần trả (Cố định)</span>
                    </label>
                    <div class="flex items-center gap-4 bg-base-200/50 p-4 rounded-2xl border border-base-content/5">
                        <input type="hidden" name="points" id="points_input">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary"><i class="fa-solid fa-coins"></i></div>
                        <div class="font-black text-primary text-2xl" id="points_final_display">0 VSD</div>
                    </div>
                </div>

                <div class="form-control hidden" id="file_upload_div">
                    <label class="form-label-vsd !mb-1 flex justify-between items-center">
                        <span>Đính kèm tài liệu</span>
                        <span class="text-[9px] opacity-40 font-normal normal-case tracking-normal">Tối đa 5MB</span>
                    </label>
                    <input type="file" name="attachment" class="file-input file-input-bordered w-full vsd-input-compact !p-0 !h-[40px] !leading-[40px] text-xs" />
                </div>
            </form>

            <div class="modal-action-vsd p-5 shrink-0 mt-auto">
                <div class="flex gap-3">
                    <button type="button" class="btn btn-ghost rounded-2xl flex-1 font-black text-xs uppercase tracking-widest opacity-30 h-11 min-h-0" onclick="this.closest('dialog').close()">Hủy</button>
                    <button type="submit" form="requestForm" class="vsd-btn-ask flex-1 justify-center h-11 min-h-0">Gửi yêu cầu</button>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Toast Container -->
    <div id="toast-container" class="toast toast-top toast-end z-50"></div>

    <script>
    const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

    function showVsdToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} shadow-lg rounded-2xl animate-in slide-in-from-right duration-300`;
        
        let icon = 'fa-circle-info';
        if(type === 'success') icon = 'fa-circle-check';
        if(type === 'error') icon = 'fa-circle-exclamation';
        if(type === 'warning') icon = 'fa-triangle-exclamation';
        
        toast.innerHTML = `
            <i class="fa-solid ${icon}"></i>
            <span class="font-bold text-sm">${message}</span>
        `;
        
        document.getElementById('toast-container').appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }

    function checkLoginAndAction(actionType, ...args) {
        if (!isLoggedIn) {
            showVsdToast('Vui lòng đăng nhập để thực hiện chức năng này', 'warning');
            setTimeout(() => {
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            }, 1500);
            return;
        }

        // Execute original action if logged in
        if (actionType === 'ask_question') {
            openRequestModal(...args);
        } else if (actionType === 'become_tutor') {
            window.location.href = '/tutors/apply';
        }
    }

    let currentTutorPrices = {};

    function openRequestModal(tutorId, tutorName, prices) {
        document.getElementById('modal_tutor_name').innerText = tutorName;
        document.getElementById('modal_tutor_id').value = tutorId;
        
        currentTutorPrices = prices;
        
        // Update labels
        document.getElementById('price_display_normal').innerText = prices.normal;
        document.getElementById('price_display_medium').innerText = prices.medium;
        document.getElementById('price_display_vip').innerText = prices.vip;
        
        updatePriceDisplay('normal'); // Default
        document.getElementById('request_modal').showModal();
    }

    function updatePriceDisplay(pkg) {
        const price = currentTutorPrices[pkg];
        document.getElementById('points_input').value = price;
        document.getElementById('points_final_display').innerText = price + ' VSD';
        
        // Only VIP gets file attachments
        const fileDiv = document.getElementById('file_upload_div');
        if (pkg === 'vip') {
            fileDiv.classList.remove('hidden');
        } else {
            fileDiv.classList.add('hidden');
            fileDiv.querySelector('input[type="file"]').value = ''; // Clear file if switching away
        }
    }

    document.getElementById('requestForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        let btn = document.querySelector('button[form="requestForm"]');
        // Fallback if button not found by form attribute (e.g. inside form)
        if (!btn) btn = this.querySelector('button[type="submit"]');

        if(btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Đang gửi...';
        }

        const formData = new FormData(this);
        
        try {
            const res = await fetch('/handler/tutor_handler.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is JSON
            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await res.text();
                console.error("Non-JSON response:", text);
                throw new Error("Server returned invalid format.");
            }

            const data = await res.json();
            
            if(data.success) {
                window.location.href = '/tutors/request?id=' + data.request_id;
            } else {
                if(typeof showAlert === 'function') {
                    showAlert(data.message, 'error');
                } else {
                    alert(data.message);
                }
            }
        } catch(err) {
            console.error(err);
            if(typeof showAlert === 'function') {
                showAlert('Có lỗi xảy ra: ' + err.message, 'error');
            } else {
                alert('Có lỗi xảy ra: ' + err.message);
            }
        } finally {
            if(btn) {
                btn.disabled = false;
                btn.innerHTML = 'Gửi yêu cầu';
            }
        }
    });
    </script>
</body>
</html>
