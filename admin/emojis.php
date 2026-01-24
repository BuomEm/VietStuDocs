<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';

redirectIfNotAdmin();

$page_title = "Quản lý Emoji";
$admin_active_page = 'emojis';

// Fetch emojis
$emojis = $VSD->get_results("SELECT * FROM emojis ORDER BY created_at DESC");

include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-face-smile text-primary"></i>
                    Quản lý Custom Emoji
                </h1>
                <p class="text-base-content/60 text-sm">Thêm, xóa và quản lý các biểu tượng cảm xúc tùy chỉnh cho bình luận.</p>
            </div>
            <button onclick="document.getElementById('addEmojiModal').showModal()" class="btn btn-primary rounded-xl">
                <i class="fa-solid fa-plus"></i> Thêm Emoji mới
            </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php if(empty($emojis)): ?>
                <div class="col-span-full py-20 text-center bg-base-100 rounded-3xl border border-dashed border-base-content/20">
                    <i class="fa-regular fa-face-frown text-4xl mb-3 opacity-20"></i>
                    <p class="opacity-50 font-medium">Chưa có emoji nào được thêm.</p>
                </div>
            <?php else: foreach($emojis as $emoji): ?>
                <div class="bg-base-100 p-4 rounded-2xl border border-base-content/5 shadow-sm group hover:shadow-xl hover:border-primary/20 transition-all text-center relative">
                    <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="deleteEmoji(<?= $emoji['id'] ?>)" class="btn btn-xs btn-error btn-square rounded-lg">
                            <i class="fa-solid fa-trash text-[10px]"></i>
                        </button>
                    </div>
                    
                    <div class="w-16 h-16 mx-auto mb-3 bg-base-200 rounded-xl p-2 flex items-center justify-center">
                        <img src="<?= htmlspecialchars($emoji['file_path']) ?>" alt="<?= $emoji['name'] ?>" class="max-w-full max-h-full object-contain">
                    </div>
                    
                    <div class="font-bold text-sm truncate" title="<?= $emoji['name'] ?>"><?= htmlspecialchars($emoji['name']) ?></div>
                    <div class="text-[10px] font-mono opacity-50 bg-base-200 rounded px-1 mt-1 inline-block"><?= htmlspecialchars($emoji['shortcode']) ?></div>
                    
                    <div class="mt-3">
                        <input type="checkbox" class="toggle toggle-xs toggle-primary" <?= $emoji['is_active'] ? 'checked' : '' ?> onchange="toggleEmojiStatus(<?= $emoji['id'] ?>, this.checked)">
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Add Emoji Modal -->
<dialog id="addEmojiModal" class="modal">
    <div class="modal-box rounded-3xl border border-base-300">
        <h3 class="font-black text-xl flex items-center gap-3 text-primary uppercase tracking-tighter mb-6">
            <i class="fa-solid fa-plus-circle"></i> THÊM EMOJI MỚI
        </h3>
        
        <form id="addEmojiForm" class="space-y-4">
            <input type="hidden" name="action" value="add">
            
            <div class="form-control">
                <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Tên Emoji</span></label>
                <input type="text" name="name" required placeholder="ví dụ: math_fire" class="input input-bordered bg-base-200 rounded-xl font-bold" pattern="[a-z0-9_]+" title="Chỉ dùng chữ thường, số và dấu gạch dưới">
                <label class="label"><span class="label-text-alt opacity-50">Shortcode sẽ là :tên_emoji:</span></label>
            </div>

            <div class="form-control">
                <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Hình ảnh</span></label>
                <div class="flex items-center gap-4">
                    <div id="emojiPreview" class="w-16 h-16 bg-base-200 rounded-2xl border border-dashed border-base-content/20 flex items-center justify-center overflow-hidden">
                        <i class="fa-solid fa-image opacity-20"></i>
                    </div>
                    <div class="flex-1">
                        <input type="file" name="emoji_file" required accept="image/png,image/webp,image/gif" class="file-input file-input-bordered file-input-primary w-full rounded-xl" onchange="previewEmoji(this)">
                        <div class="text-[10px] mt-2 opacity-50">Hỗ trợ PNG, JPG, GIF, WebP. Hệ thống tự động tối ưu & chuyển đổi.</div>
                    </div>
                </div>
            </div>

            <div class="modal-action gap-3 mt-8">
                <button type="button" class="btn btn-ghost rounded-xl font-bold" onclick="this.closest('dialog').close()">Hủy bỏ</button>
                <button type="submit" class="btn btn-primary rounded-xl px-10 font-black uppercase tracking-widest" id="submitBtn">Lưu Emoji</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop bg-base-neutral/20 backdrop-blur-[2px]">
        <button>close</button>
    </form>
</dialog>

<script>
function previewEmoji(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('emojiPreview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-contain">`;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('addEmojiForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner"></span> Đang lưu...';

    const formData = new FormData(this);
    fetch('../handler/admin_emoji_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        showAlert('Có lỗi xảy ra', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

function toggleEmojiStatus(id, active) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    formData.append('active', active ? 1 : 0);

    fetch('../handler/admin_emoji_handler.php', {
        method: 'POST',
        body: formData
    });
}

function deleteEmoji(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa emoji này? Comments đã dùng emoji này sẽ hiển thị dưới dạng text shortcode.')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('../handler/admin_emoji_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showAlert(data.message, 'error');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
