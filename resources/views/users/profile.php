<?php
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <div class="max-w-4xl mx-auto">
            <div class="card bg-base-100 shadow-xl rounded-[3rem] overflow-hidden">
                <div class="h-32 bg-primary/10"></div>
                <div class="px-8 pb-8 relative">
                    <div class="absolute -top-16 left-8">
                        <div class="avatar">
                            <div class="w-32 h-32 rounded-3xl ring ring-base-100 ring-offset-base-100 ring-offset-2 overflow-hidden shadow-2xl">
                                <?php if($user['avatar']): ?>
                                    <img src="/uploads/avatars/<?= $user['avatar'] ?>" />
                                <?php else: ?>
                                    <div class="bg-primary text-primary-content flex items-center justify-center h-full text-4xl font-bold">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-20">
                        <h1 class="text-3xl font-black mb-1"><?= htmlspecialchars($user['username']) ?></h1>
                        <p class="text-sm opacity-50 mb-6"><?= htmlspecialchars($user['email']) ?></p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-control">
                                <label class="label"><span class="label-text font-bold">Tên đăng nhập</span></label>
                                <input type="text" value="<?= $user['username'] ?>" class="input input-bordered rounded-xl" disabled>
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text font-bold">Ngày tham gia</span></label>
                                <input type="text" value="<?= date('d/m/Y', strtotime($user['created_at'])) ?>" class="input input-bordered rounded-xl" disabled>
                            </div>
                        </div>
                        
                        <div class="mt-8 flex gap-2">
                            <button class="btn btn-primary rounded-xl px-8">Cập nhật thông tin</button>
                            <a href="/logout" class="btn btn-error btn-ghost rounded-xl">Đăng xuất</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


