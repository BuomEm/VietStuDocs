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
                                <div>
                                    <span class="text-base-content/60">Người hỏi:</span>
                                    <span class="font-bold"><?= htmlspecialchars($request['student_name']) ?></span>
                                </div>
                                <div>
                                    <span class="text-base-content/60">Gia sư:</span>
                                    <span class="font-bold"><?= htmlspecialchars($request['tutor_name']) ?></span>
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

                    <!-- Answer Section (Loop through all answers) -->
                    <?php if (!empty($request['answers'])): ?>
                        <?php foreach($request['answers'] as $index => $answer): ?>
                            <div class="card bg-success/10 border border-success/30 shadow-xl mb-4">
                                <div class="card-body">
                                    <h3 class="card-title text-success"><i class="fa-solid fa-check-circle"></i> Câu trả lời #<?= $index + 1 ?></h3>
                                    <div class="divider my-1"></div>
                                    <div class="prose max-w-none mt-2 whitespace-pre-wrap"><?= trim(htmlspecialchars($answer['content'])) ?></div>
                                    
                                    <?php if(!empty($answer['attachment'])): ?>
                                        <div class="mt-2 bg-base-100 p-3 rounded-lg border border-base-200">
                                            <div class="flex items-center gap-2 mb-2">
                                                <i class="fa-solid fa-paperclip"></i> 
                                                <span class="font-bold text-sm">File đính kèm</span>
                                            </div>
                                            
                                            <?php 
                                            $ext = strtolower(pathinfo($answer['attachment'], PATHINFO_EXTENSION));
                                            if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                                            ?>
                                                <img src="/uploads/tutors/<?= htmlspecialchars($answer['attachment']) ?>" class="max-w-full rounded-lg shadow-sm" alt="Answer Attachment">
                                            <?php else: ?>
                                                <div class="flex items-center justify-between bg-base-200 p-2 rounded">
                                                    <span class="text-sm truncate mr-2"><?= htmlspecialchars($answer['attachment']) ?></span>
                                                    <a href="/uploads/tutors/<?= htmlspecialchars($answer['attachment']) ?>" download class="btn btn-sm btn-primary">
                                                        <i class="fa-solid fa-download"></i> Tải về
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-right mt-4 text-xs text-base-content/60">
                                        Trả lời lúc: <?= date('d/m/Y H:i', strtotime($answer['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

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

                    <!-- Tutor Answer Input (Allow answering if pending OR answered) -->
                    <?php if ($is_tutor && ($request['status'] === 'pending' || $request['status'] === 'answered')): ?>
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
                                    
                                    <div class="card-actions justify-end mt-4">
                                        <button type="submit" class="btn btn-primary">Gửi câu trả lời</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($is_student && $request['status'] === 'pending'): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <span>Đang chờ Gia sư phản hồi. Bạn sẽ nhận được thông báo khi có câu trả lời.</span>
                        </div>
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
document.getElementById('answerForm')?.addEventListener('submit', async function(e) {
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
            location.reload();
        } else {
            alert(data.message);
        }
    } catch(err) {
        alert('Có lỗi xảy ra');
    }
});

document.getElementById('ratingForm')?.addEventListener('submit', async function(e) {
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
            location.reload();
        } else {
            alert(data.message);
        }
    } catch(err) {
        alert('Có lỗi xảy ra');
    }
});
</script>
