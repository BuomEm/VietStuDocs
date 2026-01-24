/**
 * View Page Scripts
 * Handles document interactions, downloads, comments, and specific UI logic.
 * Relies on VSD_CONFIG global object defined in the PHP view.
 */

/* =========================================
   Global Variables
   ========================================= */
let currentDownloadXhr = null;
let lastLoaded = 0;
let lastTime = 0;
let currentPurchaseDocId = null;
let currentEmojiTarget = null;
let pdfViewerInitialized = false; // Track initialization

/* =========================================
   Document Interactions (Like, Save, Report)
   ========================================= */ 

/* =========================================
   Document Interactions (Like, Save, Report)
   ========================================= */

function toggleReaction(type) {
    if (!VSD_CONFIG.isLoggedIn) {
        showAlert('Vui lòng đăng nhập để tương tác', 'lock', 'Yêu Cầu Đăng Nhập');
        return;
    }
    
    const params = new URLSearchParams();
    params.append('action', type);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update counts
            const likeBtn = document.querySelector('[onclick="toggleReaction(\'like\')"]');
            const dislikeBtn = document.querySelector('[onclick="toggleReaction(\'dislike\')"]');
            
            if(likeBtn) {
                likeBtn.innerHTML = `<i class="fa-${data.user_reaction === 'like' ? 'solid' : 'regular'} fa-thumbs-up"></i> ${data.likes}`;
                likeBtn.classList.toggle('text-primary', data.user_reaction === 'like');
            }
            if(dislikeBtn) {
                dislikeBtn.innerHTML = `<i class="fa-${data.user_reaction === 'dislike' ? 'solid' : 'regular'} fa-thumbs-down"></i> ${data.dislikes}`;
                dislikeBtn.classList.toggle('text-error', data.user_reaction === 'dislike');
            }
        }
    })
    .catch(err => showAlert('Lỗi kết nối', 'error'));
}

function toggleSave() {
    if (!VSD_CONFIG.isLoggedIn) {
        showAlert('Vui lòng đăng nhập để lưu tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
        return;
    }
    
    const params = new URLSearchParams();
    params.append('action', 'save');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const saveBtn = document.querySelector('[onclick="toggleSave()"]');
            if(saveBtn) {
                saveBtn.innerHTML = `<i class="fa-${data.saved ? 'solid' : 'regular'} fa-bookmark"></i>`;
                saveBtn.classList.toggle('text-primary', data.saved);
            }
        }
    })
    .catch(err => showAlert('Lỗi kết nối', 'error'));
}

function openShareModal() {
    document.getElementById('shareModal').showModal();
}

function closeShareModal() {
    document.getElementById('shareModal').close();
}

function copyLink() {
    const link = document.getElementById('docLink');
    if(link) {
        link.select();
        document.execCommand('copy');
        showAlert('Đã sao chép liên kết vào clipboard!', 'success', 'Thành Công');
    }
}

function openReportModal() {
    if (!VSD_CONFIG.isLoggedIn) {
        showAlert('Vui lòng đăng nhập để báo cáo tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
        return;
    }
    document.getElementById('reportModal').showModal();
}

function closeReportModal() {
    document.getElementById('reportModal').close();
}

function submitDocumentReport(e) {
    if(e) e.preventDefault();
    
    const reason = document.getElementById('reportReason').value;
    const description = document.getElementById('reportDescription').value;
    
    if (!reason) {
        showAlert('Vui lòng chọn lý do báo cáo', 'triangle-exclamation', 'Thiếu Thông Tin');
        return;
    }
    
    // Disable submit button/show loading...
    // Assuming button exists inside form
    
    fetch('/handler/report_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            document_id: VSD_CONFIG.docId,
            reason: reason,
            description: description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeReportModal();
            const form = document.getElementById('reportForm');
            if(form) form.reset();
            showAlert(data.message, 'circle-check', 'Thành Công');
        } else {
            showAlert(data.message, 'triangle-exclamation', 'Lỗi');
        }
    })
    .catch(error => {
        showAlert('Có lỗi xảy ra khi gửi báo cáo. Vui lòng thử lại sau.', 'triangle-exclamation', 'Lỗi');
    });
}


/* =========================================
   Download Logic
   ========================================= */

function downloadDoc() {
    if(!VSD_CONFIG.isLoggedIn) {
        showAlert('Vui lòng đăng nhập để tải xuống tài liệu', 'lock', 'Yêu Cầu Đăng Nhập');
        setTimeout(() => {
            window.location.href = '/login';
        }, 2000);
        return;
    }
    if(!VSD_CONFIG.hasPurchased) {
        openPurchaseModal(VSD_CONFIG.docId, VSD_CONFIG.price);
        return;
    }
    
    // Show download queue widget
    showDownloadQueue();
    
    // Start download
    const downloadUrl = '../handler/download.php?id=' + VSD_CONFIG.docId;
    startSecureDownload(VSD_CONFIG.docId, VSD_CONFIG.originalName);
}

// Start secure download with progress tracking
function startSecureDownload(docId, fileName) {
    showDownloadQueue();
    updateDownloadProgress(0, 0, 0, 0);
    
    lastLoaded = 0;
    lastTime = Date.now();
    
    const xhr = new XMLHttpRequest();
    currentDownloadXhr = xhr;
    
    xhr.open('GET', 'view.php?id=' + docId + '&download=1', true);
    xhr.responseType = 'blob'; 
    
    xhr.onprogress = function(event) {
        if (event.lengthComputable) {
            const loaded = event.loaded;
            const total = event.total;
            const percent = Math.floor((loaded / total) * 100);
            
            const currentTime = Date.now();
            const elapsedSeconds = (currentTime - lastTime) / 1000;
            const speedBps = elapsedSeconds > 0 ? loaded / elapsedSeconds : 0;
            
            updateDownloadProgress(percent, speedBps, loaded, total);
            
            lastLoaded = loaded;
            lastTime = currentTime;
        }
    };
    
    xhr.onabort = function() {
        currentDownloadXhr = null;
        hideDownloadQueue();
    };
    
    xhr.onload = function() {
        currentDownloadXhr = null;
        if (xhr.status === 200) {
            const blob = xhr.response;
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);
            
            setTimeout(() => {
                hideDownloadQueue();
            }, 1000);
        } else {
            hideDownloadQueue();
            showAlert('Lỗi khi tải xuống tài liệu', 'error', 'Lỗi');
        }
    };
    
    xhr.onerror = function() {
        hideDownloadQueue();
        showAlert('Lỗi kết nối khi tải xuống', 'error', 'Lỗi');
    };
    
    xhr.send();
}

function showDownloadQueue() {
    const widget = document.getElementById('downloadQueueWidget');
    if (widget) {
        widget.classList.remove('hidden');
        widget.classList.add('show');
    }
}

function cancelDownload() {
    if (currentDownloadXhr) {
        currentDownloadXhr.abort();
        currentDownloadXhr = null;
        showAlert('Đã hủy tải xuống tài liệu', 'info', 'Đã Hủy');
    }
    hideDownloadQueue();
}

function hideDownloadQueue() {
    const widget = document.getElementById('downloadQueueWidget');
    if (widget) {
        widget.classList.remove('show');
        setTimeout(() => {
            widget.classList.add('hidden');
        }, 300);
    }
}

function updateDownloadProgress(percent, speedBps, loaded, total) {
    const progressBar = document.getElementById('downloadProgressBar');
    const progressPercent = document.getElementById('downloadProgressPercent');
    const downloadSpeed = document.getElementById('downloadSpeed');
    const speedIcon = document.getElementById('downloadSpeedIcon');
    
    if (progressBar) {
        if (total > 0) {
            progressBar.value = percent;
        } else {
            progressBar.removeAttribute('value');
        }
    }
    
    if (progressPercent) {
        progressPercent.textContent = total > 0 ? percent + '%' : 'Đang nhận dữ liệu...';
    }
    
    if (downloadSpeed) {
        const speedKBps = (speedBps / 1024).toFixed(1);
        const speedMBps = (speedBps / (1024 * 1024)).toFixed(2);
        
        if (speedBps >= 1024 * 1024) {
            downloadSpeed.textContent = speedMBps + ' MB/s';
        } else {
            downloadSpeed.textContent = speedKBps + ' KB/s';
        }
        
        const speedBadge = document.getElementById('downloadSpeedBadge');
        if (speedIcon && speedBadge) {
            if (speedBps >= 200 * 1024) {
                speedIcon.className = 'fa-solid fa-bolt';
                speedBadge.className = 'badge badge-success badge-sm gap-1 py-3 px-3 text-white';
            } else if (speedBps >= 50 * 1024) {
                speedIcon.className = 'fa-solid fa-gauge';
                speedBadge.className = 'badge badge-warning badge-sm gap-1 py-3 px-3 text-warning-content';
            } else {
                speedIcon.className = 'fa-solid fa-hourglass-half';
                speedBadge.className = 'badge badge-error badge-sm gap-1 py-3 px-3 text-white';
            }
        }
    }
}

/* =========================================
   UI Interactivity
   ========================================= */
function showGlobalLoader() {
    const loader = document.getElementById('vsdGlobalLoader');
    if(loader) loader.classList.add('active');
}

function hideGlobalLoader() {
    const loader = document.getElementById('vsdGlobalLoader');
    if(loader) loader.classList.remove('active');
}

function goToSaved() {
    window.location.href = 'saved.php';
}

function toggleDocInfo() {
    const section = document.getElementById('documentInfoSection');
    const arrow = document.getElementById('infoToggleArrow');
    
    if (section.classList.contains('show')) {
        section.classList.remove('show');
        if(arrow) arrow.classList.remove('rotated');
        setTimeout(() => {
            section.style.display = 'none';
        }, 400); 
    } else {
        section.style.display = 'grid';
        section.offsetHeight; // force reflow
        section.classList.add('show');
        if(arrow) arrow.classList.add('rotated');
        setTimeout(() => {
            section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }
}

function openPurchaseModal(docId, price) {
    currentPurchaseDocId = docId;
    document.getElementById('purchasePrice').textContent = number_format(price) + ' điểm';
    document.getElementById('purchaseModal').showModal();
}

function closePurchaseModal() {
    document.getElementById('purchaseModal').close();
    currentPurchaseDocId = null;
}

function confirmPurchase(event) {
    if(!currentPurchaseDocId) return;
    
    const confirmBtn = event ? event.target : document.getElementById('confirmPurchaseBtn');
    if(!confirmBtn) return;
    
    const originalText = confirmBtn.textContent;
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Đang xử lý...';
    confirmBtn.style.opacity = '0.6';
    confirmBtn.style.cursor = 'not-allowed';
    
    fetch('/handler/purchase_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'document_id=' + currentPurchaseDocId
    })
    .then(response => {
        if(!response.ok) throw new Error('HTTP error');
        return response.text();
    })
    .then(text => {
        try {
            if(!text || text.trim() === '') throw new Error('Empty response');
            const data = JSON.parse(text);
            if(data.success) {
                closePurchaseModal();
                // Success toast
                const successMsg = document.createElement('div');
                successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; font-weight: 600;';
                successMsg.textContent = '✓ ' + (data.message || 'Mua tài liệu thành công!');
                document.body.appendChild(successMsg);
                setTimeout(() => location.reload(), 1500);
            } else {
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
                confirmBtn.style.opacity = '1';
                confirmBtn.style.cursor = 'pointer';
                showAlert(data.message || 'Lỗi', 'error', 'Lỗi Mua Tài Liệu');
            }
        } catch(e) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = originalText;
            confirmBtn.style.opacity = '1';
            confirmBtn.style.cursor = 'pointer';
            showAlert('Lỗi xử lý phản hồi', 'error');
        }
    })
    .catch(error => {
        confirmBtn.disabled = false;
        confirmBtn.textContent = originalText;
        confirmBtn.style.opacity = '1';
        confirmBtn.style.cursor = 'pointer';
        showAlert('Lỗi kết nối', 'error');
    });
}

function number_format(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/* =========================================
   Comments & Actions
   ========================================= */

function renderCommentText(text) {
    if (!text) return "";
    
    // Escape HTML first
    let escaped = text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    // Replace newlines
    let html = escaped.replace(/\n/g, '<br>');

    // Replace shortcodes
    const map = VSD_CONFIG.emojiMap || {};
    return html.replace(/:([a-z0-9_]+):/g, (match, name) => {
        if (map[name]) {
            return `<img src="${map[name]}" class="inline-block w-[18px] h-[18px] align-text-bottom mx-[2px]" alt="${match}" loading="lazy">`;
        }
        return match;
    });
}


function likeComment(commentId) {
    const btn = document.getElementById(`like-btn-${commentId}`);
    if (!btn) return;
    
    const countSpan = document.getElementById(`like-count-${commentId}`);
    const isLiked = btn.classList.contains('text-error');
    
    // Optimistic Toggle
    if (isLiked) {
        btn.classList.remove('text-error');
        btn.classList.add('text-base-content/40');
        if(countSpan) {
            let currentCount = parseInt(countSpan.innerText) || 0;
            currentCount = Math.max(0, currentCount - 1);
            countSpan.innerText = currentCount > 0 ? currentCount : '';
        }
    } else {
        btn.classList.add('text-error');
        btn.classList.remove('text-base-content/40');
        if(countSpan) {
            let currentCount = parseInt(countSpan.innerText) || 0;
            currentCount++;
            countSpan.innerText = currentCount;
        }
    }

    const formData = new FormData();
    formData.append('action', 'like_comment');
    formData.append('comment_id', commentId);
    
    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            if(countSpan) countSpan.innerText = data.count > 0 ? data.count : '';
        } else {
            console.error('Like failed:', data.message);
            // Revert
            if (isLiked) {
                btn.classList.add('text-error');
                btn.classList.remove('text-base-content/40');
            } else {
                btn.classList.remove('text-error');
                btn.classList.add('text-base-content/40');
            }
            if(countSpan) countSpan.innerText = parseInt(countSpan.innerText) + (isLiked ? 1 : -1);
        }
    })
    .catch(err => console.error(err));
}

function actionComment(action, commentId) {
    if(action === 'delete') {
        showConfirm('Bạn có chắc chắn muốn xóa bình luận này?', 'Xác nhận xóa', () => {
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const el = document.getElementById(`comment-${commentId}`);
                    if(el) el.remove();
                    showNotification('Đã xóa bình luận thành công', 'Thành công');
                } else {
                    showNotification(data.message || 'Không thể xóa bình luận', 'Lỗi');
                }
            });
        });
    } else if (action === 'pin' || action === 'unpin') {
        const formData = new FormData();
        formData.append('action', action === 'pin' ? 'pin_comment' : 'unpin_comment');
        formData.append('comment_id', commentId);
        fetch('', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if(data.success) location.reload();
            else alert(data.message);
        });
    }
}

function startEdit(commentId) {
    const contentP = document.getElementById(`comment-content-${commentId}`);
    const editForm = document.getElementById(`edit-form-${commentId}`);
    const editInput = document.getElementById(`edit-input-${commentId}`);
    
    let html = contentP.innerHTML;
    let text = html.replace(/<br\s*\/?>/gi, "\n");
    const tmp = document.createElement("textarea");
    tmp.innerHTML = text;
    editInput.value = tmp.value;

    contentP.classList.add('hidden');
    editForm.classList.remove('hidden');
}

function cancelEdit(commentId) {
    document.getElementById(`comment-content-${commentId}`).classList.remove('hidden');
    document.getElementById(`edit-form-${commentId}`).classList.add('hidden');
}

function saveEdit(commentId) {
    const content = document.getElementById(`edit-input-${commentId}`).value;
    if(!content.trim()) return;
    
    const formData = new FormData();
    formData.append('action', 'edit_comment');
    formData.append('comment_id', commentId);
    formData.append('content', content);
    
    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            const contentP = document.getElementById(`comment-content-${commentId}`);
            contentP.innerHTML = renderCommentText(data.content);
            cancelEdit(commentId);
        } else alert(data.message);
    });
}

function showReportCommentModal(commentId) {
    document.getElementById('report_comment_id').value = commentId;
    document.getElementById('report_comment_modal').showModal();
}

function submitCommentReport() {
    const commentId = document.getElementById('report_comment_id').value;
    const reason = document.getElementById('report_reason').value; // Note: Ensure IDs match HTML
    
    const formData = new FormData();
    formData.append('action', 'report_comment');
    formData.append('comment_id', commentId);
    formData.append('reason', reason);
    
    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        document.getElementById('report_comment_modal').close();
        showNotification('Báo cáo đã được gửi. Cảm ơn bạn!', 'Thành công');
    });
}

function toggleReply(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) form.classList.toggle('hidden');
}

function toggleHiddenReplies(commentId) {
    const hiddenDiv = document.getElementById(`hidden-replies-${commentId}`);
    const btn = document.getElementById(`btn-show-more-${commentId}`);
    
    if(hiddenDiv.classList.contains('hidden')) {
        hiddenDiv.classList.remove('hidden');
        hiddenDiv.classList.add('animate-fade-in');
        btn.innerText = 'Ẩn bớt';
    } else {
        hiddenDiv.classList.add('hidden');
        btn.innerText = 'Xem thêm câu trả lời...';
    }
}

function handlePostComment(parentId = null) {
    console.log('handlePostComment triggered', {parentId});
    let content = '';
    let btn = null;
    let textarea = null;
    
    if (parentId) {
        textarea = document.getElementById(`reply-content-${parentId}`);
        content = textarea ? textarea.value : '';
        // Find the button in the specific reply form context
        const replyForm = document.getElementById(`reply-form-${parentId}`);
        if(replyForm) {
            btn = replyForm.querySelector('button.btn-primary');
        }
    } else {
        textarea = document.getElementById('commentContent');
        content = textarea ? textarea.value : '';
        btn = document.getElementById('postCommentBtn');
    }

    if(!content || !content.trim()) return;
    if(!btn) return;

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';

    const formData = new FormData();
    formData.append('action', 'comment');
    formData.append('content', content);
    if (parentId) formData.append('parent_id', parentId);

    fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success && data.html) {
            if(textarea) {
                textarea.value = '';
                textarea.style.height = '44px'; // Reset height
            }
            
            if (parentId) {
                // Determine insertion (replies list)
                const replyContainer = document.getElementById(`replies-${parentId}`);
                if (replyContainer) {
                    replyContainer.insertAdjacentHTML('beforeend', data.html); // Append reply
                    // Also close the reply form?
                    toggleReply(parentId);
                }
            } else {
                // Parent comment - append to top of list
                // Try specific ID first (added in view.php)
                let list = document.getElementById('commentsList');
                if (!list) list = document.querySelector('.space-y-6'); // Fallback

                if (list) {
                     // Check if empty placeholder exists
                     const noComments = document.getElementById('noCommentsMsg');
                     if(noComments) noComments.remove();

                     // Just prepend
                     list.insertAdjacentHTML('afterbegin', data.html);
                     
                     // Scroll to it
                     const newEl = list.firstElementChild;
                     if(newEl) newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    console.error('Comments container not found');
                    showNotification('Không tìm thấy vùng chứa bình luận để cập nhật.', 'Lỗi');
                    return;
                }
            }
            
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        } else {
             showNotification(data.message || 'Không thể gửi bình luận', 'Lỗi');
             btn.disabled = false;
             btn.innerHTML = originalHtml;
        }
    })
    .catch(err => {
        console.error(err);
        showNotification('Có lỗi xảy ra', 'Lỗi');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

/* =========================================
   Modals & Emoji
   ========================================= */

function toggleEmojiPicker(targetId) {
    const picker = document.getElementById('emoji-picker');
    if(!picker) return;

    let btn = null;
    if (typeof event !== 'undefined' && event.target) {
         btn = event.target.closest('button');
    }
    // Fallback if event is not captured or btn is null (though should be called via onclick)
    if(!btn) return; 

    if (currentEmojiTarget === targetId && !picker.classList.contains('hidden')) {
        picker.classList.add('hidden');
        return;
    }
    
    currentEmojiTarget = targetId;
    
    // Show first to get dimensions
    picker.classList.remove('hidden');
    
    // Inject Custom Emojis if needed (Lazy check)
    // ... (Custom emoji logic remains similar or assumed handled by initial injection if static)
    // Let's re-inject if needed, or rely on static. 
    // Optimization: Only inject once or check if empty.
    const customSection = document.getElementById('custom-emoji-section');
    if (customSection && customSection.innerHTML === '' && VSD_CONFIG.emojiMap) {
        let customHtml = '';
        for (const [name, path] of Object.entries(VSD_CONFIG.emojiMap)) {
            customHtml += `<button onclick="insertEmoji(':${name}:')" class="hover:bg-base-200 p-1 rounded-xl transition-colors" title=":${name}:">
                <img src="${path}" class="w-8 h-8 object-contain">
            </button>`;
        }
        customSection.innerHTML = customHtml;
    }

    // Positioning Logic
    const rect = btn.getBoundingClientRect();
    const pickerRect = picker.getBoundingClientRect();
    const padding = 10;
    
    // Default: Above the button, aligned right
    let top = rect.top - pickerRect.height - padding;
    let left = rect.right - pickerRect.width;

    // Check if it goes off top screen -> flip to below
    if (top < padding) {
        top = rect.bottom + padding;
        picker.style.transformOrigin = 'top right';
    } else {
        picker.style.transformOrigin = 'bottom right';
    }
    
    // Check if it goes off left screen -> align left
    if (left < padding) {
        left = rect.left;
        picker.style.transformOrigin = picker.style.transformOrigin.replace('right', 'left');
    }

    picker.style.top = `${top}px`;
    picker.style.left = `${left}px`;
    
    // Close on click outside
    const closeFn = (e) => {
        if (!picker.contains(e.target) && !btn.contains(e.target)) {
            picker.classList.add('hidden');
            document.removeEventListener('click', closeFn);
        }
    };
    // Timeout to avoid immediate close from the current click
    setTimeout(() => document.addEventListener('click', closeFn), 0);
}

function insertEmoji(emoji) {
    if(!currentEmojiTarget) return;
    const input = document.getElementById(currentEmojiTarget);
    if(input) {
        input.value += emoji;
        input.focus();
    }
}

// Global Alert/Confirm Wrappers (using helper.php modal structure)
function showConfirm(message, title = 'Xác nhận', onConfirm) {
    if(window.vsdConfirm) {
        window.vsdConfirm({
            title: title,
            message: message,
            onConfirm: onConfirm
        });
    } else {
        // Fallback or deprecated modal usage
         document.getElementById('confirm_title').innerText = title;
         document.getElementById('confirm_message').innerText = message;
         const modal = document.getElementById('confirm_modal');
         const btn = document.getElementById('confirm_btn');
         btn.onclick = () => { modal.close(); if(onConfirm) onConfirm(); };
         modal.showModal();
    }
}

function showNotification(message, title = 'Thông báo') {
    if(window.showAlert) {
        window.showAlert(message, 'info'); // Simplified mapping
    } else {
        document.getElementById('notification_title').innerText = title;
        document.getElementById('notification_message').innerText = message;
        document.getElementById('notification_modal').showModal();
    }
}

// Basic Alert (from view.php original)
function showAlert(message, iconType = 'info', title = 'Thông Báo') {
     // Use the global window.showAlert if simpler, or custom logic
     // Original view.php had a specific alertModal.
     const modal = document.getElementById('alertModal');
     if(modal) {
         document.getElementById('alertMessage').textContent = message;
         const icons = {
            'info': '<i class="fa-solid fa-circle-info text-6xl text-info"></i>',
            'warning': '<i class="fa-solid fa-triangle-exclamation text-6xl text-warning"></i>',
            'success': '<i class="fa-solid fa-circle-check text-6xl text-success"></i>',
            'error': '<i class="fa-solid fa-circle-xmark text-6xl text-error"></i>',
            'lock': '<i class="fa-solid fa-lock text-6xl text-warning"></i>'
         };
         const iconKey = iconType; 
         document.getElementById('alertIcon').innerHTML = icons[iconKey] || icons['info'];
         document.getElementById('alertTitle').textContent = title;
         modal.showModal();
     } else {
         alert(message);
     }
}

function closeAlertModal() {
    const modal = document.getElementById('alertModal');
    if(modal) modal.close();
}

/* =========================================
   Initialization
   ========================================= */

document.addEventListener('DOMContentLoaded', () => {
    // Inject Modals & Emoji Picker HTML
    // Inject Modals & Emoji Picker HTML
    const emojiHtml = `
        <div id="emoji-picker" class="fixed z-[9999] bg-base-100/90 backdrop-blur-xl shadow-2xl rounded-2xl border border-white/20 w-[320px] p-4 hidden flex flex-col gap-3 max-h-[400px] overflow-hidden transition-all duration-200 origin-bottom-right">
            <div class="flex items-center justify-between pb-2 border-b border-base-content/5">
                <span class="text-xs font-black uppercase tracking-widest opacity-50">Emojis</span>
                <button onclick="document.getElementById('emoji-picker').classList.add('hidden')" class="btn btn-ghost btn-xs btn-circle"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="custom-emoji-section" class="flex flex-wrap gap-2 max-h-[100px] overflow-y-auto empty:hidden"></div>
            <div class="emoji-grid grid grid-cols-8 gap-1 overflow-y-auto pr-1 customize-scrollbar" style="max-height: 200px;">
                ${['😀','😂','🥰','😍','😭','😡','👍','👎','❤️','🔥','🎉','🤝','🙏','👀','🤔','😅','😱','👋','💪','✨','💩','🤡','👻','💀','👽','🤖','💯','💢','💥','💫','💦','💤','👋','ok','👇','👈','👉','👆'].map(e => `<button onclick="insertEmoji('${e}')" class="btn btn-ghost btn-sm p-0 h-9 w-9 text-xl hover:bg-base-content/10 rounded-xl transition-all hover:scale-110">${e}</button>`).join('')}
            </div>
        </div>
        
        <!-- Report Modal -->
        <dialog id="report_comment_modal" class="modal">
          <div class="modal-box">
            <h3 class="font-bold text-lg">Báo cáo bình luận</h3>
            <input type="hidden" id="report_comment_id">
            <div class="py-4">
                <select id="report_reason" class="select select-bordered w-full">
                    <option value="Spam">Spam / Quảng cáo</option>
                    <option value="Ngôn từ đả kích">Ngôn từ đả kích / Xúc phạm</option>
                    <option value="Nội dung sai lệch">Nội dung sai lệch</option>
                    <option value="Khác">Khác</option>
                </select>
            </div>
            <div class="modal-action">
              <form method="dialog">
                <button class="btn btn-ghost">Hủy</button>
                <button class="btn btn-error" onclick="submitCommentReport()">Gửi báo cáo</button>
              </form>
            </div>
          </div>
        </dialog>
        
        <!-- Confirm Modal (Deprecated fallback) -->
        <dialog id="confirm_modal" class="modal">
          <div class="modal-box">
            <h3 class="font-bold text-lg" id="confirm_title">Xác nhận</h3>
            <p class="py-4" id="confirm_message"></p>
            <div class="modal-action">
              <button class="btn btn-ghost" onclick="document.getElementById('confirm_modal').close()">Hủy</button>
              <button class="btn btn-error" id="confirm_btn">Xác nhận</button>
            </div>
          </div>
        </dialog>
        
        <!-- Notification Modal (Deprecated fallback) -->
        <dialog id="notification_modal" class="modal">
          <div class="modal-box">
            <h3 class="font-bold text-lg" id="notification_title">Thông báo</h3>
            <p class="py-4" id="notification_message"></p>
            <div class="modal-action">
              <button class="btn btn-primary" onclick="document.getElementById('notification_modal').close()">Đóng</button>
            </div>
          </div>
        </dialog>
    `;
    
    document.body.insertAdjacentHTML('beforeend', emojiHtml);

    // Apply Content Protection if needed
    if(typeof VSD_CONFIG !== 'undefined' && !VSD_CONFIG.hasPurchased) {
        // Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable copy
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            e.clipboardData.setData('text/plain', '');
            showAlert('Sao chép nội dung bị cấm. Vui lòng mua tài liệu để sử dụng.', 'warning', 'Cảnh Báo');
            return false;
        });
        
        // Disable cut
        document.addEventListener('cut', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable select
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable drag
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable print screen (F12, Print Screen key)
        document.addEventListener('keydown', function(e) {
            // Disable F12 (Developer Tools) and other shortcuts
            if(e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I') || 
               (e.ctrlKey && e.shiftKey && e.key === 'C') || 
               (e.ctrlKey && e.key === 'U') || 
               (e.ctrlKey && e.key === 'S')) {
                e.preventDefault();
                return false;
            }
            // Disable Print Screen
            if(e.key === 'PrintScreen') {
                e.preventDefault();
                navigator.clipboard.writeText('');
                showAlert('Chụp màn hình bị cấm. Vui lòng mua tài liệu để sử dụng.', 'warning', 'Cảnh Báo');
                return false;
            }
        });
        
        // Disable screenshot on mobile (iOS/Android)
        document.addEventListener('touchstart', function(e) {
            if(e.touches.length > 1) {
                e.preventDefault();
            }
        }, { passive: false });
        
        // Blur on tab switch (prevents screenshot when switching tabs)
        document.addEventListener('visibilitychange', function() {
            if(document.hidden) {
                document.body.style.filter = 'blur(10px)';
            } else {
                document.body.style.filter = 'none';
            }
        });
        
        // Console warning
        console.log('%c⚠️ CẢNH BÁO!', 'color: red; font-size: 50px; font-weight: bold;');
        console.log('%cSao chép hoặc chỉnh sửa mã này là bất hợp pháp!', 'color: red; font-size: 20px;');
    }

    // Initialize Document Viewers
    if (typeof VSD_CONFIG !== 'undefined') {
        const fileExt = VSD_CONFIG.fileExt;
        
        // 1. PDF Viewer Initialization
        if (VSD_CONFIG.pdfPath && (fileExt === 'pdf' || fileExt === 'docx' || fileExt === 'doc')) {
            // Global PDF.js configurations
            if (typeof pdfjsLib !== 'undefined') {
                pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
            }
            
            if (!pdfViewerInitialized && typeof VsdPdfViewer !== 'undefined') {
                pdfViewerInitialized = true;
                new VsdPdfViewer('pdfViewer', VSD_CONFIG.pdfPath, {
                    maxPreviewPages: VSD_CONFIG.limitPreviewPages,
                    hasPurchased: VSD_CONFIG.hasPurchased
                });
            }
        } 
        // 2. DOCX Fallback Viewer (if no PDF preview)
        else if ((fileExt === 'docx' || fileExt === 'doc') && typeof docx !== 'undefined') {
            const docxContainer = document.getElementById("docxViewer");
            if (docxContainer) {
                showGlobalLoader();
                const docxUrl = '../handler/file.php?doc_id=' + VSD_CONFIG.docId;
                fetch(docxUrl)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.blob();
                    })
                    .then(blob => {
                        docx.renderAsync(blob, docxContainer, null, {
                            className: "docx",
                            inWrapper: false,
                            ignoreWidth: false,
                            ignoreHeight: false,
                            ignoreFonts: false,
                            breakPageHasIndent: false,
                            debug: false
                        }).then(() => {
                            hideGlobalLoader();
                        });
                    })
                    .catch(error => {
                        hideGlobalLoader();
                        console.error('DOCX Render Error:', error);
                        docxContainer.innerHTML = `<div class="p-10 text-center text-error"><i class="fa-solid fa-triangle-exclamation text-4xl mb-2"></i><p>Không thể hiển thị tài liệu: ${error.message}</p></div>`;
                    });
            }
        }
    }
});

function updateCommentUI(textarea) {
    if(!textarea) return;
    
    // Auto resize
    textarea.style.height = '44px'; // Reset to min height
    textarea.style.height = (textarea.scrollHeight) + 'px';
}
