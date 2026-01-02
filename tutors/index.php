<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

$page_title = "Tìm Gia Sư - VietStuDocs";
$current_page = 'tutors';
$tutors = getActiveTutors($_GET);
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <div class="container mx-auto">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Sidebar Filter -->
                <div class="w-full md:w-1/4">
                    <div class="card bg-base-100 shadow-xl sticky top-24">
                        <div class="card-body">
                            <h2 class="card-title text-primary"><i class="fa-solid fa-filter"></i> Bộ lọc</h2>
                            <form action="" method="GET">
                                <div class="form-control w-full">
                                    <label class="label"><span class="label-text">Môn học</span></label>
                                    <input type="text" name="subject" value="<?= htmlspecialchars($_GET['subject'] ?? '') ?>" placeholder="Toán, Lý, Anh..." class="input input-bordered w-full" />
                                </div>
                                <div class="form-control w-full mt-4">
                                    <button type="submit" class="btn btn-primary w-full">Tìm kiếm</button>
                                    <a href="tutors" class="btn btn-ghost w-full mt-2">Xóa bộ lọc</a>
                                </div>
                            </form>
                            
                            <div class="divider"></div>
                            
                            <div class="text-center">
                                <p class="mb-2">Bạn muốn trở thành gia sư?</p>
                                <a href="/tutors/apply" class="btn btn-secondary btn-sm">Đăng ký ngay</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tutor List -->
                <div class="w-full md:w-3/4">
                    <h1 class="text-3xl font-bold mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-secondary"></i> Danh sách Gia Sư
                    </h1>
                    
                    <?php if(empty($tutors)): ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Chưa có gia sư nào phù hợp với tìm kiếm của bạn.</span>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-6">
                            <?php foreach($tutors as $tutor): ?>
                                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 border border-base-200">
                                    <div class="card-body flex-row gap-6 p-6">
                                        <!-- Avatar -->
                                        <div class="flex-none hidden md:block">
                                            <div class="avatar <?= !empty($tutor['avatar']) ? '' : 'placeholder' ?>">
                                                <div class="rounded-full w-24 border border-base-300 overflow-hidden ring ring-offset-base-100 ring-4 ring-primary/10 <?= empty($tutor['avatar']) ? 'bg-primary text-primary-content flex items-center justify-center font-bold text-4xl' : '' ?>">
                                                    <?php if(!empty($tutor['avatar']) && file_exists('../uploads/avatars/' . $tutor['avatar'])): ?>
                                                        <img src="../uploads/avatars/<?= $tutor['avatar'] ?>" alt="Avatar" />
                                                    <?php else: ?>
                                                        <span><?= strtoupper(substr($tutor['username'], 0, 1)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-center mt-2">
                                                <div class="badge badge-accent badge-outline font-bold">
                                                    <i class="fa-solid fa-star text-yellow-500 mr-1"></i> <?= $tutor['rating'] ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Info -->
                                        <div class="flex-grow">
                                            <div class="flex justify-between items-start">
                                                <h2 class="card-title text-2xl mb-1">
                                                    <?= htmlspecialchars($tutor['username']) ?>
                                                    <div class="badge badge-primary">Gia sư</div>
                                                </h2>
                                                <!-- Mobile Avatar (visible only on small screens) -->
                                                <div class="avatar <?= !empty($tutor['avatar']) ? '' : 'placeholder' ?> md:hidden">
                                                    <div class="bg-neutral text-neutral-content rounded-full w-12 overflow-hidden ring ring-offset-base-100 ring-2 ring-primary/10">
                                                        <?php if(!empty($tutor['avatar']) && file_exists('../uploads/avatars/' . $tutor['avatar'])): ?>
                                                            <img src="../uploads/avatars/<?= $tutor['avatar'] ?>" alt="Avatar" />
                                                        <?php else: ?>
                                                            <span class="text-xl font-bold"><?= strtoupper(substr($tutor['username'], 0, 1)) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <p class="text-sm text-base-content/70 mb-3">
                                                <i class="fa-solid fa-check-circle text-success"></i> Đã giải quyết <?= $tutor['completed_count'] ?> câu hỏi
                                            </p>
                                            
                                            <div class="flex flex-wrap gap-2 mb-4">
                                                <?php 
                                                $subjects = explode(',', $tutor['subjects']); 
                                                foreach($subjects as $subj): 
                                                ?>
                                                    <div class="badge badge-secondary badge-outline"><?= trim(htmlspecialchars($subj)) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <p class="line-clamp-2 text-base-content/80 mb-4"><?= htmlspecialchars($tutor['bio']) ?></p>
                                            
                                            <div class="flex flex-wrap gap-4 text-sm font-medium bg-base-200 p-3 rounded-lg">
                                                <div class="tooltip" data-tip="Trả lời ngắn gọn">
                                                    <span class="text-success">Basic:</span> <?= $tutor['price_basic'] ?> pts
                                                </div>
                                                <div class="divider divider-horizontal m-0"></div>
                                                <div class="tooltip" data-tip="Giải thích chi tiết">
                                                    <span class="text-info">Standard:</span> <?= $tutor['price_standard'] ?> pts
                                                </div>
                                                <div class="divider divider-horizontal m-0"></div>
                                                <div class="tooltip" data-tip="Giải chi tiết + file">
                                                    <span class="text-warning">Premium:</span> <?= $tutor['price_premium'] ?> pts
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Action -->
                                        <div class="flex-none flex flex-col justify-center">
                                            <button onclick="openRequestModal(<?= $tutor['user_id'] ?>, '<?= htmlspecialchars($tutor['username']) ?>')" class="btn btn-primary btn-block md:btn-wide">
                                                Đặt câu hỏi <i class="fa-solid fa-arrow-right"></i>
                                            </button>
                                        </div>
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
</div>

<!-- Request Modal -->
<dialog id="request_modal" class="modal">
  <div class="modal-box w-11/12 max-w-3xl">
    <h3 class="font-bold text-lg">Đặt câu hỏi cho <span id="modal_tutor_name" class="text-primary"></span></h3>
    <form id="requestForm" class="mt-4" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_request">
        <input type="hidden" name="tutor_id" id="modal_tutor_id">
        
        <div class="form-control">
            <label class="label">Tiêu đề câu hỏi</label>
            <input type="text" name="title" required class="input input-bordered" placeholder="Ví dụ: Giúp em bài toán tích phân này với">
        </div>
        
        <div class="form-control mt-2">
            <label class="label">Nội dung chi tiết</label>
            <textarea name="content" required class="textarea textarea-bordered h-32" placeholder="Mô tả chi tiết câu hỏi của bạn..."></textarea>
        </div>
        
        <div class="form-control mt-2">
            <label class="label">Gói câu hỏi</label>
            <div class="grid grid-cols-3 gap-4">
                <label class="label cursor-pointer border rounded p-2 hover:bg-base-200">
                    <span class="label-text">
                        <div class="font-bold">Basic</div>
                        <div class="text-xs">Trả lời ngắn</div>
                    </span> 
                    <input type="radio" name="package_type" value="basic" class="radio radio-success" checked />
                </label>
                <label class="label cursor-pointer border rounded p-2 hover:bg-base-200">
                    <span class="label-text">
                        <div class="font-bold">Standard</div>
                        <div class="text-xs">Giải thích chi tiết</div>
                    </span> 
                    <input type="radio" name="package_type" value="standard" class="radio radio-info" />
                </label>
                <label class="label cursor-pointer border rounded p-2 hover:bg-base-200">
                    <span class="label-text">
                        <div class="font-bold">Premium</div>
                        <div class="text-xs">Giải + File</div>
                    </span> 
                    <input type="radio" name="package_type" value="premium" class="radio radio-warning" />
                </label>
            </div>
        </div>

        <div class="form-control mt-2 hidden" id="file_upload_div">
            <label class="label"><span class="label-text">Đính kèm file (Dành cho gói Premium)</span></label>
            <input type="file" name="attachment" class="file-input file-input-bordered w-full" />
            <label class="label"><span class="label-text-alt text-warning">Hỗ trợ: Ảnh, PDF, Word, Zip. Tối đa 5MB.</span></label>
        </div>

        <div class="modal-action">
            <button type="button" class="btn" onclick="request_modal.close()">Hủy</button>
            <button type="submit" class="btn btn-primary">Gửi câu hỏi</button>
        </div>
    </form>
  </div>
</dialog>

<script>
function openRequestModal(tutorId, tutorName) {
    document.getElementById('modal_tutor_name').innerText = tutorName;
    document.getElementById('modal_tutor_id').value = tutorId;
    document.getElementById('request_modal').showModal();
}

// Logic to show/hide file input based on package
const packageRadios = document.querySelectorAll('input[name="package_type"]');
const fileUploadDiv = document.getElementById('file_upload_div');

packageRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        if(this.value === 'premium') {
            fileUploadDiv.classList.remove('hidden');
        } else {
            fileUploadDiv.classList.add('hidden');
            // Reset file input if needed
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
            alert(data.message);
        }
    } catch(err) {
        alert('Có lỗi xảy ra');
    }
});
</script>
