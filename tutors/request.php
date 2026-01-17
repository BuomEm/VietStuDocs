<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$request_id = $_GET['id'] ?? 0;

$request = getRequestDetails($request_id);

// Access Control
if (!$request) {
    header("Location: /error?code=404");
    exit;
}

$is_student = ($request['student_id'] == $user_id);
$is_tutor = ($request['tutor_id'] == $user_id);

if (!$is_student && !$is_tutor && !isAdmin($user_id)) {
    header("Location: /error?code=403");
    exit;
}
$page_title = "Chi tiết câu hỏi - VietStuDocs";
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

    .request-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 24px;
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

    /* Main Question Card */
    .question-header-vsd {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        gap: 24px;
    }

    .question-title-vsd {
        font-size: 2.5rem;
        font-weight: 1000;
        letter-spacing: -0.05em;
        line-height: 1.1;
        color: oklch(var(--bc));
    }

    .status-badge-vsd {
        padding: 10px 24px;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .status-pending { background: #fde68a; color: #92400e; }
    .status-answered { background: #bbf7d0; color: #166534; }
    .status-completed { background: oklch(var(--b3)); color: oklch(var(--bc) / 0.5); }

    .question-content-vsd {
        background: oklch(var(--b2) / 0.5);
        padding: 32px;
        border-radius: 2rem;
        font-size: 1.1rem;
        line-height: 1.8;
        color: oklch(var(--bc));
        border: 1px solid oklch(var(--bc) / 0.05);
        margin-bottom: 32px;
        white-space: pre-wrap;
    }

    /* Attachment Box */
    .attachment-box-vsd {
        background: oklch(var(--b2) / 0.3);
        border: 1px dashed oklch(var(--bc) / 0.1);
        border-radius: 2rem;
        padding: 24px;
        margin-top: 24px;
    }

    .attachment-preview-vsd {
        max-width: 100%;
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        border: 1px solid oklch(var(--bc) / 0.05);
        margin-bottom: 20px;
    }

    /* Info Grid */
    .meta-grid-vsd {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        padding-top: 24px;
        border-top: 1px solid oklch(var(--bc) / 0.05);
    }

    .meta-item-vsd {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .meta-label-vsd {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        opacity: 0.4;
    }

    .meta-user-vsd {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 800;
        font-size: 0.95rem;
    }

    /* Chat Section */
    .chat-container-vsd {
        margin-top: 60px;
        display: flex;
        flex-direction: column;
        gap: 32px;
    }

    .chat-bubble-vsd {
        max-width: 80%;
        padding: 24px;
        border-radius: 2rem;
        position: relative;
        font-size: 1rem;
        line-height: 1.6;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
    }

    .chat-start-vsd {
        align-self: flex-start;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-bottom-left-radius: 4px;
    }

    .chat-end-vsd {
        align-self: flex-end;
        background: var(--red-gradient);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .chat-header-vsd {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        font-size: 0.75rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .chat-end-vsd .chat-header-vsd {
        justify-content: flex-end;
        color: rgba(255,255,255,0.7);
    }

    .chat-meta-vsd {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        font-size: 0.7rem;
        opacity: 0.5;
    }

    /* Input Area */
    .input-glass-vsd {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 3rem;
        padding: 40px;
        margin-top: 48px;
        box-shadow: 0 -20px 40px -10px rgba(0,0,0,0.05);
    }

    .vsd-textarea {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1.5rem !important;
        padding: 24px !important;
        font-weight: 600 !important;
        font-size: 1rem !important;
        width: 100%;
        resize: none;
        transition: all 0.3s ease;
    }

    .vsd-textarea:focus {
        border-color: var(--vsd-red) !important;
        box-shadow: 0 0 0 4px rgba(153, 27, 27, 0.1) !important;
    }

    .vsd-btn-send {
        background: var(--vsd-red);
        color: white;
        height: 64px;
        padding: 0 40px;
        border-radius: 1.5rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        border: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        display: inline-flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 15px 30px -8px rgba(153, 27, 27, 0.4);
    }

    .vsd-btn-send:hover {
        transform: scale(1.02);
        filter: brightness(1.1);
    }

    /* Success / Rating Card */
    .rating-card-vsd {
        background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);
        border: 2px solid #fbbf24;
        color: #92400e;
    }

    [data-theme="dark"] .rating-card-vsd {
        background: linear-gradient(135deg, #451a03 0%, #171717 100%);
        border-color: #92400e;
        color: #fef3c7;
    }

    /* Progress Sidebar */
    .progress-sidebar-vsd {
        position: sticky;
        top: 100px;
        z-index: 10;
    }

    @media (max-width: 1024px) {
        .progress-sidebar-vsd {
            position: relative;
            top: 0;
        }
    }

    .step-item-vsd {
        display: flex;
        gap: 20px;
        padding-bottom: 32px;
        position: relative;
    }

    .step-item-vsd:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 20px;
        top: 40px;
        bottom: 0px;
        width: 2px;
        background: oklch(var(--bc) / 0.1);
    }

    .step-icon-vsd {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: oklch(var(--b2));
        color: oklch(var(--bc) / 0.3);
        font-size: 0.9rem;
        z-index: 1;
        transition: all 0.3s ease;
    }

    .step-item-vsd.active .step-icon-vsd {
        background: var(--vsd-red);
        color: white;
        box-shadow: 0 8px 20px -5px rgba(153, 27, 27, 0.4);
    }

    .step-content-vsd {
        padding-top: 8px;
    }

    .step-title-vsd {
        font-size: 0.85rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 4px;
    }

    .step-item-vsd.active .step-title-vsd {
        color: var(--vsd-red);
    }

</style>

<body class="bg-base-100">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="request-container">
                
                <div class="mb-10 animate-in fade-in slide-in-from-left duration-500">
                    <a href="/tutors/dashboard" class="btn btn-ghost rounded-2xl gap-3 font-black text-[10px] tracking-widest uppercase opacity-40 hover:opacity-100">
                        <i class="fa-solid fa-arrow-left"></i> Quay lại bảng điều khiển
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10">
                    
                    <!-- Main Content -->
                    <div class="lg:col-span-8 order-2 lg:order-1">
                        
                        <!-- Question Card -->
                        <div class="glass-card-vsd animate-in fade-in slide-in-from-bottom duration-700">
                            <div class="question-header-vsd">
                                <h1 class="question-title-vsd"><?= htmlspecialchars($request['title']) ?></h1>
                                <div class="status-badge-vsd <?= $request['status'] == 'pending' ? 'status-pending' : ($request['status'] == 'answered' ? 'status-answered' : 'status-completed') ?>">
                                    <i class="fa-solid <?= $request['status'] == 'pending' ? 'fa-hourglass-half' : 'fa-check' ?>"></i>
                                    <?= $request['status'] == 'pending' ? 'Chờ trả lời' : ($request['status'] == 'answered' ? 'Đã trả lời' : 'Hoàn tất') ?>
                                </div>
                            </div>

                            <div class="question-content-vsd"><?= trim(htmlspecialchars($request['content'])) ?></div>

                            <?php if($request['attachment']): ?>
                                <div class="attachment-box-vsd">
                                    <h4 class="font-black text-[10px] uppercase tracking-widest mb-6 opacity-40">Tài liệu đính kèm</h4>
                                    <?php 
                                    $q_ext = strtolower(pathinfo($request['attachment'], PATHINFO_EXTENSION));
                                    if(in_array($q_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                                    ?>
                                        <img src="/uploads/tutors/<?= htmlspecialchars($request['attachment']) ?>" class="attachment-preview-vsd" alt="Attachment">
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center justify-between bg-base-100/50 p-4 rounded-2xl border border-base-content/5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary text-xl">
                                                <i class="fa-solid fa-file-export"></i>
                                            </div>
                                            <span class="text-xs font-black opacity-60 truncate max-w-[200px]"><?= htmlspecialchars($request['attachment']) ?></span>
                                        </div>
                                        <a href="/uploads/tutors/<?= htmlspecialchars($request['attachment']) ?>" download class="btn btn-primary rounded-xl px-6 h-12 font-black text-xs uppercase tracking-wider">Tải về</a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="meta-grid-vsd">
                                <div class="meta-item-vsd">
                                    <span class="meta-label-vsd">Học viên</span>
                                    <div class="meta-user-vsd">
                                        <div class="avatar placeholder">
                                            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-black">
                                                <?php if(!empty($request['student_avatar']) && file_exists('../uploads/avatars/' . $request['student_avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $request['student_avatar'] ?>" class="rounded-xl" />
                                                <?php else: ?>
                                                    <?= strtoupper(substr($request['student_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?= htmlspecialchars($request['student_name']) ?>
                                    </div>
                                </div>
                                <div class="meta-item-vsd">
                                    <span class="meta-label-vsd">Gia sư đảm nhận</span>
                                    <div class="meta-user-vsd">
                                        <div class="avatar placeholder">
                                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-black">
                                                <?php if(!empty($request['tutor_avatar']) && file_exists('../uploads/avatars/' . $request['tutor_avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $request['tutor_avatar'] ?>" class="rounded-xl" />
                                                <?php else: ?>
                                                    <?= strtoupper(substr($request['tutor_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?= htmlspecialchars($request['tutor_name']) ?>
                                    </div>
                                </div>
                                <div class="meta-item-vsd pt-4">
                                    <span class="meta-label-vsd">Phí dịch vụ</span>
                                    <div class="font-black text-xs uppercase tracking-widest text-error"><?= $request['points_used'] ?> VSD (TẠM GIỮ)</div>
                                </div>
                                <div class="meta-item-vsd pt-4">
                                    <span class="meta-label-vsd">Hạn chót trả lời (SLA)</span>
                                    <div class="font-black text-xs uppercase tracking-widest <?= (strtotime($request['sla_deadline']) < time() && $request['status'] == 'pending') ? 'text-error' : 'text-success' ?>">
                                        <?= date('H:i d/m/Y', strtotime($request['sla_deadline'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Offers Section -->
                        <?php 
                        $offers = getRequestOffers($request_id);
                        if (!empty($offers)): 
                        ?>
                            <div class="mt-8 space-y-4">
                                <h3 class="font-black text-xs uppercase tracking-widest opacity-30 px-4">Đề nghị bổ sung điểm</h3>
                                <?php foreach($offers as $offer): 
                                    $is_accepted = $offer['status'] === 'accepted';
                                ?>
                                    <div class="glass-card-vsd !p-6 border-l-4 <?= $is_accepted ? 'border-l-emerald-500' : 'border-l-warning' ?> flex flex-col md:flex-row justify-between items-center gap-6">
                                        <div>
                                            <div class="flex items-center gap-3 mb-2">
                                                <span class="badge <?= $is_accepted ? 'badge-success text-white' : 'badge-warning' ?> font-black text-[10px]">+<?= $offer['points_offered'] ?> VSD</span>
                                                <?php if(!$is_accepted): ?>
                                                    <span class="text-[10px] font-bold opacity-40 uppercase">Hết hạn: <?= date('H:i d/m', strtotime($offer['deadline'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm font-bold italic">"<?= htmlspecialchars($offer['reason']) ?>"</p>
                                        </div>
                                        <?php if($is_accepted): ?>
                                            <div class="px-8 py-3 rounded-2xl bg-emerald-500/10 text-emerald-600 font-black text-xs uppercase tracking-widest border border-emerald-500/20">
                                                 <i class="fa-solid fa-check mr-2"></i> Đã chấp nhận
                                            </div>
                                        <?php elseif($is_student && $request['status'] == 'pending'): ?>
                                            <button onclick="acceptOffer(<?= $offer['id'] ?>)" class="btn btn-warning rounded-2xl h-12 px-8 font-black text-xs uppercase tracking-widest shadow-lg shadow-warning/20">Chấp nhận</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Conversation Section -->
                        <div id="conversationSection" class="chat-container-vsd">
                            <?php if (!empty($request['answers'])): ?>
                                <?php foreach($request['answers'] as $index => $msg): ?>
                                    <?php 
                                        $is_me = ($msg['sender_id'] == $user_id);
                                        $is_sender_tutor = ($msg['sender_id'] == $request['tutor_id']);
                                    ?>
                                    <div class="chat-bubble-vsd <?= $is_me ? 'chat-end-vsd' : 'chat-start-vsd' ?> animate-in slide-in-from-<?= $is_me ? 'right' : 'left' ?> duration-500" data-msg-id="<?= $msg['id'] ?>">
                                        <div class="chat-header-vsd">
                                            <?php if(!$is_me): ?>
                                                <div class="w-8 h-8 rounded-lg bg-base-200 flex items-center justify-center text-xs font-black overflow-hidden">
                                                    <?php if(!empty($msg['sender_avatar']) && file_exists('../uploads/avatars/' . $msg['sender_avatar'])): ?>
                                                        <img src="../uploads/avatars/<?= $msg['sender_avatar'] ?>" />
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($msg['sender_name'], 0, 1)) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($msg['sender_name']) ?>
                                            <time class="opacity-40 text-[9px]"><?= date('H:i', strtotime($msg['created_at'])) ?></time>
                                        </div>
                                        
                                        <div class="whitespace-pre-wrap font-medium"><?= trim(htmlspecialchars($msg['content'])) ?></div>
                                        
                                        <?php if(!empty($msg['attachment'])): ?>
                                            <div class="mt-4 pt-4 border-t border-base-content/10">
                                                <a href="/uploads/tutors/<?= htmlspecialchars($msg['attachment']) ?>" download class="flex items-center gap-3 text-xs font-black hover:opacity-70 transition-opacity">
                                                    <i class="fa-solid fa-paperclip"></i> File đính kèm
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Student Rating Card -->
                        <?php if ($request['status'] === 'answered' && $is_student): ?>
                            <div class="glass-card-vsd rating-card-vsd mt-12 animate-in zoom-in duration-500">
                                <h3 class="font-black text-2xl mb-2 flex items-center gap-4">
                                    <i class="fa-solid fa-star"></i> Đánh giá & Hoàn tất
                                </h3>
                                <p class="text-sm font-bold opacity-80 mb-8 leading-relaxed">
                                    Nếu bạn hài lòng với câu trả lời (4-5 sao), gia sư sẽ nhận được điểm thù lao. 
                                    Nếu dưới 4 sao, đội ngũ Admin sẽ tham gia hỗ trợ xử lý khiếu nại.
                                </p>
                                
                                <form id="ratingForm" class="space-y-6">
                                    <input type="hidden" name="action" value="rate_tutor">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    
                                    <div class="rating rating-lg rating-half flex justify-center mb-4">
                                        <input type="radio" name="rating" class="rating-hidden" />
                                        <input type="radio" name="rating" value="0.5" class="mask mask-star-2 mask-half-1 bg-orange-500" />
                                        <input type="radio" name="rating" value="1" class="mask mask-star-2 mask-half-2 bg-orange-500" />
                                        <input type="radio" name="rating" value="1.5" class="mask mask-star-2 mask-half-1 bg-orange-500" />
                                        <input type="radio" name="rating" value="2" class="mask mask-star-2 mask-half-2 bg-orange-500" />
                                        <input type="radio" name="rating" value="2.5" class="mask mask-star-2 mask-half-1 bg-orange-500" />
                                        <input type="radio" name="rating" value="3" class="mask mask-star-2 mask-half-2 bg-orange-500" />
                                        <input type="radio" name="rating" value="3.5" class="mask mask-star-2 mask-half-1 bg-orange-500" />
                                        <input type="radio" name="rating" value="4" class="mask mask-star-2 mask-half-2 bg-orange-500" />
                                        <input type="radio" name="rating" value="4.5" class="mask mask-star-2 mask-half-1 bg-orange-500" />
                                        <input type="radio" name="rating" value="5" class="mask mask-star-2 mask-half-2 bg-orange-500" checked />
                                    </div>
                                    
                                    <div class="form-control">
                                        <textarea name="review" required class="vsd-textarea h-32" placeholder="Cảm ơn gia sư hoặc để lại góp ý tại đây..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="vsd-btn-send w-full justify-center bg-orange-600 shadow-orange-900/20">Hoàn tất hỗ trợ</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Completed Review Display -->
                        <?php if ($request['status'] === 'completed' || $request['status'] === 'disputed'): ?>
                            <div class="glass-card-vsd mt-12">
                                <h3 class="font-black text-xs uppercase tracking-widest opacity-30 mb-8 border-b border-base-content/5 pb-4">Đánh giá chung</h3>
                                <div class="flex flex-col items-center py-10">
                                    <div class="rating rating-lg rating-half pointer-events-none mb-4">
                                        <input type="radio" class="rating-hidden" />
                                        <?php for($i=0.5; $i<=5; $i+=0.5): ?>
                                            <input type="radio" class="mask mask-star-2 bg-orange-500 <?= fmod($i, 1) !== 0.0 ? 'mask-half-1' : 'mask-half-2' ?>" <?= ($request['rating'] ?? 0) == $i ? 'checked' : '' ?> />
                                        <?php endfor; ?>
                                    </div>
                                    <div class="text-4xl font-black mb-4"><?= $request['rating'] ?>/5</div>
                                    <p class="text-xl font-bold italic opacity-60 text-center">"<?= htmlspecialchars($request['review'] ?? 'Không có nhận xét') ?>"</p>
                                </div>
                                
                                <?php if($request['status'] === 'disputed'): ?>
                                    <div class="alert bg-red-500/10 border-red-500/20 text-red-600 rounded-3xl p-6 mt-6">
                                        <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                                        <div>
                                            <h4 class="font-black uppercase text-xs tracking-widest">Đang khiếu nại</h4>
                                            <p class="text-sm font-bold opacity-70">Admin đang xem xét yêu cầu này do đánh giá thấp hoặc sự cố hỗ trợ.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Cancelled Card -->
                        <?php if ($request['status'] === 'cancelled'): ?>
                            <div class="glass-card-vsd mt-12 border-red-500/20 bg-red-500/5">
                                <div class="flex flex-col items-center text-center py-8">
                                    <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mb-6 text-red-500 text-3xl">
                                        <i class="fa-solid fa-ban"></i>
                                    </div>
                                    <h3 class="font-black text-2xl text-red-600 mb-2">Yêu cầu đã bị hủy</h3>
                                    <p class="text-sm font-bold opacity-60 max-w-md mx-auto">
                                        Yêu cầu này đã bị hủy do quá hạn trả lời hoặc theo yêu cầu của hệ thống. 
                                        Tiền cọc đã được hoàn lại cho học viên.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Waiting for Rating Card (Tutor View) -->
                        <?php if ($is_tutor && $request['status'] === 'answered'): ?>
                            <div class="glass-card-vsd mt-12 bg-emerald-500/5 border-emerald-500/20">
                                <div class="flex flex-col items-center text-center py-8">
                                    <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mb-6 text-emerald-500 text-3xl animate-pulse">
                                        <i class="fa-solid fa-hourglass-half"></i>
                                    </div>
                                    <h3 class="font-black text-2xl text-emerald-700 mb-2">Đang chờ đánh giá</h3>
                                    <p class="text-sm font-bold opacity-60 max-w-md mx-auto">
                                        Bạn đã hoàn tất hỗ trợ. Vui lòng đợi học viên xác nhận và đánh giá chất lượng để nhận điểm thù lao.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Message Input Panel -->
                        <?php 
                        $is_expired = ($request['status'] === 'pending' && strtotime($request['sla_deadline']) < time());
                        if (($is_tutor && $request['status'] === 'pending') || ($is_student && $request['status'] === 'pending' && !empty($request['answers']))): 
                        ?>
                            <div class="input-glass-vsd animate-in slide-in-from-bottom duration-700">
                                <h3 class="font-black text-xs uppercase tracking-[0.2em] opacity-30 mb-8"><?= $is_tutor ? 'Gia sư trả lời' : 'Học viên nhắn tin' ?></h3>
                                
                                <form id="<?= $is_tutor ? 'answerForm' : 'chatForm' ?>" class="space-y-6" enctype="multipart/form-data">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    
                                    <?php if ($is_expired): ?>
                                        <div class="alert bg-error/10 border-error/20 text-error rounded-2xl">
                                            <i class="fa-solid fa-clock"></i>
                                            <span class="text-sm font-bold">Thời gian hỗ trợ đã kết thúc. Vui lòng gia hạn thêm thời gian hoặc hoàn tất yêu cầu.</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-control">
                                            <textarea name="content" required class="vsd-textarea h-32" placeholder="Nhập nội dung tin nhắn của bạn..."></textarea>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex flex-wrap gap-3 items-center">
                                        <?php if (!$is_expired): ?>
                                            <label class="btn btn-ghost btn-sm rounded-xl h-10 px-4 border border-base-content/10 flex items-center gap-2 cursor-pointer hover:bg-base-200">
                                                <i class="fa-solid fa-paperclip opacity-40 text-xs"></i>
                                                <span class="text-[9px] font-black uppercase tracking-wider opacity-40">Đính kèm</span>
                                                <input type="file" name="attachment" class="hidden" />
                                            </label>
                                        <?php endif; ?>
                                        
                                        <?php if($is_tutor): ?>
                                            <button type="button" onclick="showOfferModal()" class="btn btn-ghost btn-sm rounded-xl h-10 px-4 border-warning/30 text-warning hover:bg-warning/5 font-black text-[9px] uppercase tracking-wider">
                                                Gia hạn / Thêm điểm
                                            </button>
                                            <button type="button" onclick="finishRequestManual()" class="btn btn-ghost btn-sm rounded-xl h-10 px-4 border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/5 font-black text-[9px] uppercase tracking-wider">
                                                Hoàn tất
                                            </button>
                                        <?php endif; ?>

                                        <?php if($is_student): ?>
                                            <button type="button" onclick="showExtensionModal()" class="btn btn-ghost btn-sm rounded-xl h-10 px-4 border-info/30 text-info hover:bg-info/5 font-black text-[9px] uppercase tracking-wider">
                                                Gia hạn thời gian
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (!$is_expired): ?>
                                            <button type="submit" class="vsd-btn-send ml-auto h-12 px-6 text-xs">
                                                <span class="btn-text">Gửi</span>
                                                <span class="btn-loading hidden"><span class="loading loading-spinner loading-xs"></span></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar Progress -->
                    <div class="lg:col-span-4 order-1 lg:order-2">
                        <aside class="progress-sidebar-vsd">
                            <div class="glass-card-vsd animate-in slide-in-from-right duration-700">
                                <h3 class="font-black text-xs uppercase tracking-[0.25em] mb-10 opacity-30">Trình trạng yêu cầu</h3>
                                
                                <div class="space-y-0">
                                    <div class="step-item-vsd active">
                                        <div class="step-icon-vsd"><i class="fa-solid fa-paper-plane"></i></div>
                                        <div class="step-content-vsd">
                                            <h4 class="step-title-vsd">Gửi câu hỏi</h4>
                                            <p class="text-[10px] font-bold opacity-40 uppercase"><?= $request['points_used'] ?> VSD được tạm giữ</p>
                                        </div>
                                    </div>
                                    
                                    <div class="step-item-vsd <?= ($request['status'] == 'answered' || $request['status'] == 'completed' || $request['status'] == 'disputed') ? 'active' : '' ?>">
                                        <div class="step-icon-vsd"><i class="fa-solid fa-chalkboard-user"></i></div>
                                        <div class="step-content-vsd">
                                            <h4 class="step-title-vsd">Gia sư phản hồi</h4>
                                            <?php if($request['status'] == 'pending'): ?>
                                                <p class="text-[10px] font-bold text-warning uppercase animate-pulse" id="main-sla-timer">Đang chờ xử lý...</p>
                                            <?php else: ?>
                                                <p class="text-[10px] font-bold opacity-40 uppercase">Hoàn tất</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="step-item-vsd <?= ($request['status'] == 'completed' || $request['status'] == 'disputed') ? 'active' : '' ?>">
                                        <div class="step-icon-vsd"><i class="fa-solid fa-check-double"></i></div>
                                        <div class="step-content-vsd">
                                            <h4 class="step-title-vsd">Hoàn tất & Đánh giá</h4>
                                            <p class="text-[10px] font-bold opacity-40 uppercase"><?= $request['status'] == 'completed' ? 'Đã xong' : 'Chưa hoàn tất' ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-8 pt-8 border-t border-base-content/5">
                                    <p class="text-[10px] font-bold opacity-30 leading-relaxed text-center italic">
                                        "Mọi cuộc hội thoại đều được hệ thống ghi lại để đảm bảo chất lượng dịch vụ và bảo vệ quyền lợi hai bên."
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>

                </div>
            </div>
        </main>
        
        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>

    <script>
    const CURRENT_USER_ID = <?= $user_id ?>;
    const REQUEST_ID = <?= $request_id ?>;
    const TUTOR_ID = <?= $request['tutor_id'] ?>;
    let lastMsgId = 0;
    let currentStatus = '<?= $request['status'] ?>';
    
    document.querySelectorAll('[data-msg-id]').forEach(msg => {
        const id = parseInt(msg.dataset.msgId);
        if(id > lastMsgId) lastMsgId = id;
    });

    async function handleTutorAction(formId, action) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            if(!btn) return;
            
            // Prevent double submission
            if(btn.dataset.processing === 'true') return;
            btn.dataset.processing = 'true';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('action', action);
            
            try {
                const res = await fetch('/handler/tutor_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if(data.success) {
                    if (action === 'send_chat_message' || action === 'answer_request' || action === 'student_chat') {
                        form.reset();
                        // Reset file input if exists
                        const fileInput = form.querySelector('input[type="file"]');
                        if (fileInput) fileInput.value = '';
                        
                        pollMessages(); // Fire and forget
                        showAlert('Đã gửi tin nhắn!', 'success');
                    } else {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (err) {
                console.error(err);
                showAlert('Có lỗi xảy ra khi gửi tin nhắn', 'error');
            } finally {
                btn.disabled = false;
                btn.dataset.processing = 'false';
            }
        });
    }

    function scrollToBottom() {
        const section = document.getElementById('conversationSection');
        if(section.lastElementChild) {
            section.lastElementChild.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function renderMessage(msg) {
        const isMe = msg.sender_id == CURRENT_USER_ID;
        const isSenderTutor = msg.sender_id == TUTOR_ID;
        const sideClass = isMe ? 'chat-end-vsd' : 'chat-start-vsd';
        const ringClass = isMe ? 'ring-primary/20' : 'ring-secondary/20';
        
        const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

        let avatarHtml = '';
        if(!isMe) {
            avatarHtml = `<div class="w-8 h-8 rounded-lg bg-base-200 flex items-center justify-center text-xs font-black overflow-hidden">
                ${msg.sender_avatar ? `<img src="../uploads/avatars/${msg.sender_avatar}" />` : `<span>${msg.sender_name.charAt(0).toUpperCase()}</span>`}
            </div>`;
        }

        let attachmentHtml = '';
        if(msg.attachment) {
            attachmentHtml = `
                <div class="mt-4 pt-4 border-t border-base-content/10">
                    <a href="/uploads/tutors/${msg.attachment}" download class="flex items-center gap-3 text-xs font-black hover:opacity-70 transition-opacity">
                        <i class="fa-solid fa-paperclip"></i> File đính kèm
                    </a>
                </div>
            `;
        }

        return `
            <div class="chat-bubble-vsd ${sideClass} animate-in slide-in-from-${isMe ? 'right' : 'left'} duration-500" data-msg-id="${msg.id}">
                <div class="chat-header-vsd">
                    ${avatarHtml}
                    ${msg.sender_name}
                    <time class="opacity-40 text-[9px]">${time}</time>
                </div>
                <div class="whitespace-pre-wrap font-medium">${msg.content.trim()}</div>
                ${attachmentHtml}
            </div>
        `;
    }

    async function pollMessages() {
        try {
            const res = await fetch(`/api/tutor_chat.php?action=poll_messages&request_id=${REQUEST_ID}&last_id=${lastMsgId}`);
            const data = await res.json();
            
            if(data.success) {
                if(data.messages.length > 0) {
                    const container = document.getElementById('conversationSection');
                    data.messages.forEach(msg => {
                        if(parseInt(msg.id) > lastMsgId) {
                            container.insertAdjacentHTML('beforeend', renderMessage(msg));
                            lastMsgId = parseInt(msg.id);
                        }
                    });
                    scrollToBottom();
                }

                if(data.status && data.status !== currentStatus) {
                    setTimeout(() => location.reload(), 1500);
                }
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }

    setInterval(pollMessages, 3000);

    async function acceptOffer(offerId) {
        vsdConfirm({
            title: 'Chấp nhận đề nghị',
            message: 'Bạn đồng ý bổ sung điểm cho yêu cầu này theo đề nghị của Gia sư?',
            type: 'warning',
            confirmText: 'Đồng ý',
            onConfirm: async function() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'accept_offer');
                    formData.append('offer_id', offerId);
                    
                    const res = await fetch('/handler/tutor_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    
                    if(data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        });
    }

    async function finishRequestManual() {
        // Validation: Must reply at least once
        const myReplies = document.querySelectorAll('#conversationSection .chat-end-vsd');
        if (myReplies.length === 0) {
            showAlert('Bạn cần phản hồi ít nhất 1 lần trước khi hoàn tất yêu cầu!', 'error');
            return;
        }

        vsdConfirm({
            title: 'Hoàn tất hỗ trợ',
            message: 'Xác nhận hoàn tất hỗ trợ cho yêu cầu này? Sau khi đóng, học viên sẽ thực hiện đánh giá chất lượng.',
            type: 'success',
            confirmText: 'Kết thúc ngay',
            onConfirm: async function() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'finish_request'); // Using finish_request logic
                    // Actually, handler/tutor_handler.php does not seem to have 'finish_request' case in the viewed file!
                    // I checked tutor_handler.php lines 1-275 and it was not there.
                    // It uses api/tutor_chat.php (from previous code view).
                    // In previous view of request.php line 874: `fetch('/api/tutor_chat.php', ...)`
                    // Ah, so it calls API directly. I should keep that.
                    
                    // Re-verifying finishRequestManual original code:
                    // fetch('/api/tutor_chat.php', { method: 'POST', body: formData });
                    // So I should keep the fetch target as /api/tutor_chat.php and action as finish_request.
                    
                    formData.append('request_id', REQUEST_ID);
                    
                    const res = await fetch('/api/tutor_chat.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    
                    if(data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        });
    }

    // ... (rest of the file) ...


    function updateCountdowns() {
        const now = new Date().getTime();
        
        // Update main timer
        const mainTimer = document.getElementById('main-sla-timer');
        if(mainTimer) {
             const deadlineStr = '<?= $request['sla_deadline'] ?? '' ?>';
             if(deadlineStr) {
                const deadline = new Date(deadlineStr).getTime();
                const distance = deadline - now;
                
                if (distance < 0) {
                    mainTimer.innerHTML = "<span class='text-error'>Đã quá hạn</span>";
                } else {
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    mainTimer.innerHTML = `Còn lại: ${hours}h ${minutes}p`;
                }
             }
        }
        
        // Update bubble timers
        document.querySelectorAll('.sla-timer').forEach(el => {
            const dStr = el.getAttribute('data-deadline');
            if(dStr) {
                 const d = new Date(dStr).getTime();
                 const dist = d - now;
                 if(dist > 0) {
                     const h = Math.floor((dist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                     const m = Math.floor((dist % (1000 * 60 * 60)) / (1000 * 60));
                     el.innerText = `(Hết hạn trong ${h}h ${m}p)`;
                 } else {
                     el.innerText = '(Đã hết hạn)';
                 }
            }
        });
    }
    
    // Start interval
    setInterval(updateCountdowns, 60000); // Check every minute
    updateCountdowns(); // Run immediately

    function showOfferModal() {
        vsdPrompt({
            title: 'Đề nghị thêm điểm',
            message: 'Nếu câu hỏi phức tạp hơn mô tả ban đầu, bạn có thể đề nghị học viên bổ sung điểm.',
            inputs: [
                {
                    label: 'Mức điểm bổ sung',
                    name: 'points',
                    type: 'select',
                    options: [
                        {value: 10, label: '+10 VSD'},
                        {value: 20, label: '+20 VSD'},
                        {value: 40, label: '+40 VSD'},
                        {value: 60, label: '+60 VSD'}
                    ]
                },
                {
                    label: 'Lý do đề nghị',
                    name: 'reason',
                    type: 'textarea',
                    placeholder: 'Ví dụ: Bài toán cần giải theo 3 cách khác nhau...'
                }
            ],
            confirmText: 'Gửi đề nghị',
            onConfirm: async function(data) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'create_offer');
                    formData.append('request_id', REQUEST_ID);
                    formData.append('points', data.points);
                    formData.append('reason', data.reason);
                    
                    const res = await fetch('/handler/tutor_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    const resData = await res.json();
                    
                    if(resData.success) {
                        showAlert(resData.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(resData.message, 'error');
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        });
    }

    function showExtensionModal() {
        vsdPrompt({
            title: 'Gia hạn thời gian',
            message: 'Bạn có thể dùng điểm tích lũy để gia hạn thêm thời gian cho Gia sư. Cứ 10 VSD = +1 Giờ.',
            inputs: [
                {
                    label: 'Mức gia hạn',
                    name: 'points',
                    type: 'select',
                    options: [
                        {value: 10, label: '10 VSD (+1 Giờ)'},
                        {value: 20, label: '20 VSD (+2 Giờ)'},
                        {value: 40, label: '40 VSD (+4 Giờ)'},
                        {value: 60, label: '60 VSD (+6 Giờ)'}
                    ]
                }
            ],
            confirmText: 'Gia hạn ngay',
            onConfirm: async function(data) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'student_extend_time');
                    formData.append('request_id', REQUEST_ID);
                    formData.append('points', data.points);
                    
                    const res = await fetch('/handler/tutor_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    const resData = await res.json();
                    
                    if(resData.success) {
                        showAlert(resData.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(resData.message, 'error');
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        });
    }

    handleTutorAction('answerForm', 'send_chat_message');
    handleTutorAction('chatForm', 'send_chat_message');
    handleTutorAction('ratingForm', 'rate_tutor');
    
    // Auto scroll bottom on load
    window.addEventListener('load', () => {
        setTimeout(scrollToBottom, 500);
    });
    </script>
</body>
</html>
