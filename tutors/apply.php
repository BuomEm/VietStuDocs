<?php
session_start();
require_once __DIR__ . '/../config/auth.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$page_title = "Đăng ký làm Gia Sư - VietStuDocs";
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="flex-1 p-6">
        <div class="container mx-auto">
            <div class="max-w-2xl mx-auto">
                <div class="card bg-base-100 shadow-xl border border-primary/20">
                    <div class="card-body">
                        <h1 class="text-3xl font-bold text-center mb-2">Đăng ký làm Gia Sư</h1>
                        <p class="text-center text-base-content/70 mb-6">Chia sẻ kiến thức và kiếm Points từ cộng đồng!</p>
                        
                        <form id="applyForm" class="space-y-4">
                            <input type="hidden" name="action" value="register_tutor">
                            
                            <div class="form-control">
                                <label class="label font-bold">Môn học bạn có thể dạy</label>
                                <input type="text" name="subjects" required placeholder="Ví dụ: Toán Cao Cấp, Lập Trình Web, Tiếng Anh..." class="input input-bordered" />
                                <label class="label text-xs text-base-content/60">Phân cách bằng dấu phẩy</label>
                            </div>
                            
                            <div class="form-control">
                                <label class="label font-bold">Giới thiệu bản thân & Kinh nghiệm</label>
                                <textarea name="bio" required class="textarea textarea-bordered h-32" placeholder="Tôi là sinh viên năm 3 ĐHBK, có kinh nghiệm dạy kèm..."></textarea>
                            </div>
                            
                            <div class="divider">Thiết lập giá Points</div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="form-control">
                                    <label class="label text-sm">Gói Basic</label>
                                    <input type="number" name="price_basic" value="20" min="10" class="input input-bordered input-sm" />
                                </div>
                                <div class="form-control">
                                    <label class="label text-sm">Gói Standard</label>
                                    <input type="number" name="price_standard" value="50" min="20" class="input input-bordered input-sm" />
                                </div>
                                <div class="form-control">
                                    <label class="label text-sm">Gói Premium</label>
                                    <input type="number" name="price_premium" value="100" min="50" class="input input-bordered input-sm" />
                                </div>
                            </div>
                            
                            <div class="alert alert-warning text-sm mt-4">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>Đơn đăng ký của bạn sẽ được Admin duyệt trong vòng 24h.</span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-full text-lg mt-6">Gửi Đăng Ký</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
</div>

<script>
document.getElementById('applyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const res = await fetch('/handler/tutor_handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if(data.success) {
            alert(data.message);
            window.location.href = '/tutors/';
        } else {
            alert(data.message);
        }
    } catch(err) {
        alert('Có lỗi xảy ra');
    }
});
</script>
