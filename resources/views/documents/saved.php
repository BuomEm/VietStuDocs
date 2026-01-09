<?php
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black flex items-center gap-3">
                <i class="fa-solid fa-bookmark text-primary"></i>
                Tài liệu đã lưu
            </h1>
            <p class="opacity-60">Danh sách các tài liệu bạn đã đánh dấu để xem lại</p>
        </div>

        <?php if(count($docs) > 0): ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach($docs as $doc): ?>
                    <div class="document-card-vsd bg-base-100 group">
                        <div class="aspect-[3/4] bg-base-200 relative overflow-hidden">
                            <?php if ($doc['thumbnail']): ?>
                                <img src="/uploads/<?= $doc['thumbnail'] ?>" class="w-full h-full object-cover" loading="lazy">
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full opacity-20">
                                    <i class="fa-solid fa-file text-6xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <a href="/view?id=<?= $doc['id'] ?>" class="btn btn-primary btn-circle"><i class="fa-solid fa-eye"></i></a>
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold line-clamp-2"><?= htmlspecialchars($doc['original_name']) ?></h3>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 opacity-30">
                <i class="fa-solid fa-bookmark text-6xl mb-4"></i>
                <p>Bạn chưa lưu tài liệu nào.</p>
            </div>
        <?php endif; ?>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


