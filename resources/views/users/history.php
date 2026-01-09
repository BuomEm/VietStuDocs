<?php
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black flex items-center gap-3">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                Lịch sử giao dịch
            </h1>
            <p class="opacity-60">Theo dõi các hoạt động cộng/trừ điểm của bạn</p>
        </div>

        <div class="overflow-x-auto bg-base-100 rounded-[2rem] shadow-xl border border-base-200">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="bg-base-200">
                        <th class="rounded-tl-[2rem]">Thời gian</th>
                        <th>Loại</th>
                        <th>Số điểm</th>
                        <th>Nội dung</th>
                        <th class="rounded-tr-[2rem]">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($transactions) > 0): ?>
                        <?php foreach($transactions as $t): ?>
                            <tr>
                                <td class="text-xs opacity-50"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $t['transaction_type'] === 'earn' ? 'badge-success' : 'badge-error' ?> badge-sm font-bold">
                                        <?= $t['transaction_type'] === 'earn' ? 'Nhận' : 'Dùng' ?>
                                    </span>
                                </td>
                                <td class="font-bold <?= $t['transaction_type'] === 'earn' ? 'text-success' : 'text-error' ?>">
                                    <?= $t['transaction_type'] === 'earn' ? '+' : '-' ?> <?= number_format($t['points']) ?>
                                </td>
                                <td class="max-w-xs truncate"><?= htmlspecialchars($t['reason']) ?></td>
                                <td><span class="text-[10px] uppercase font-black opacity-30"><?= $t['status'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-10 opacity-30">Chưa có giao dịch nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


