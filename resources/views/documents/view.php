<?php
// Giao diện Xem chi tiết tài liệu
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <div class="max-w-5xl mx-auto">
            <!-- Tài liệu Header -->
            <div class="flex flex-col md:flex-row gap-6 mb-8">
                <div class="flex-1">
                    <h1 class="text-3xl font-black text-base-content mb-2"><?= htmlspecialchars($doc['original_name']) ?></h1>
                    <div class="flex items-center gap-4 text-sm opacity-60">
                        <span class="flex items-center gap-1"><i class="fa-solid fa-user"></i> <?= $doc['username'] ?></span>
                        <span class="flex items-center gap-1"><i class="fa-solid fa-eye"></i> <?= $doc['views'] ?> lượt xem</span>
                        <span class="flex items-center gap-1"><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                    </div>
                </div>
                <div class="flex gap-2 h-fit">
                    <button class="btn btn-primary"><i class="fa-solid fa-download"></i> Tải xuống</button>
                    <button class="btn btn-ghost"><i class="fa-solid fa-share"></i> Chia sẻ</button>
                </div>
            </div>

            <!-- Viewer Area (Sử dụng PDF.js hoặc Docx-preview) -->
            <div class="bg-base-300 rounded-3xl overflow-hidden min-h-[600px] flex items-center justify-center relative">
                <div class="absolute inset-0 flex items-center justify-center bg-base-100/50 backdrop-blur-sm z-10">
                    <div class="text-center">
                        <i class="fa-solid fa-lock text-5xl mb-4 opacity-20"></i>
                        <p class="font-bold">Đang tải tài liệu...</p>
                    </div>
                </div>
                <!-- Nội dung Viewer thực tế sẽ được script xử lý -->
            </div>
        </div>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


