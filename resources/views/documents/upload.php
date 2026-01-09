<?php
// Giao diện Tải lên tài liệu
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-extrabold flex items-center gap-3 text-base-content">
                    <div class="p-3 rounded-2xl bg-primary/10 text-primary shadow-inner">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    Tải Lên Tài Liệu
                </h1>
                <p class="text-base-content/60 mt-2 font-medium">Chia sẻ kiến thức, nhận lại giá trị xứng đáng</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
            <div class="xl:col-span-8">
                <!-- Dropzone Area -->
                <div id="dragDropArea" class="rounded-[2rem] bg-base-100 border-2 border-dashed border-base-300 p-20 text-center cursor-pointer hover:border-primary transition-all">
                    <i class="fa-solid fa-folder-open text-5xl mb-4 opacity-20"></i>
                    <h2 class="text-2xl font-bold">Kéo thả file vào đây</h2>
                    <p class="opacity-50">Hỗ trợ PDF, Word, Excel, PowerPoint...</p>
                </div>
                <input type="file" id="fileInput" class="hidden" multiple>
            </div>

            <div class="xl:col-span-4">
                <div class="card bg-primary text-primary-content shadow-xl rounded-[2rem]">
                    <div class="card-body">
                        <span class="text-xs uppercase font-bold opacity-70">Số dư hiện tại</span>
                        <div class="text-4xl font-black"><?= number_format($user_points['current_points']) ?> VSD</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


