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
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="flex-1 p-6">
        <div class="container mx-auto">
            <div class="mb-4">
                <a href="/tutors/dashboard" class="btn btn-ghost gap-2"><i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="col-span-2">
                    <!-- Question Card -->
                    <div class="card bg-base-100 shadow-xl mb-6 border border-base-200">
                        <div class="card-body">
                            <div class="flex justify-between items-start mb-4">
                                <h2 class="card-title text-2xl text-primary break-all"><?= htmlspecialchars($request['title']) ?></h2>
                                <span class="badge badge-lg <?= $request['status'] == 'pending' ? 'badge-warning' : 'badge-success' ?>">
                                    <?= ucfirst($request['status']) ?>
                                </span>
                            </div>
                            
                            <div class="bg-base-200 p-4 rounded-lg mb-4 font-mono text-sm leading-relaxed whitespace-pre-wrap"><?= trim(htmlspecialchars($request['content'])) ?></div>
                            
                            <?php if($request['attachment']): ?>
                                <div class="mt-4 p-4 bg-base-200 rounded-lg border border-base-300">
                                    <div class="flex items-center gap-2 mb-3">
                                        <i class="fa-solid fa-paperclip text-primary font-bold"></i>
                                        <span class="font-bold">File đính kèm từ bạn</span>
                                    </div>
                                    
                                    <?php 
                                    $q_ext = strtolower(pathinfo($request['attachment'], PATHINFO_EXTENSION));
                                    if(in_array($q_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                                    ?>
                                        <img src="/uploads/tutors/<?= htmlspecialchars($request['attachment']) ?>" class="max-w-full rounded-lg shadow-md border border-base-300" alt="Question Attachment">
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 flex items-center justify-between bg-base-100 p-3 rounded-md border border-base-300">
                                        <span class="text-sm truncate opacity-70"><?= htmlspecialchars($request['attachment']) ?></span>
                                        <a href="/uploads/tutors/<?= htmlspecialchars($request['attachment']) ?>" download class="btn btn-sm btn-primary">
                                            <i class="fa-solid fa-download"></i> Tải về
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="divider my-2">Thông tin chi tiết</div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="flex flex-col gap-1">
                                    <span class="text-base-content/60">Người hỏi:</span>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar <?= !empty($request['student_avatar']) ? '' : 'placeholder' ?>">
                                            <div class="w-8 h-8 rounded-full border border-base-300 overflow-hidden ring ring-offset-base-100 ring-1 ring-primary/10 <?= empty($request['student_avatar']) ? 'bg-primary text-primary-content flex items-center justify-center font-bold text-xs' : '' ?>">
                                                <?php if(!empty($request['student_avatar']) && file_exists('../uploads/avatars/' . $request['student_avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $request['student_avatar'] ?>" alt="Student Avatar" />
                                                <?php else: ?>
                                                    <span><?= strtoupper(substr($request['student_name'], 0, 1)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="font-bold"><?= htmlspecialchars($request['student_name']) ?></span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-base-content/60">Gia sư:</span>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar <?= !empty($request['tutor_avatar']) ? '' : 'placeholder' ?>">
                                            <div class="w-8 h-8 rounded-full border border-base-300 overflow-hidden ring ring-offset-base-100 ring-1 ring-success/10 <?= empty($request['tutor_avatar']) ? 'bg-success text-success-content flex items-center justify-center font-bold text-xs' : '' ?>">
                                                <?php if(!empty($request['tutor_avatar']) && file_exists('../uploads/avatars/' . $request['tutor_avatar'])): ?>
                                                    <img src="../uploads/avatars/<?= $request['tutor_avatar'] ?>" alt="Tutor Avatar" />
                                                <?php else: ?>
                                                    <span><?= strtoupper(substr($request['tutor_name'], 0, 1)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="font-bold"><?= htmlspecialchars($request['tutor_name']) ?></span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-base-content/60">Gói:</span>
                                    <span class="badge badge-outline uppercase"><?= $request['package_type'] ?></span>
                                </div>
                                <div>
                                    <span class="text-base-content/60">Points:</span>
                                    <span class="text-error font-bold"><?= $request['points_used'] ?> pts</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversation Section -->
                    <div id="conversationSection">
                    <?php if (!empty($request['answers'])): ?>
                        <?php foreach($request['answers'] as $index => $msg): ?>
                            <?php 
                                $is_me = ($msg['sender_id'] == $user_id);
                                $is_sender_tutor = ($msg['sender_id'] == $request['tutor_id']);
                            ?>
                            <div class="chat <?= $is_me ? 'chat-end' : 'chat-start' ?> mb-4" data-msg-id="<?= $msg['id'] ?>">
                                <div class="chat-image avatar <?= !empty($msg['sender_avatar']) ? '' : 'placeholder' ?>">
                                    <div class="w-10 rounded-full border border-base-300 overflow-hidden ring ring-offset-base-100 ring-2 <?= $is_me ? 'ring-primary/20' : 'ring-secondary/20' ?> <?= empty($msg['sender_avatar']) ? ($is_sender_tutor ? 'bg-success text-success-content' : 'bg-primary text-primary-content') . ' flex items-center justify-center font-bold' : '' ?>">
                                        <?php if(!empty($msg['sender_avatar']) && file_exists('../uploads/avatars/' . $msg['sender_avatar'])): ?>
                                            <img src="../uploads/avatars/<?= $msg['sender_avatar'] ?>" alt="Avatar" />
                                        <?php else: ?>
                                            <span><?= strtoupper(substr($msg['sender_name'], 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="chat-header opacity-50 text-xs mb-1">
                                    <?= htmlspecialchars($msg['sender_name']) ?>
                                    <time class="text-[10px]"><?= date('H:i', strtotime($msg['created_at'])) ?></time>
                                </div>
                                <div class="chat-bubble shadow-sm <?= $is_sender_tutor ? 'chat-bubble-success bg-success/20 text-success-content' : 'chat-bubble-primary' ?> text-sm">
                                    <div class="whitespace-pre-wrap"><?= trim(htmlspecialchars($msg['content'])) ?></div>
                                    
                                    <?php if(!empty($msg['attachment'])): ?>
                                        <div class="mt-2 pt-2 border-t border-base-content/10">
                                            <a href="/uploads/tutors/<?= htmlspecialchars($msg['attachment']) ?>" download class="btn btn-xs btn-ghost gap-1">
                                                <i class="fa-solid fa-paperclip"></i> File đính kèm
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-footer opacity-50 text-[10px]">
                                    <?= $is_sender_tutor ? 'Gia sư' : 'Học viên' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>

                    <!-- Rating Section (For Student to Rate) - Show if status is answered (not completed/disputed) -->
                    <?php if ($request['status'] === 'answered' && $is_student): ?>
                        <div class="card bg-base-100 shadow-xl border border-warning mt-6">
                            <div class="card-body">
                                <h3 class="card-title text-warning"><i class="fa-solid fa-star"></i> Đánh giá & Hoàn tất</h3>
                                <div class="alert alert-warning text-sm shadow-sm mb-4">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>Nếu bạn đánh giá <strong>4 sao trở lên</strong>, gia sư sẽ nhận được điểm. Nếu dưới <strong>4 sao</strong>, Admin sẽ xem xét lại.</span>
                                </div>
                                
                                <form id="ratingForm">
                                    <input type="hidden" name="action" value="rate_tutor">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    
                                    <div class="rating rating-lg rating-half mb-4">
                                        <input type="radio" name="rating" class="rating-hidden" />
                                        <input type="radio" name="rating" value="0.5" class="mask mask-star-2 mask-half-1 bg-orange-400" />
                                        <input type="radio" name="rating" value="1" class="mask mask-star-2 mask-half-2 bg-orange-400" />
                                        <input type="radio" name="rating" value="1.5" class="mask mask-star-2 mask-half-1 bg-orange-400" />
                                        <input type="radio" name="rating" value="2" class="mask mask-star-2 mask-half-2 bg-orange-400" />
                                        <input type="radio" name="rating" value="2.5" class="mask mask-star-2 mask-half-1 bg-orange-400" />
                                        <input type="radio" name="rating" value="3" class="mask mask-star-2 mask-half-2 bg-orange-400" />
                                        <input type="radio" name="rating" value="3.5" class="mask mask-star-2 mask-half-1 bg-orange-400" />
                                        <input type="radio" name="rating" value="4" class="mask mask-star-2 mask-half-2 bg-orange-400" />
                                        <input type="radio" name="rating" value="4.5" class="mask mask-star-2 mask-half-1 bg-orange-400" />
                                        <input type="radio" name="rating" value="5" class="mask mask-star-2 mask-half-2 bg-orange-400" checked />
                                    </div>
                                    
                                    <div class="form-control">
                                        <textarea name="review" required class="textarea textarea-bordered" placeholder="Nhận xét của bạn (Bắt buộc)"></textarea>
                                    </div>
                                    
                                    <div class="card-actions justify-end mt-4">
                                        <button type="submit" class="btn btn-warning">Gửi đánh giá</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Completed/Review Display -->
                    <?php if ($request['status'] === 'completed' || $request['status'] === 'disputed'): ?>
                        <div class="card bg-base-100 shadow-xl border border-base-200 mt-6">
                            <div class="card-body">
                                <h3 class="card-title"><i class="fa-solid fa-star text-yellow-500"></i> Đánh giá từ học viên</h3>
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="rating rating-md rating-half pointer-events-none">
                                        <input type="radio" class="rating-hidden" />
                                        <?php for($i=0.5; $i<=5; $i+=0.5): ?>
                                            <input type="radio" class="mask mask-star-2 bg-orange-400 <?= fmod($i, 1) !== 0.0 ? 'mask-half-1' : 'mask-half-2' ?>" <?= ($request['rating'] ?? 0) == $i ? 'checked' : '' ?> />
                                        <?php endfor; ?>
                                    </div>
                                    <span class="font-bold text-lg text-orange-500"><?= $request['rating'] ?>/5</span>
                                </div>
                                <p class="italic text-base-content/70">"<?= htmlspecialchars($request['review'] ?? 'Không có nhận xét') ?>"</p>
                                
                                <?php if($request['status'] === 'disputed'): ?>
                                    <div class="alert alert-error mt-4">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <span>Đang chờ Admin xử lý khiếu nại (Đánh giá thấp).</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tutor Answer Input (Allow answering only if pending) -->
                    <?php if ($is_tutor && $request['status'] === 'pending'): ?>
                        <div class="card bg-base-100 shadow-xl border border-primary mt-6">
                            <div class="card-body">
                                <h3 class="card-title text-primary"><i class="fa-solid fa-pen-nib"></i> <?= $request['status'] === 'answered' ? 'Gửi thêm câu trả lời' : 'Trả lời câu hỏi' ?></h3>
                                <p class="text-sm mb-4">Bạn sẽ nhận được <strong><?= $request['points_used'] ?> Points</strong> sau khi học viên hài lòng (đánh giá >= 4 sao).</p>
                                
                                <form id="answerForm" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="answer_request">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <div class="form-control">
                                        <textarea name="content" required class="textarea textarea-bordered h-48 focus:textarea-primary" placeholder="Nhập câu trả lời chi tiết của bạn tại đây..."></textarea>
                                    </div>
                                    <!-- Attachment simple UI -->
                                    <div class="form-control mt-2">
                                        <label class="label"><span class="label-text">Đính kèm file (tùy chọn)</span></label>
                                        <input type="file" name="attachment" class="file-input file-input-bordered w-full max-w-xs" />
                                    </div>
                                    
                                    <div class="card-actions justify-end mt-4 gap-2">
                                        <button type="button" onclick="finishRequestManual()" class="btn btn-outline btn-success">
                                            <i class="fa-solid fa-check-double"></i> Kết thúc hỗ trợ
                                        </button>
                                        <button type="submit" class="btn btn-primary">Gửi câu trả lời</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($is_student && $request['status'] === 'pending'): ?>
                        <!-- Student Message Input -->
                        <div class="card bg-base-100 shadow-xl border border-secondary mt-6">
                            <div class="card-body">
                                <h3 class="card-title text-secondary"><i class="fa-solid fa-reply"></i> Đổi thoại với Gia sư</h3>
                                <p class="text-sm mb-4">Bạn có thể hỏi thêm chi tiết hoặc làm rõ câu trả lời tại đây.</p>
                                
                                <form id="chatForm" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="student_chat">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <div class="form-control">
                                        <textarea name="content" required class="textarea textarea-bordered focus:textarea-secondary" placeholder="Nhập câu hỏi hoặc phản hồi của bạn..."></textarea>
                                    </div>
                                    <div class="form-control mt-2">
                                        <input type="file" name="attachment" class="file-input file-input-bordered file-input-sm w-full max-w-xs" />
                                    </div>
                                    
                                    <div class="card-actions justify-end mt-4">
                                        <button type="submit" class="btn btn-secondary">Gửi tin nhắn</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if($request['status'] === 'pending'): ?>
                            <div class="alert alert-info mt-4">
                                <i class="fa-solid fa-hourglass-half"></i>
                                <span>Đang chờ Gia sư phản hồi lần đầu.</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Info -->
                <div class="col-span-1">
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <h3 class="font-bold text-lg mb-4">Hướng dẫn</h3>
                            <ul class="steps steps-vertical text-sm">
                                <li class="step step-primary">Đặt câu hỏi</li>
                                <li class="step <?= ($request['status'] == 'answered' || $request['status'] == 'completed') ? 'step-primary' : '' ?>">Gia sư trả lời</li>
                                <li class="step <?= $request['status'] == 'completed' ? 'step-primary' : '' ?>">Hoàn tất & Đánh giá</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
</div>

<script>
const CURRENT_USER_ID = <?= $user_id ?>;
const REQUEST_ID = <?= $request_id ?>;
const TUTOR_ID = <?= $request['tutor_id'] ?>;
let lastMsgId = 0;
let currentStatus = '<?= $request['status'] ?>';

// Update lastMsgId from existing messages
document.querySelectorAll('[data-msg-id]').forEach(msg => {
    const id = parseInt(msg.dataset.msgId);
    if(id > lastMsgId) lastMsgId = id;
});

// Generic handler for tutor request actions
async function handleTutorAction(formId, action) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
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
                // For chat/answer messages, we don't need alert/reload anymore
                if (action === 'send_chat_message' || action === 'answer_request' || action === 'student_chat') {
                    form.reset();
                    pollMessages(); // Poll immediately
                } else {
                    alert(data.message);
                    location.reload();
                }
            } else {
                alert(data.message);
            }
        } catch (err) {
            console.error(err);
        } finally {
            btn.disabled = false;
        }
    });
}

function scrollToBottom() {
    window.scrollTo({
        top: document.body.scrollHeight,
        behavior: 'smooth'
    });
}

function renderMessage(msg) {
    const isMe = msg.sender_id == CURRENT_USER_ID;
    const isSenderTutor = msg.sender_id == TUTOR_ID;
    const side = isMe ? 'chat-end' : 'chat-start';
    const bubbleClass = isSenderTutor ? 'chat-bubble-success bg-success/20 text-success-content' : 'chat-bubble-primary';
    const ringClass = isMe ? 'ring-primary/20' : 'ring-secondary/20';
    const roleTxt = isSenderTutor ? 'Gia sư' : 'Học viên';
    
    // Format time
    const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

    let avatarContent = '';
    let placeholderClasses = '';
    if(msg.sender_avatar) {
        avatarContent = `<img src="../uploads/avatars/${msg.sender_avatar}" alt="Avatar" />`;
    } else {
        const bg = isSenderTutor ? 'bg-success text-success-content' : 'bg-primary text-primary-content';
        placeholderClasses = `${bg} flex items-center justify-center font-bold`;
        avatarContent = `<span>${msg.sender_name.charAt(0).toUpperCase()}</span>`;
    }

    let attachmentHtml = '';
    if(msg.attachment) {
        attachmentHtml = `
            <div class="mt-2 pt-2 border-t border-base-content/10">
                <a href="/uploads/tutors/${msg.attachment}" download class="btn btn-xs btn-ghost gap-1">
                    <i class="fa-solid fa-paperclip"></i> File đính kèm
                </a>
            </div>
        `;
    }

    return `
        <div class="chat ${side} mb-4" data-msg-id="${msg.id}">
            <div class="chat-image avatar ${msg.sender_avatar ? '' : 'placeholder'}">
                <div class="w-10 rounded-full border border-base-300 overflow-hidden ring ring-offset-base-100 ring-2 ${ringClass} ${placeholderClasses}">
                    ${avatarContent}
                </div>
            </div>
            <div class="chat-header opacity-50 text-xs mb-1">
                ${msg.sender_name}
                <time class="text-[10px]">${time}</time>
            </div>
            <div class="chat-bubble shadow-sm ${bubbleClass} text-sm">
                <div class="whitespace-pre-wrap">${msg.content.trim()}</div>
                ${attachmentHtml}
            </div>
            <div class="chat-footer opacity-50 text-[10px]">
                ${roleTxt}
            </div>
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
                        container.innerHTML += renderMessage(msg);
                        lastMsgId = parseInt(msg.id);
                    }
                });
                scrollToBottom();
            }

            // If status changed, reload to update UI (forms, badges etc)
            if(data.status && data.status !== currentStatus) {
                setTimeout(() => location.reload(), 2000); // Small delay to let user see the final message
            }
        }
        
        // If status changed to something that requires reload (e.g. completed)
        // But status in detail is stable usually.
    } catch (err) {
        console.error('Polling error:', err);
    }
}

// Start polling every 3 seconds
setInterval(pollMessages, 3000);

async function finishRequestManual() {
    vsdConfirm({
        title: 'Xác nhận hoàn tất',
        message: 'Xác nhận hoàn tất hỗ trợ cho yêu cầu này? Sau khi hoàn tất, bạn không thể gửi thêm tin nhắn và học viên sẽ thực hiện đánh giá.',
        type: 'success',
        confirmText: 'Kết thúc ngay',
        onConfirm: async function() {
            try {
                const formData = new FormData();
                formData.append('action', 'finish_request');
                formData.append('request_id', <?= $request['id'] ?>);
                
                const res = await fetch('/api/tutor_chat.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if(data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (err) {
                console.error(err);
            }
        }
    });
}

// Initialize all forms
handleTutorAction('answerForm', 'send_chat_message');
handleTutorAction('chatForm', 'send_chat_message');
handleTutorAction('ratingForm', 'rate_tutor');
</script>
