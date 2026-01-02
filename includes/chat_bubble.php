<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
if (!isUserLoggedIn()) return;
$is_tutor_user = isTutor(getCurrentUserId());
?>
<!-- Tutor Chat Bubble -->
<div id="tutor-chat-container" class="fixed bottom-6 right-6 z-[1000] flex flex-col items-end">
    <!-- Chat Window -->
    <div id="tutor-chat-window" class="hidden mb-4 w-72 sm:w-80 h-[400px] bg-base-100 rounded-2xl shadow-2xl border border-base-300 flex flex-col overflow-hidden transition-all duration-300 scale-95 opacity-0 origin-bottom-right">
        <!-- Header -->
        <div class="bg-primary p-2.5 text-primary-content flex justify-between items-center shrink-0">
            <div class="flex items-center gap-2">
                <div class="bg-primary-focus p-1.5 rounded-lg">
                    <i class="fa-solid fa-graduation-cap text-[10px]"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-bold text-[10px] truncate" id="chat-header-title">Hỗ trợ học tập</h3>
                    <p class="text-[8px] opacity-80 truncate" id="chat-header-status">Gia sư & Học viên</p>
                </div>
            </div>
            <div class="flex gap-1 items-center">
                <?php if($is_tutor_user): ?>
                    <button onclick="finishChatRequest()" class="btn btn-ghost btn-xs btn-circle hidden" id="finish-request-btn" title="Kết thúc hỗ trợ">
                        <i class="fa-solid fa-check-double text-success text-[10px]"></i>
                    </button>
                <?php endif; ?>
                <button onclick="toggleChatList()" class="btn btn-ghost btn-xs btn-circle hidden" id="back-to-list-btn">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                </button>
                <button onclick="toggleTutorChat()" class="btn btn-ghost btn-xs btn-circle">
                    <i class="fa-solid fa-times text-[10px]"></i>
                </button>
            </div>
        </div>

        <!-- Chat Body -->
        <div class="flex-1 overflow-y-auto p-2.5 bg-base-200/40" id="chat-body">
            <div id="chat-loading" class="hidden flex flex-col items-center justify-center h-full gap-2 opacity-50">
                <span class="loading loading-spinner loading-xs"></span>
            </div>

            <div id="chat-list-view" class="hidden space-y-1.5"></div>
            <div id="chat-messages-view" class="hidden space-y-2.5 pb-2"></div>
        </div>

        <!-- Footer -->
        <div id="chat-footer" class="hidden p-2 border-t border-base-300 bg-base-100">
            <div id="chat-closed-notice" class="hidden text-center py-1 opacity-50 text-[9px] font-bold uppercase tracking-wider">Hội thoại đã đóng</div>
            <form id="chat-send-form" class="flex flex-col gap-1.5">
                <div class="flex gap-1.5 items-center">
                    <button type="button" id="chat-attachment-btn" onclick="document.getElementById('chat-file-input').click()" class="btn btn-ghost btn-xs btn-square hidden">
                        <i class="fa-solid fa-paperclip text-[10px]"></i>
                    </button>
                    <input type="file" id="chat-file-input" class="hidden" onchange="handleFileSelect(this)">
                    
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="request_id" id="chat-active-request-id">
                    <input type="text" name="message" id="chat-input" placeholder="Viết tin nhắn..." class="input input-bordered input-xs flex-1 focus:outline-none text-[11px]" autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-xs btn-square">
                        <i class="fa-solid fa-paper-plane text-[10px]"></i>
                    </button>
                </div>
                <div id="chat-file-preview" class="hidden flex items-center justify-between bg-base-200 p-1 px-2 rounded-lg text-[9px]">
                    <span class="truncate max-w-[150px]" id="preview-filename"></span>
                    <button type="button" onclick="clearFile()" class="text-error"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Button -->
    <button onclick="toggleTutorChat()" id="chat-toggle-btn" class="btn btn-primary btn-circle btn-md shadow-lg hover:scale-105 transition-transform ring-2 ring-primary ring-offset-2 ring-offset-base-100">
        <div class="relative">
            <i class="fa-solid fa-comment-dots text-lg"></i>
            <span id="chat-unread-badge" class="badge badge-error badge-xs absolute -top-1 -right-1 hidden"></span>
        </div>
    </button>
</div>

<style>
#tutor-chat-window.active {
    display: flex !important;
    transform: scale(1) !important;
    opacity: 1 !important;
}
.chat-bubble-student {
    background-color: oklch(var(--p));
    color: oklch(var(--pc));
    border-radius: 10px 10px 0 10px;
}
.chat-bubble-tutor {
    background-color: oklch(var(--b1));
    color: oklch(var(--bc));
    border-radius: 10px 10px 10px 0;
    border: 1px solid oklch(var(--b3));
}
.chat-bubble { min-height: unset; }
.system-card {
    background: color-mix(in oklch, oklch(var(--wa)), transparent 90%);
    border: 1px solid oklch(var(--wa)/20%);
    border-radius: 12px;
    padding: 10px;
    margin: 10px 0;
}
</style>

<script>
let chatOpen = false;
let currentView = 'list';
let activeRequestId = null;
let lastMessageId = 0;
let pollInterval = null;
let currentRequestStatus = 'pending';

const currentUserId = <?= (int)getCurrentUserId() ?>;
const isTutorUser = <?= $is_tutor_user ? 'true' : 'false' ?>;

function toggleTutorChat() {
    const win = document.getElementById('tutor-chat-window');
    chatOpen = !chatOpen;
    if (chatOpen) {
        win.classList.remove('hidden');
        requestAnimationFrame(() => win.classList.add('active'));
        if (currentView === 'list') loadChatList();
        else openConversation(activeRequestId);
    } else {
        win.classList.remove('active');
        stopPolling();
        setTimeout(() => { if (!chatOpen) win.classList.add('hidden'); }, 300);
    }
}

async function loadChatList() {
    stopPolling();
    showLoading(true);
    document.getElementById('chat-list-view').classList.add('hidden');
    document.getElementById('chat-messages-view').classList.add('hidden');
    document.getElementById('chat-footer').classList.add('hidden');
    document.getElementById('back-to-list-btn').classList.add('hidden');
    if(document.getElementById('finish-request-btn')) document.getElementById('finish-request-btn').classList.add('hidden');
    
    document.getElementById('chat-header-title').innerText = 'Hội thoại gia sư';
    document.getElementById('chat-header-status').innerText = 'Gia sư & Học viên';
    
    try {
        const res = await fetch('/api/tutor_chat.php?action=get_chats');
        const data = await res.json();
        if (data.success) {
            const list = document.getElementById('chat-list-view');
            list.innerHTML = '';
            if (data.chats.length === 0) {
                list.innerHTML = `<div class="text-center py-10 opacity-50 text-[10px]">Không có hội thoại nào.</div>`;
            } else {
                data.chats.forEach(chat => {
                    const otherParty = (chat.student_id == currentUserId) ? chat.tutor_name : chat.student_name;
                    const date = new Date(chat.last_message_time || chat.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    list.innerHTML += `
                        <div onclick="openConversation(${chat.id})" class="flex items-center gap-2 p-2 bg-base-100 hover:bg-base-300 rounded-xl cursor-pointer transition-colors border border-base-200 mb-1">
                            <div class="avatar placeholder">
                                <div class="bg-neutral-focus text-neutral-content rounded-full w-7 text-[9px]">
                                    <span>${otherParty.charAt(0)}</span>
                                </div>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-bold text-[9px] truncate">${otherParty}</h4>
                                    <span class="text-[7px] opacity-70">${date}</span>
                                </div>
                                <p class="text-[8px] opacity-60 truncate">${chat.last_message || 'Chưa có tin nhắn...'}</p>
                            </div>
                        </div>
                    `;
                });
            }
            showLoading(false);
            list.classList.remove('hidden');
            currentView = 'list';
        }
    } catch (err) { showLoading(false); }
}

async function openConversation(requestId) {
    showLoading(true);
    activeRequestId = requestId;
    lastMessageId = 0;
    document.getElementById('chat-list-view').classList.add('hidden');
    document.getElementById('back-to-list-btn').classList.remove('hidden');
    
    document.getElementById('chat-active-request-id').value = requestId;
    
    try {
        const res = await fetch('/api/tutor_chat.php?action=get_messages&request_id=' + requestId);
        const data = await res.json();
        if (data.success) {
            updateChatUI(data);
            showLoading(false);
            document.getElementById('chat-messages-view').classList.remove('hidden');
            document.getElementById('chat-footer').classList.remove('hidden');
            currentView = 'messages';
            scrollToBottom();
            startPolling();
        }
    } catch (err) { showLoading(false); }
}

function updateChatUI(data) {
    const status = data.request.status;
    currentRequestStatus = status;
    const isMeStudent = (data.request.student_id == currentUserId);
    const packageType = data.request.package_type;
    
    // Header & Buttons
    document.getElementById('chat-header-title').innerText = data.request.other_party;
    document.getElementById('chat-header-status').innerText = 'Dự án: ' + data.request.title;
    
    if(isTutorUser && status === 'pending' && document.getElementById('finish-request-btn')) {
        document.getElementById('finish-request-btn').classList.remove('hidden');
    } else {
        if(document.getElementById('finish-request-btn')) document.getElementById('finish-request-btn').classList.add('hidden');
    }

    const attachBtn = document.getElementById('chat-attachment-btn');
    if(attachBtn) {
        if(packageType === 'premium') attachBtn.classList.remove('hidden');
        else attachBtn.classList.add('hidden');
    }

    // Messages
    const view = document.getElementById('chat-messages-view');
    view.innerHTML = '';
    
    // Prepend Initial Request
    appendInitialMessage(data.request);

    // Append history messages
    data.messages.forEach(msg => {
        appendMessageUI(msg);
        if(msg.id > lastMessageId) lastMessageId = msg.id;
    });

    // Rating card
    checkAndShowRatingCard(status, isMeStudent, data.request.id);

    // Footer notice
    updateFooterStatus(status);
}

function appendMessageUI(msg) {
    const view = document.getElementById('chat-messages-view');
    const isMe = msg.sender_id == currentUserId;
    const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    let attachmentHtml = '';
    if(msg.attachment) {
        const ext = msg.attachment.split('.').pop().toLowerCase();
        if(['jpg','jpeg','png','gif'].includes(ext)) {
            attachmentHtml = `<img src="/uploads/tutors/${msg.attachment}" class="rounded-lg mt-1 max-w-full cursor-pointer" onclick="window.open(this.src)">`;
        } else {
            attachmentHtml = `<a href="/uploads/tutors/${msg.attachment}" target="_blank" class="flex items-center gap-1 mt-1 text-[8px] underline"><i class="fa-solid fa-file"></i> Tải file</a>`;
        }
    }

    view.innerHTML += `
        <div class="chat ${isMe ? 'chat-end' : 'chat-start'}" data-msg-id="${msg.id}">
            <div class="chat-header opacity-50 text-[7px] mb-0.5">${msg.sender_name} <time>${time}</time></div>
            <div class="chat-bubble text-[10px] ${isMe ? 'chat-bubble-student' : 'chat-bubble-tutor'} py-1.5 px-2.5 shadow-sm leading-snug">
                ${msg.content}
                ${attachmentHtml}
            </div>
        </div>
    `;
}

function appendInitialMessage(request) {
    const view = document.getElementById('chat-messages-view');
    const initialTime = new Date(request.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    let initialAttachHtml = '';
    if(request.initial_attachment) {
        const ext = request.initial_attachment.split('.').pop().toLowerCase();
        if(['jpg','jpeg','png','gif'].includes(ext)) {
            initialAttachHtml = `<img src="/uploads/tutors/${request.initial_attachment}" class="rounded-lg mt-1 max-w-full cursor-pointer" onclick="window.open(this.src)">`;
        } else {
            initialAttachHtml = `<a href="/uploads/tutors/${request.initial_attachment}" target="_blank" class="flex items-center gap-1 mt-1 text-[8px] underline"><i class="fa-solid fa-file"></i> Tải file</a>`;
        }
    }
    const isInitialMe = (request.student_id == currentUserId);
    view.innerHTML += `
        <div class="chat ${isInitialMe ? 'chat-end' : 'chat-start'}">
            <div class="chat-header opacity-50 text-[7px] mb-0.5">${request.student_name} <time>${initialTime}</time></div>
            <div class="chat-bubble text-[10px] ${isInitialMe ? 'chat-bubble-student' : 'chat-bubble-tutor'} py-1.5 px-2.5 shadow-sm leading-snug">
                <div class="font-bold border-b border-white/20 mb-1 pb-1">Câu hỏi: ${request.title}</div>
                ${request.initial_content}
                ${initialAttachHtml}
            </div>
        </div>
    `;
}

function checkAndShowRatingCard(status, isMeStudent, requestId) {
    if(isMeStudent && status === 'answered') {
        const view = document.getElementById('chat-messages-view');
        const existingRating = document.getElementById('chat-rating-form');
        if(existingRating) return;

        view.innerHTML += `
            <div class="system-card text-center space-y-2" id="rating-card">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <i class="fa-solid fa-star text-warning"></i>
                    <span class="font-bold text-[11px]">Đánh giá Gia sư</span>
                </div>
                <p class="text-[9px] opacity-70 mb-2">Gia sư đã hoàn tất hỗ trợ. Vui lòng đánh giá để kết thúc dự án.</p>
                <form id="chat-rating-form" class="space-y-2">
                    <input type="hidden" name="action" value="rate_tutor">
                    <input type="hidden" name="request_id" value="${activeRequestId}">
                    
                    <div class="rating rating-xs rating-half justify-center">
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
                    
                    <textarea name="review" required class="textarea textarea-bordered textarea-xs w-full h-12 text-[10px]" placeholder="Nhận xét của bạn..."></textarea>
                    <button type="submit" class="btn btn-warning btn-xs w-full text-[10px]">Gửi & Hoàn tất</button>
                </form>
            </div>
        `;
        bindRatingForm();
    }
}

function bindRatingForm() {
    const ratingForm = document.getElementById('chat-rating-form');
    if(ratingForm) {
        ratingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const res = await fetch('/handler/tutor_handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert("Đã gửi đánh giá thành công!");
                    openConversation(activeRequestId);
                } else { alert(data.message); }
            } catch (err) { console.error(err); }
        });
    }
}

function updateFooterStatus(status) {
    const isClosed = (status === 'completed' || status === 'cancelled' || status === 'rejected' || status === 'answered');
    document.getElementById('chat-send-form').classList.toggle('hidden', isClosed);
    document.getElementById('chat-closed-notice').classList.toggle('hidden', !isClosed);
    if(isClosed && status === 'answered') {
        document.getElementById('chat-closed-notice').innerText = "Vui lòng đánh giá để tiếp tục";
    } else {
        document.getElementById('chat-closed-notice').innerText = "Hội thoại đã đóng";
    }
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(async () => {
        if (!chatOpen || currentView !== 'messages' || !activeRequestId) return;
        try {
            const res = await fetch(`/api/tutor_chat.php?action=poll_messages&request_id=${activeRequestId}&last_id=${lastMessageId}`);
            const data = await res.json();
            if (data.success && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendMessageUI(msg);
                    if(msg.id > lastMessageId) lastMessageId = msg.id;
                });
                scrollToBottom();
            }
            // Check status change (e.g. tutor finished chat)
            if (data.status !== currentRequestStatus) {
                openConversation(activeRequestId);
            }
        } catch (e) {}
    }, 3000);
}

function stopPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = null;
}

async function finishChatRequest() {
    vsdConfirm({
        title: 'Hoàn tất hỗ trợ',
        message: 'Xác nhận hoàn tất hỗ trợ? Cả hai bên sẽ không thể nhắn tin nữa và học viên sẽ nhận được yêu cầu đánh giá.',
        confirmText: 'Hoàn tất',
        type: 'success',
        onConfirm: async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'finish_request');
                formData.append('request_id', activeRequestId);
                const res = await fetch('/api/tutor_chat.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    openConversation(activeRequestId);
                }
            } catch (err) { console.error(err); }
        }
    });
}

function handleFileSelect(input) {
    if(input.files && input.files[0]) {
        document.getElementById('preview-filename').innerText = input.files[0].name;
        document.getElementById('chat-file-preview').classList.remove('hidden');
    }
}

function clearFile() {
    document.getElementById('chat-file-input').value = '';
    document.getElementById('chat-file-preview').classList.add('hidden');
}

function toggleChatList() { if (currentView === 'messages') loadChatList(); }
function showLoading(show) { document.getElementById('chat-loading').classList.toggle('hidden', !show); }
function scrollToBottom() { setTimeout(() => { const b = document.getElementById('chat-body'); if(b) b.scrollTo({top: b.scrollHeight, behavior:'smooth'}); }, 50); }

document.getElementById('chat-send-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    const fileInput = document.getElementById('chat-file-input');
    if (!msg && !fileInput.files.length) return;
    const formData = new FormData(this);
    if(fileInput.files.length) formData.append('attachment', fileInput.files[0]);
    input.value = '';
    clearFile();
    try {
        const res = await fetch('/api/tutor_chat.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            // After sending, immediately poll once to show my message or just re-poll
            // Actually openConversation handles lastMessageId correctly but we can just let polling handle it
            // or force one refresh
            const pollRes = await fetch(`/api/tutor_chat.php?action=poll_messages&request_id=${activeRequestId}&last_id=${lastMessageId}`);
            const pollData = await pollRes.json();
            if (pollData.success) {
                pollData.messages.forEach(m => {
                    appendMessageUI(m);
                    if(m.id > lastMessageId) lastMessageId = m.id;
                });
                scrollToBottom();
            }
        } else alert(data.message);
    } catch (err) { console.error('Send Error:', err); }
});
</script>
