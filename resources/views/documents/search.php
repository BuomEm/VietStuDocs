<?php
// Giao diện Tìm kiếm tài liệu
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black mb-2">Kết quả tìm kiếm cho: "<?= htmlspecialchars($keyword) ?>"</h1>
            <p class="opacity-60">Tìm thấy <?= count($results) ?> tài liệu phù hợp</p>
        </div>

        <?php if(count($results) > 0): ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach($results as $doc): ?>
                    <div class="card bg-base-100 shadow-sm border border-base-200 rounded-[2rem] overflow-hidden">
                        <div class="p-6">
                            <h3 class="font-bold mb-2 line-clamp-2"><?= htmlspecialchars($doc['original_name']) ?></h3>
                            <div class="flex items-center justify-between mt-4">
                                <span class="text-xs opacity-50"><?= $doc['username'] ?></span>
                                <a href="/view?id=<?= $doc['id'] ?>" class="btn btn-primary btn-sm rounded-xl">Xem ngay</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 opacity-30">
                <i class="fa-solid fa-magnifying-glass text-6xl mb-4"></i>
                <p>Không tìm thấy kết quả nào phù hợp.</p>
            </div>
        <?php endif; ?>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


