<?php
include D_ROOT . '/resources/views/partials/head.php';
include D_ROOT . '/resources/views/partials/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8 flex flex-col items-center justify-center">
        <div class="text-center mb-12">
            <h1 class="text-5xl font-black text-primary mb-4">Nâng Cấp Premium</h1>
            <p class="text-xl opacity-60">Mở khóa toàn bộ tính năng và tải không giới hạn</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 w-full max-w-6xl">
            <!-- Free Plan -->
            <div class="card bg-base-100 shadow-xl rounded-[3rem] p-8 border border-base-200">
                <h3 class="text-xl font-bold mb-4">Miễn Phí</h3>
                <div class="text-4xl font-black mb-6">0 VSD <span class="text-sm opacity-50">/ tháng</span></div>
                <ul class="space-y-4 mb-8 opacity-70">
                    <li><i class="fa-solid fa-check text-success mr-2"></i> Xem tài liệu công khai</li>
                    <li><i class="fa-solid fa-check text-success mr-2"></i> Đăng tài liệu</li>
                    <li class="opacity-30"><i class="fa-solid fa-xmark mr-2"></i> Tải không giới hạn</li>
                </ul>
                <button class="btn btn-ghost rounded-2xl w-full" disabled>Đang sử dụng</button>
            </div>

            <!-- Premium Plan (Featured) -->
            <div class="card bg-primary text-primary-content shadow-2xl rounded-[3rem] p-8 scale-105 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 bg-accent text-accent-content font-bold text-xs uppercase rounded-bl-3xl">Phổ biến</div>
                <h3 class="text-xl font-bold mb-4">Premium</h3>
                <div class="text-4xl font-black mb-6">99.000 VSD <span class="text-sm opacity-70">/ tháng</span></div>
                <ul class="space-y-4 mb-8">
                    <li><i class="fa-solid fa-check mr-2"></i> Không quảng cáo</li>
                    <li><i class="fa-solid fa-check mr-2"></i> Tải tài liệu Premium</li>
                    <li><i class="fa-solid fa-check mr-2"></i> Huy hiệu vương miện</li>
                </ul>
                <button class="btn bg-white text-primary border-none rounded-2xl w-full font-black">NÂNG CẤP NGAY</button>
            </div>

            <!-- LifeTime Plan -->
            <div class="card bg-base-100 shadow-xl rounded-[3rem] p-8 border border-base-200">
                <h3 class="text-xl font-bold mb-4">Vĩnh Viễn</h3>
                <div class="text-4xl font-black mb-6">499.000 VSD</div>
                <ul class="space-y-4 mb-8 opacity-70">
                    <li><i class="fa-solid fa-check text-success mr-2"></i> Mọi quyền lợi Premium</li>
                    <li><i class="fa-solid fa-check text-success mr-2"></i> Thanh toán 1 lần</li>
                    <li><i class="fa-solid fa-check text-success mr-2"></i> Hỗ trợ VIP 24/7</li>
                </ul>
                <button class="btn btn-outline btn-primary rounded-2xl w-full">CHỌN GÓI</button>
            </div>
        </div>
    </main>

    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>


