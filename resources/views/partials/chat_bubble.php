<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
if (!isUserLoggedIn()) return;
$is_tutor_user = isTutor(getCurrentUserId());
?>

<!-- Tutor Chat Bubble Premium -->
<div id="tutor-chat-container" class="fixed bottom-24 lg:bottom-6 right-6 z-[1000] flex flex-col items-end">
    <!-- Chat Window Premium -->
    <div id="tutor-chat-window" class="hidden mb-6 w-[350px] max-w-[90vw] h-[580px] max-h-[70vh] bg-base-100/80 backdrop-blur-[40px] rounded-[2.5rem] shadow-[0_30px_100px_-20px_rgba(0,0,0,0.3)] border border-white/10 flex flex-col overflow-hidden transition-all duration-500 scale-90 opacity-0 origin-bottom-right">
        
        <!-- Premium Header -->
        <div class="relative bg-gradient-to-br from-primary to-primary-focus p-6 text-primary-content flex justify-between items-center shrink-0 overflow-hidden">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10"></div>
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
            
            <div class="relative z-10 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-lg border border-white/20" id="chat-header-icon-wrapper">
                    <i class="fa-solid fa-graduation-cap text-xl"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-black text-sm tracking-tight truncate leading-tight" id="chat-header-title">Hỗ trợ học tập</h3>
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 bg-success rounded-full animate-pulse"></span>
                        <p class="text-[10px] font-bold opacity-80 uppercase tracking-widest truncate" id="chat-header-status">Online</p>
                    </div>
                </div>
            </div>

            <div class="relative z-10 flex gap-2">
                <?php if($is_tutor_user): ?>
                    <button onclick="finishChatRequest()" class="btn btn-ghost btn-circle btn-sm bg-white/10 hover:bg-white/20 border-none hidden" id="finish-request-btn" title="Kết thúc hỗ trợ">
                        <i class="fa-solid fa-check-double text-success text-sm"></i>
                    </button>
                <?php endif; ?>
                <button onclick="toggleChatList()" class="btn btn-ghost btn-circle btn-sm bg-white/10 hover:bg-white/20 border-none hidden" id="back-to-list-btn">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </button>
                <button onclick="toggleTutorChat()" class="btn btn-ghost btn-circle btn-sm bg-white/10 hover:bg-white/20 border-none">
                    <i class="fa-solid fa-times text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Chat Body Premium -->
        <div class="flex-1 overflow-y-auto p-5 scrollbar-vsd" id="chat-body" style="background-image: radial-gradient(circle at 2px 2px, oklch(var(--bc)/.03) 1px, transparent 0); background-size: 24px 24px;">
            <div id="chat-loading" class="hidden flex flex-col items-center justify-center h-full gap-4 opacity-40">
                <div class="loading loading-spinner loading-md text-primary"></div>
                <span class="text-[10px] font-black uppercase tracking-widest">Đang tải dữ liệu...</span>
            </div>

            <div id="chat-list-view" class="hidden space-y-3"></div>
            <div id="chat-messages-view" class="hidden space-y-4 pb-4"></div>
        </div>

        <!-- Premium Footer -->
        <div id="chat-footer" class="hidden p-5 bg-base-100/50 backdrop-blur-md border-t border-white/5">
            <div id="chat-closed-notice" class="hidden text-center py-3 bg-base-200/50 rounded-2xl text-[10px] font-black uppercase tracking-widest text-base-content/40">
                <i class="fa-solid fa-lock mr-2"></i> Hội thoại đã đóng
            </div>
            
            <form id="chat-send-form" class="flex flex-col gap-3 allow-double-submit">
                <div class="relative group">
                    <input type="text" name="message" id="chat-input" placeholder="Viết tin nhắn của bạn..." class="input w-full bg-base-200/50 border-none rounded-2xl pr-12 focus:ring-2 focus:ring-primary/20 transition-all font-medium text-sm h-12" autocomplete="off">
                    
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                        <button type="button" id="chat-attachment-btn" onclick="document.getElementById('chat-file-input').click()" class="btn btn-ghost btn-sm btn-circle text-base-content/40 hover:text-primary transition-colors hidden">
                            <i class="fa-solid fa-paperclip"></i>
                        </button>
                        <input type="file" id="chat-file-input" class="hidden" onchange="handleFileSelect(this)">
                        
                        <button type="submit" class="btn btn-primary btn-sm btn-circle shadow-lg shadow-primary/20 hover:scale-110 active:scale-95 transition-all">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                        </button>
                    </div>
                </div>

                <div id="chat-file-preview" class="hidden flex items-center justify-between bg-primary/10 border border-primary/20 p-2 px-4 rounded-xl text-[10px] font-bold text-primary animate-in fade-in slide-in-from-bottom-2">
                    <div class="flex items-center gap-2 truncate">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span class="truncate" id="preview-filename"></span>
                    </div>
                    <button type="button" onclick="clearFile()" class="btn btn-ghost btn-xs btn-circle hover:bg-primary/20"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="request_id" id="chat-active-request-id">
            </form>
        </div>
    </div>

    <!-- Toggle Button Premium -->
    <button onclick="toggleTutorChat()" id="chat-toggle-btn" class="group relative w-16 h-16 flex items-center justify-center bg-primary rounded-[1.5rem] shadow-[0_20px_40px_-10px_rgba(oklch(var(--p))/0.4)] hover:shadow-[0_25px_50px_-12px_rgba(oklch(var(--p))/0.5)] hover:-translate-y-1 active:scale-95 transition-all duration-300 ring-2 ring-primary/20 ring-offset-4 ring-offset-base-100">
        <div class="absolute inset-0 bg-gradient-to-tr from-white/20 to-transparent rounded-[1.5rem] pointer-events-none"></div>
        <div class="relative z-10">
            <i class="fa-solid fa-comment-dots text-white text-2xl group-hover:scale-110 transition-transform"></i>
            <span id="chat-unread-badge" class="absolute -top-1 -right-1 min-w-[20px] h-[20px] bg-white text-primary text-[10px] font-black rounded-full flex items-center justify-center shadow-lg border-2 border-primary hidden animate-bounce">0</span>
        </div>
    </button>
</div>

<style>
/* Premium Scrollbar */
.scrollbar-vsd::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-vsd::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-vsd::-webkit-scrollbar-thumb {
    background: oklch(var(--bc)/0.1);
    border-radius: 10px;
}
.scrollbar-vsd::-webkit-scrollbar-thumb:hover {
    background: oklch(var(--bc)/0.2);
}

#tutor-chat-window.active {
    display: flex !important;
    transform: scale(1) !important;
    opacity: 1 !important;
}

/* Chat Bubbles Modernized */
.chat-bubble-vsd {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 1.5rem;
    font-size: 13px;
    line-height: 1.5;
    position: relative;
    box-shadow: 0 4px 15px -3px rgba(0,0,0,0.05);
}

.chat-end .chat-bubble-vsd {
    background: linear-gradient(135deg, oklch(var(--p)) 0%, oklch(var(--p) / 0.8) 100%);
    color: white;
    border-bottom-right-radius: 4px;
    box-shadow: 0 10px 20px -5px oklch(var(--p) / 0.3);
}

.chat-start .chat-bubble-vsd {
    background: var(--glass-bg, white);
    color: oklch(var(--bc));
    border-bottom-left-radius: 4px;
    border: 1px solid oklch(var(--bc)/0.05);
}

.chat-vsd-meta {
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    opacity: 0.6;
    margin-bottom: 6px;
    color: oklch(var(--p));
}

.chat-time-vsd {
    font-size: 9px;
    font-weight: 800;
    opacity: 0.3;
    margin-bottom: 4px;
    white-space: nowrap;
}

.chat-item-premium {
    background: oklch(var(--b1));
    border: 1px solid oklch(var(--bc)/0.05);
    padding: 14px;
    border-radius: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.chat-item-premium:hover {
    background: oklch(var(--b2));
    border-color: oklch(var(--p)/0.2);
    transform: translateX(5px);
    box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05);
}

/* System Card Premium */
.system-card-vsd {
    background: linear-gradient(to bottom, oklch(var(--p)/0.05), transparent);
    border: 1px solid oklch(var(--p)/0.1);
    border-radius: 2rem;
    padding: 20px;
    margin: 20px 0;
    backdrop-filter: blur(10px);
}

.initial-msg-vsd {
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 8px;
    padding-bottom: 8px;
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
        
        // Hide unread badge when opened
        document.getElementById('chat-unread-badge').classList.add('hidden');
    } else {
        win.classList.remove('active');
        stopPolling();
        setTimeout(() => { if (!chatOpen) win.classList.add('hidden'); }, 500);
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
                list.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16 text-center opacity-30">
                        <i class="fa-solid fa-comments text-5xl mb-4"></i>
                        <p class="text-[10px] font-black uppercase tracking-widest">Không có hội thoại nào</p>
                    </div>
                `;
            } else {
                data.chats.forEach(chat => {
                    const otherParty = (chat.student_id == currentUserId) ? chat.tutor_name : chat.student_name;
                    const date = new Date(chat.last_message_time || chat.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    list.innerHTML += `
                        <div onclick="openConversation(${chat.id})" class="chat-item-premium group flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary font-black shadow-inner border border-primary/5">
                                ${otherParty.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <div class="flex justify-between items-center mb-0.5">
                                    <h4 class="font-black text-xs tracking-tight group-hover:text-primary transition-colors truncate">${otherParty}</h4>
                                    <span class="text-[9px] font-black uppercase opacity-40">${date}</span>
                                </div>
                                <p class="text-[11px] font-medium opacity-50 truncate">${chat.last_message || 'Chưa có tin nhắn...'}</p>
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
    document.getElementById('chat-header-status').innerText = data.request.title;
    
    if(isTutorUser && status === 'pending' && document.getElementById('finish-request-btn')) {
        document.getElementById('finish-request-btn').classList.remove('hidden');
    } else {
        if(document.getElementById('finish-request-btn')) document.getElementById('finish-request-btn').classList.add('hidden');
    }

    const attachBtn = document.getElementById('chat-attachment-btn');
    if(attachBtn) {
        if(packageType === 'premium' || isTutorUser) attachBtn.classList.remove('hidden');
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
            attachmentHtml = `<img src="/uploads/tutors/${msg.attachment}" class="rounded-xl mt-3 max-w-full cursor-pointer shadow-lg active:scale-95 transition-transform" onclick="window.open(this.src)">`;
        } else {
            attachmentHtml = `
                <a href="/uploads/tutors/${msg.attachment}" target="_blank" class="flex items-center gap-2 mt-3 p-2 bg-black/10 rounded-xl text-[10px] font-bold border border-white/10 hover:bg-black/20 transition-colors">
                    <i class="fa-solid fa-file-invoice text-sm"></i> TẢI XUỐNG FILE
                </a>
            `;
        }
    }

    view.innerHTML += `
        <div class="flex flex-col ${isMe ? 'items-end' : 'items-start'} mb-5 animate-in fade-in slide-in-from-bottom-4 duration-500" data-msg-id="${msg.id}">
            <div class="chat-vsd-meta">${msg.sender_name}</div>
            <div class="flex items-end gap-2 ${isMe ? 'flex-row-reverse' : 'flex-row'}">
                <div class="chat-bubble-vsd ${isMe ? '' : 'bg-base-100'}">
                    ${msg.content}
                    ${attachmentHtml}
                </div>
                <div class="chat-time-vsd pb-1">${time}</div>
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
            initialAttachHtml = `<img src="/uploads/tutors/${request.initial_attachment}" class="rounded-xl mt-3 max-w-full cursor-pointer shadow-lg" onclick="window.open(this.src)">`;
        } else {
            initialAttachHtml = `
                <a href="/uploads/tutors/${request.initial_attachment}" target="_blank" class="flex items-center gap-2 mt-3 p-2 bg-black/10 rounded-xl text-[10px] font-bold border border-white/10 hover:bg-black/20 transition-colors">
                    <i class="fa-solid fa-file-invoice text-sm"></i> TẢI FILE GỬI KÈM
                </a>
            `;
        }
    }
    const isInitialMe = (request.student_id == currentUserId);
    view.innerHTML += `
        <div class="flex flex-col ${isInitialMe ? 'items-end' : 'items-start'} mb-5 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div class="chat-vsd-meta">${request.student_name}</div>
            <div class="flex items-end gap-2 ${isInitialMe ? 'flex-row-reverse' : 'flex-row'}">
                <div class="chat-bubble-vsd ${isInitialMe ? '' : 'bg-base-100'}">
                    <div class="initial-msg-vsd font-black text-[11px] uppercase tracking-wider">CÂU HỎI: ${request.title}</div>
                    <div class="font-medium opacity-90">${request.initial_content}</div>
                    ${initialAttachHtml}
                </div>
                <div class="chat-time-vsd pb-1">${initialTime}</div>
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
            <div class="system-card-vsd text-center space-y-4 animate-in zoom-in duration-500" id="rating-card">
                <div class="w-16 h-16 rounded-full bg-warning/20 text-warning flex items-center justify-center mx-auto shadow-inner">
                    <i class="fa-solid fa-star text-2xl"></i>
                </div>
                <div>
                    <h4 class="font-black text-sm uppercase tracking-wider">Đánh giá Gia sư</h4>
                    <p class="text-[11px] font-medium opacity-50 mt-1">Gia sư đã hoàn tất hỗ trợ. Vui lòng đánh giá để kết thúc dự án.</p>
                </div>
                
                <form id="chat-rating-form" class="space-y-4 allow-double-submit">
                    <input type="hidden" name="action" value="rate_tutor">
                    <input type="hidden" name="request_id" value="${activeRequestId}">
                    
                    <div class="rating rating-md rating-half justify-center">
                        <input type="radio" name="rating" class="rating-hidden" />
                        <input type="radio" name="rating" value="0.5" class="mask mask-star-2 mask-half-1 bg-warning" />
                        <input type="radio" name="rating" value="1" class="mask mask-star-2 mask-half-2 bg-warning" />
                        <input type="radio" name="rating" value="1.5" class="mask mask-star-2 mask-half-1 bg-warning" />
                        <input type="radio" name="rating" value="2" class="mask mask-star-2 mask-half-2 bg-warning" />
                        <input type="radio" name="rating" value="2.5" class="mask mask-star-2 mask-half-1 bg-warning" />
                        <input type="radio" name="rating" value="3" class="mask mask-star-2 mask-half-2 bg-warning" />
                        <input type="radio" name="rating" value="3.5" class="mask mask-star-2 mask-half-1 bg-warning" />
                        <input type="radio" name="rating" value="4" class="mask mask-star-2 mask-half-2 bg-warning" />
                        <input type="radio" name="rating" value="4.5" class="mask mask-star-2 mask-half-1 bg-warning" />
                        <input type="radio" name="rating" value="5" class="mask mask-star-2 mask-half-2 bg-warning" checked />
                    </div>
                    
                    <textarea name="review" required class="textarea w-full bg-base-200/50 border-none rounded-2xl p-4 text-xs font-medium focus:ring-2 focus:ring-warning/20 h-24" placeholder="Nhận xét của bạn về gia sư này..."></textarea>
                    
                    <button type="submit" class="btn btn-warning w-full rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg shadow-warning/20">
                        Gửi Đánh Giá & Hoàn Tất
                    </button>
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
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> ĐANG GỬI...';
            
            const formData = new FormData(this);
            try {
                const res = await fetch('/handler/tutor_handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showAlert('Thành công', 'Cảm ơn bạn đã đánh giá gia sư!', 'success');
                    openConversation(activeRequestId);
                } else { 
                    showAlert('Lỗi', data.message, 'error'); 
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Gửi Đánh Giá & Hoàn Tất';
                }
            } catch (err) { console.error(err); }
        });
    }
}

function updateFooterStatus(status) {
    const isClosed = (status === 'completed' || status === 'cancelled' || status === 'rejected' || status === 'answered');
    document.getElementById('chat-send-form').classList.toggle('hidden', isClosed);
    document.getElementById('chat-closed-notice').classList.toggle('hidden', !isClosed);
    if(isClosed && status === 'answered') {
        document.getElementById('chat-closed-notice').innerHTML = '<i class="fa-solid fa-star mr-2"></i> Vui lòng đánh giá để tiếp tục';
    } else {
        document.getElementById('chat-closed-notice').innerHTML = '<i class="fa-solid fa-lock mr-2"></i> Hội thoại đã đóng';
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
    }, 5000); // Polling every 5s to be more efficient
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
function scrollToBottom() { setTimeout(() => { const b = document.getElementById('chat-body'); if(b) b.scrollTo({top: b.scrollHeight, behavior:'smooth'}); }, 100); }

document.getElementById('chat-send-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    const fileInput = document.getElementById('chat-file-input');
    if (!msg && !fileInput.files.length) return;
    
    // Add temporary UI for my message
    const tempMsg = {
        sender_id: currentUserId,
        sender_name: 'Đang gửi...',
        content: msg,
        created_at: new Date().toISOString(),
        attachment: null
    };
    
    const formData = new FormData(this);
    if(fileInput.files.length) {
        formData.append('attachment', fileInput.files[0]);
    }
    
    input.value = '';
    clearFile();
    
    try {
        const res = await fetch('/api/tutor_chat.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            // Force quick poll to get real message
            const pollRes = await fetch(`/api/tutor_chat.php?action=poll_messages&request_id=${activeRequestId}&last_id=${lastMessageId}`);
            const pollData = await pollRes.json();
            if (pollData.success) {
                pollData.messages.forEach(m => {
                    appendMessageUI(m);
                    if(m.id > lastMessageId) lastMessageId = m.id;
                });
                scrollToBottom();
            }
        } else showAlert('Lỗi', data.message, 'error');
    } catch (err) { console.error('Send Error:', err); }
});
</script>
