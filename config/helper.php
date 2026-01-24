<?php
/**
 * Helper functions for UI components like Modals
 */

/**
 * Renders a standard DaisyUI Confirmation Modal
 * @param string $id Modal ID
 * @param string $title Modal Title
 * @param string $message Modal Message
 * @param string $confirmButtonText Text for the confirm button
 * @param string $confirmAction JS code to execute on confirm
 * @param string $type primary, success, warning, error
 */
function renderConfirmModal($id, $title, $message, $confirmButtonText = 'Đồng ý', $confirmAction = '', $type = 'primary') {
    $btnClass = "btn-$type";
    $icon = 'fa-circle-info';
    if ($type === 'error') $icon = 'fa-triangle-exclamation';
    if ($type === 'success') $icon = 'fa-circle-check';
    if ($type === 'warning') $icon = 'fa-circle-exclamation';
    
    echo "
    <dialog id=\"$id\" class=\"modal modal-bottom sm:modal-middle\">
      <div class=\"modal-box border border-base-300\">
        <h3 class=\"font-bold text-lg flex items-center gap-2\">
            <i class=\"fa-solid $icon text-$type\"></i>
            $title
        </h3>
        <p class=\"py-4 text-base-content/80\">$message</p>
        <div class=\"modal-action gap-2\">
          <button type=\"button\" class=\"btn btn-ghost\" onclick=\"this.closest('dialog').close()\">Hủy bỏ</button>
          <button onclick=\"$confirmAction\" class=\"btn $btnClass\">$confirmButtonText</button>
        </div>
      </div>
      <form method=\"dialog\" class=\"modal-backdrop\">
        <button>close</button>
      </form>
    </dialog>
    ";
}

/**
 * Renders a global Confirmation Modal at the end of the body
 * Used by showConfirm() JS function
 */
function renderGlobalModal() {
    echo '
    <!-- Global Confirm Modal -->
    <dialog id="vsd_global_confirm" class="modal modal-bottom sm:modal-middle">
      <div class="modal-box border border-base-300 shadow-2xl rounded-[2rem]">
        <h3 id="vsd_confirm_title" class="font-black text-xl flex items-center gap-3 text-primary uppercase tracking-tighter">
            <i class="fa-solid fa-circle-question"></i>
            Xác nhận
        </h3>
        <p id="vsd_confirm_message" class="py-6 text-base-content/80 font-medium leading-relaxed">Bạn có chắc chắn muốn thực hiện hành động này?</p>
        <div class="modal-action gap-3">
          <button type="button" class="btn btn-ghost rounded-xl font-bold" onclick="this.closest(\'dialog\').close()">Hủy bỏ</button>
          <button id="vsd_confirm_btn" class="btn btn-primary rounded-xl px-8 font-black uppercase tracking-widest">Xác nhận</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop bg-base-neutral/20 backdrop-blur-[2px]">
        <button>close</button>
      </form>
    </dialog>

    <!-- Global Prompt Modal -->
    <dialog id="vsd_global_prompt" class="modal modal-bottom sm:modal-middle">
      <div class="modal-box border border-base-300 shadow-2xl rounded-[2rem]">
        <h3 id="vsd_prompt_title" class="font-black text-xl flex items-center gap-3 text-primary uppercase tracking-tighter">
            <i class="fa-solid fa-pen-to-square"></i>
            Nhập thông tin
        </h3>
        <p id="vsd_prompt_message" class="py-2 text-sm opacity-70 font-medium"></p>
        <div id="vsd_prompt_inputs" class="space-y-4 py-2">
            <!-- Dynamic inputs will be injected here -->
        </div>
        <div class="modal-action gap-3 mt-8">
          <button type="button" class="btn btn-ghost rounded-xl font-bold" onclick="this.closest(\'dialog\').close()">Hủy bỏ</button>
          <button id="vsd_prompt_btn" class="btn btn-primary rounded-xl px-10 font-black uppercase tracking-widest">Xác nhận</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop bg-base-neutral/20 backdrop-blur-[2px]">
        <button>close</button>
      </form>
    </dialog>

    <!-- Global Alert (Toast) Container -->
    <div id="vsd_toast_container" popover="manual" class="fixed top-20 right-6 z-[999999] flex flex-col gap-3 pointer-events-none bg-transparent border-none p-0 m-0 overflow-visible"></div>

    <style>
    #vsd_toast_container:popover-open {
        display: flex;
        background: transparent;
        border: none;
        inset: 5rem 1.5rem auto auto; /* top-20 right-6 */
    }
    .vsd-toast {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 16px 24px;
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 320px;
        max-width: 450px;
        transform: translateX(120%);
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        pointer-events: auto;
    }
    [data-theme="dark"] .vsd-toast,
    [data-theme="dim"] .vsd-toast {
        background: rgba(15, 23, 42, 0.85);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .vsd-toast.show { transform: translateX(0); }
    .vsd-toast-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .vsd-toast-success .vsd-toast-icon { background: #dcfce7; color: #16a34a; }
    .vsd-toast-error .vsd-toast-icon { background: #fee2e2; color: #dc2626; }
    .vsd-toast-info .vsd-toast-icon { background: #e0f2fe; color: #0284c7; }
    .vsd-toast-warning .vsd-toast-icon { background: #fef3c7; color: #d97706; }
    </style>

    <script>
    window.showAlert = function(message, type = "success") {
        const container = document.getElementById("vsd_toast_container");
        if (!container) return;

        // Ensure container is in the Top Layer using Popover API (if supported)
        if (container.showPopover && !container.matches(\':popover-open\')) {
            container.showPopover();
        }

        const toast = document.createElement("div");
        toast.className = `vsd-toast vsd-toast-${type}`;
        
        const icons = {
            success: "fa-circle-check",
            error: "fa-circle-xmark",
            info: "fa-circle-info",
            warning: "fa-triangle-exclamation"
        };
        const icon = icons[type] || icons.info;
        
        toast.innerHTML = `
            <div class="vsd-toast-icon">
                <i class="fa-solid ${icon}"></i>
            </div>
            <div class="flex-1">
                <div class="text-[10px] font-black uppercase opacity-40 leading-none mb-1">${type === \'error\' ? \'Lỗi\' : (type === \'success\' ? \'Thành công\' : \'Thông báo\')}</div>
                <div class="text-sm font-bold text-base-content/80 leading-snug">${message}</div>
            </div>
        `;

        container.appendChild(toast);
        setTimeout(() => toast.classList.add("show"), 10);

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => {
                toast.remove();
                // Close container if no toasts left
                if (container.children.length === 0 && container.hidePopover) {
                    container.hidePopover();
                }
            }, 500);
        }, 4000);
    };

    window.vsdConfirm = function(options) {
        const modal = document.getElementById("vsd_global_confirm");
        const titleEl = document.getElementById("vsd_confirm_title");
        const msgEl = document.getElementById("vsd_confirm_message");
        const btnEl = document.getElementById("vsd_confirm_btn");

        titleEl.innerHTML = `<i class="fa-solid ${options.icon || "fa-circle-question"}"></i> ${options.title || "Xác nhận"}`;
        msgEl.innerHTML = options.message || "Bạn có chắc chắn?";
        btnEl.innerText = options.confirmText || "Xác nhận";
        btnEl.className = "btn rounded-xl px-8 font-black uppercase tracking-widest " + (options.type ? "btn-" + options.type : "btn-primary");

        const newBtn = btnEl.cloneNode(true);
        btnEl.parentNode.replaceChild(newBtn, btnEl);

        newBtn.addEventListener("click", function() {
            if (typeof options.onConfirm === "function") options.onConfirm();
            modal.close();
        });
        modal.showModal();
    };

    window.vsdPrompt = function(options) {
        const modal = document.getElementById("vsd_global_prompt");
        // ... (rest of vsdPrompt logic same as before, but with updated UI classes)
        const titleEl = document.getElementById("vsd_prompt_title");
        const msgEl = document.getElementById("vsd_prompt_message");
        const inputsEl = document.getElementById("vsd_prompt_inputs");
        const btnEl = document.getElementById("vsd_prompt_btn");

        titleEl.innerHTML = `<i class="fa-solid ${options.icon || "fa-pen-to-square"}"></i> ${options.title || "Nhập thông tin"}`;
        msgEl.innerHTML = options.message || "";
        btnEl.innerText = options.confirmText || "Xác nhận";
        
        inputsEl.innerHTML = "";
        options.inputs.forEach(input => {
            const container = document.createElement("div");
            container.className = "form-control w-full";
            let inputHtml = `<label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">${input.label}</span></label>`;
            if (input.type === "select") {
                inputHtml += `<select name="${input.name}" class="select select-bordered w-full bg-base-200 rounded-xl font-bold">
                    ${input.options.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join("")}
                </select>`;
            } else if (input.type === "textarea") {
                inputHtml += `<textarea name="${input.name}" class="textarea textarea-bordered h-24 bg-base-200 rounded-xl font-medium" placeholder="${input.placeholder || ""}"></textarea>`;
            } else {
                inputHtml += `<input type="${input.type || "text"}" name="${input.name}" class="input input-bordered w-full bg-base-200 rounded-xl font-bold" placeholder="${input.placeholder || ""}" />`;
            }
            container.innerHTML = inputHtml;
            inputsEl.appendChild(container);
        });

        const newBtn = btnEl.cloneNode(true);
        btnEl.parentNode.replaceChild(newBtn, btnEl);
        newBtn.addEventListener("click", function() {
            const data = {};
            options.inputs.forEach(input => {
                const el = inputsEl.querySelector(`[name="${input.name}"]`);
                if (el) data[input.name] = el.value;
            });
            if (typeof options.onConfirm === "function") options.onConfirm(data);
            modal.close();
        });
        modal.showModal();
    };
    </script>
    ';
}

/**
 * Handles all background actions for the view.php page (likes, saving, comments)
 * Replaces a large block of logic that was previously in the view.php file.
 * 
 * @param DB $VSD Database object
 * @param int $doc_id Document ID
 * @param int|null $user_id Current User ID
 * @param array $doc Document Row
 */
function handleViewDocumentActions($VSD, $doc_id, $user_id, $doc) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
        return;
    }

    $action = $_POST['action'];
    $is_logged_in = (bool)$user_id;

    // Actions that require login
    $restricted_actions = ['like', 'dislike', 'save', 'comment', 'like_comment', 'delete_comment', 'pin_comment', 'unpin_comment', 'edit_comment', 'report_comment'];
    if (in_array($action, $restricted_actions)) {
        if (!$is_logged_in) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện']);
            exit;
        }
    }

    switch ($action) {
        case 'like':
        case 'dislike':
            // Check existing reaction
            $check = $VSD->get_row("SELECT * FROM document_interactions WHERE document_id = $doc_id AND user_id = $user_id AND type IN ('like', 'dislike')");
            if ($check) {
                if ($check['type'] === $action) {
                    $VSD->query("DELETE FROM document_interactions WHERE id = " . $check['id']);
                    $user_reaction = null;
                } else {
                    $VSD->query("UPDATE document_interactions SET type = '$action' WHERE id = " . $check['id']);
                    $user_reaction = $action;
                }
            } else {
                $VSD->insert('document_interactions', [
                    'document_id' => $doc_id,
                    'user_id' => $user_id,
                    'type' => $action
                ]);
                $user_reaction = $action;
            }
            
            $likes = intval($VSD->num_rows("SELECT id FROM document_interactions WHERE document_id = $doc_id AND type = 'like'"));
            $dislikes = intval($VSD->num_rows("SELECT id FROM document_interactions WHERE document_id = $doc_id AND type = 'dislike'"));
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'likes' => $likes, 
                'dislikes' => $dislikes, 
                'user_reaction' => $user_reaction
            ]);
            exit;

        case 'save':
            $check = $VSD->get_row("SELECT * FROM document_interactions WHERE document_id = $doc_id AND user_id = $user_id AND type = 'save'");
            if ($check) {
                $VSD->query("DELETE FROM document_interactions WHERE id = " . $check['id']);
                $saved = false;
            } else {
                $VSD->insert('document_interactions', [
                    'document_id' => $doc_id,
                    'user_id' => $user_id,
                    'type' => 'save'
                ]);
                $saved = true;
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'saved' => $saved]);
            exit;

        case 'comment':
            $content = trim($_POST['content'] ?? '');
            $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            
            if (empty($content)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Nội dung bình luận không được để trống']);
                exit;
            }

            // Anti-spam emojis
            if (substr_count($content, ':') > 40) { // Approx 20 emojis
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Bình luận chứa quá nhiều emoji']);
                exit;
            }
            
            $data = [
                'document_id' => $doc_id,
                'user_id' => $user_id,
                'content' => $content
            ];
            if ($parent_id) $data['parent_id'] = $parent_id;
            
            $VSD->insert('document_comments', $data);
            $new_comment_id = $VSD->insert_id();

            // Fetch newly created comment to render HTML
            $new_comment = $VSD->get_row("
                SELECT c.*, u.username, u.avatar 
                FROM document_comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.id = $new_comment_id
            ");

            if ($new_comment) {
                // Initialize avatar URL
                $avatar_url = !empty($new_comment['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $new_comment['avatar']) 
                    ? '../uploads/avatars/' . $new_comment['avatar'] 
                    : null;
                $user_initial = strtoupper(substr($new_comment['username'], 0, 1));
                $is_author = ($new_comment['user_id'] == $doc['user_id']);
                $created_at = date('H:i d/m/Y', strtotime($new_comment['created_at']));
                $rendered_content = render_comment_content($new_comment['content']); // Use helper function
                
                ob_start();
                if ($parent_id) {
                    // Render Reply HTML
                    ?>
                    <div class="flex gap-4 animate-fade-in" id="comment-<?= $new_comment['id'] ?>">
                        <div class="shrink-0">
                            <div class="w-8 h-8 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                                <?php if($avatar_url): ?>
                                    <img src="<?= $avatar_url ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="bg-primary/10 w-full h-full flex items-center justify-center text-primary font-bold text-xs">
                                        <?= $user_initial ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm"><?= htmlspecialchars($new_comment['username']) ?></span>
                                <?php if($is_author): ?>
                                    <span class="badge badge-xs badge-primary font-bold">Tác giả</span>
                                <?php endif; ?>
                                <span class="text-xs text-base-content/50"><?= $created_at ?></span>
                            </div>
                            
                            <div class="group relative">
                                <p class="text-sm text-base-content/80 leading-relaxed" id="comment-content-<?= $new_comment['id'] ?>"><?= $rendered_content ?></p>
                                <!-- Edit Form -->
                                <div id="edit-form-<?= $new_comment['id'] ?>" class="hidden mt-2">
                                    <textarea id="edit-input-<?= $new_comment['id'] ?>" class="textarea textarea-bordered w-full text-sm min-h-[60px]"></textarea>
                                    <div class="flex justify-end gap-2 mt-2">
                                        <button onclick="cancelEdit('<?= $new_comment['id'] ?>')" class="btn btn-xs btn-ghost">Hủy</button>
                                        <button onclick="saveEdit('<?= $new_comment['id'] ?>')" class="btn btn-xs btn-primary">Lưu</button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-4 pt-1 items-center">
                                <button id="like-btn-<?= $new_comment['id'] ?>" onclick="likeComment('<?= $new_comment['id'] ?>')" class="flex items-center gap-1 text-xs font-bold transition-colors text-base-content/40 hover:text-error">
                                    <i class="fa-solid fa-heart"></i>
                                    <span id="like-count-<?= $new_comment['id'] ?>"></span>
                                </button>
                                <div class="dropdown dropdown-end">
                                    <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle text-base-content/30"><i class="fa-solid fa-ellipsis"></i></div>
                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 text-xs">
                                        <li><a onclick="startEdit('<?= $new_comment['id'] ?>')"><i class="fa-solid fa-pen"></i> Chỉnh sửa</a></li>
                                        <li><a onclick="actionComment('delete', <?= $new_comment['id'] ?>)" class="text-error"><i class="fa-solid fa-trash"></i> Xóa</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                } else {
                    // Render Parent Comment HTML
                    ?>
                    <div class="flex gap-4 animate-fade-in" id="comment-<?= $new_comment['id'] ?>">
                        <div class="shrink-0">
                            <div class="w-10 h-10 rounded-full bg-base-300 flex items-center justify-center overflow-hidden ring-2 ring-base-content/5">
                                <?php if($avatar_url): ?>
                                    <img src="<?= $avatar_url ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="bg-primary/10 w-full h-full flex items-center justify-center text-primary font-bold">
                                        <?= $user_initial ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <div class="bg-base-200/50 rounded-2xl p-4 relative group hover:bg-base-200 transition-colors">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-bold text-sm font-outfit"><?= htmlspecialchars($new_comment['username']) ?></span>
                                    <?php if($is_author): ?>
                                        <span class="badge badge-xs badge-primary font-bold">Tác giả</span>
                                    <?php endif; ?>
                                    <span class="text-xs text-base-content/50"><?= $created_at ?></span>
                                </div>
                                <div class="group relative">
                                    <p class="text-sm text-base-content/80 leading-relaxed whitespace-pre-line" id="comment-content-<?= $new_comment['id'] ?>"><?= $rendered_content ?></p>
                                    <!-- Edit Form -->
                                    <div id="edit-form-<?= $new_comment['id'] ?>" class="hidden mt-2">
                                        <textarea id="edit-input-<?= $new_comment['id'] ?>" class="textarea textarea-bordered w-full text-sm min-h-[60px]"></textarea>
                                        <div class="flex justify-end gap-2 mt-2">
                                            <button onclick="cancelEdit('<?= $new_comment['id'] ?>')" class="btn btn-xs btn-ghost">Hủy</button>
                                            <button onclick="saveEdit('<?= $new_comment['id'] ?>')" class="btn btn-xs btn-primary">Lưu</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4 mt-3 border-t border-base-content/5 pt-2">
                                    <button id="like-btn-<?= $new_comment['id'] ?>" onclick="likeComment('<?= $new_comment['id'] ?>')" class="flex items-center gap-1 text-xs font-bold transition-colors text-base-content/40 hover:text-error">
                                        <i class="fa-solid fa-heart"></i>
                                        <span id="like-count-<?= $new_comment['id'] ?>"></span>
                                    </button>
                                    <button class="text-xs font-bold text-base-content/40 hover:text-primary transition-colors" onclick="toggleReply('<?= $new_comment['id'] ?>')">Trả lời</button>
                                    
                                    <div class="dropdown dropdown-end ml-auto">
                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle text-base-content/30"><i class="fa-solid fa-ellipsis"></i></div>
                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 text-xs">
                                            <li><a onclick="actionComment('pin', <?= $new_comment['id'] ?>)"><i class="fa-solid fa-thumbtack"></i> Ghim bình luận</a></li>
                                            <li><a onclick="startEdit('<?= $new_comment['id'] ?>')"><i class="fa-solid fa-pen"></i> Chỉnh sửa</a></li>
                                            <li><a onclick="actionComment('delete', <?= $new_comment['id'] ?>)" class="text-error"><i class="fa-solid fa-trash"></i> Xóa</a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div id="reply-form-<?= $new_comment['id'] ?>" class="hidden mt-3 pl-4 animate-fade-in relative">
                                    <div class="flex gap-3">
                                        <div class="w-8 h-8 rounded-full bg-base-200 flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-reply text-xs text-base-content/40"></i>
                                        </div>
                                        <div class="flex-1 relative">
                                            <div class="comment-input-area mb-2 relative flex flex-col">
                                                <textarea id="reply-content-<?= $new_comment['id'] ?>" class="textarea textarea-ghost w-full focus:bg-transparent focus:outline-none min-h-[44px] max-h-[200px] overflow-y-auto text-sm" placeholder="Viết câu trả lời..." oninput="updateCommentUI(this)"></textarea>
                                                <div class="flex justify-between items-center px-3 pb-2">
                                                    <button onclick="toggleEmojiPicker('reply-content-<?= $new_comment['id'] ?>')" class="vsd-emoji-btn vsd-emoji-btn-sm" title="Chèn Emoji">
                                                        <i class="fa-regular fa-face-smile"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button onclick="toggleReply('<?= $new_comment['id'] ?>')" class="btn btn-ghost btn-xs">Hủy</button>
                                                <button onclick="handlePostComment('<?= $new_comment['id'] ?>')" class="btn btn-primary btn-xs">Gửi</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pl-14 space-y-4 mt-4" id="replies-<?= $new_comment['id'] ?>"></div>
                        </div>
                    </div>
                    <?php
                }
                $html = ob_get_clean();
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'html' => $html]);
                exit;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Lỗi không xác định khi tạo bình luận']);
            exit;

        case 'like_comment':
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $check = $VSD->get_row("SELECT * FROM comment_likes WHERE comment_id = $comment_id AND user_id = $user_id");
            if ($check) {
                $VSD->query("DELETE FROM comment_likes WHERE id = " . $check['id']);
            } else {
                $VSD->insert('comment_likes', [
                    'comment_id' => $comment_id,
                    'user_id' => $user_id
                ]);
            }
            $count = intval($VSD->num_rows("SELECT id FROM comment_likes WHERE comment_id = $comment_id"));
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => $count]);
            exit;

        case 'delete_comment':
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $comment = $VSD->get_row("SELECT * FROM document_comments WHERE id = $comment_id");
            if ($comment && ($comment['user_id'] == $user_id || $doc['user_id'] == $user_id)) {
                $VSD->query("DELETE FROM document_comments WHERE id = $comment_id OR parent_id = $comment_id");
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa bình luận này']);
            }
            exit;

        case 'pin_comment':
        case 'unpin_comment':
            if ($doc['user_id'] != $user_id) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện']);
                exit;
            }
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $is_pinned = ($action === 'pin_comment' ? 1 : 0);
            
            if ($is_pinned) {
                 // Option: Unpin all others
                 $VSD->query("UPDATE document_comments SET is_pinned = 0 WHERE document_id = $doc_id");
            }
            
            $VSD->update('document_comments', ['is_pinned' => $is_pinned], "id = $comment_id");
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'edit_comment':
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $comment = $VSD->get_row("SELECT * FROM document_comments WHERE id = $comment_id");
            if ($comment && $comment['user_id'] == $user_id) {
                $VSD->update('document_comments', ['content' => $content], "id = $comment_id");
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'content' => $content]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa']);
            }
            exit;

        case 'report_comment':
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $VSD->insert('comment_reports', [
                'comment_id' => $comment_id,
                'user_id' => $user_id,
                'reason' => $reason
            ]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
    }
}

/**
 * Renders comment content with newlines and custom emojis
 * @param string $content
 * @param array $emoji_map (Optional) Array of name => path
 * @return string
 */
function render_comment_content($content, $emoji_map = null) {
    if (!$emoji_map) {
        global $VSD;
        static $cached_emojis = null;
        if ($cached_emojis === null) {
            $cached_emojis = [];
            $res = $VSD->get_results("SELECT name, file_path FROM emojis WHERE is_active = 1") ?: [];
            foreach ($res as $e) {
                $cached_emojis[$e['name']] = $e['file_path'];
            }
        }
        $emoji_map = $cached_emojis;
    }

    $content = htmlspecialchars($content);
    
    // Replace shortcodes :emoji_name: with <img> tags
    $content = preg_replace_callback('/:([a-z0-9_]+):/', function($matches) use ($emoji_map) {
        $name = $matches[1];
        if (isset($emoji_map[$name])) {
            $path = htmlspecialchars($emoji_map[$name]);
            return "<img src=\"$path\" class=\"inline-block w-[18px] h-[18px] align-text-bottom mx-[2px]\" alt=\":$name:\" loading=\"lazy\">";
        }
        return $matches[0];
    }, $content);

    return nl2br($content);
}
