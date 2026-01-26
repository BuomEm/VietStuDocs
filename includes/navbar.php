<?php
// Make sure variables are defined
if(!isset($user_id)) $user_id = null;
if(!isset($is_premium)) $is_premium = false;
if(!isset($current_page)) $current_page = '';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/premium.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';

$has_admin = isset($_SESSION['user_id']) && hasAdminAccess();

// Get user points if logged in
$user_points_data = null;
$user_info = null;
$current_points = 0;

if(isset($_SESSION['user_id'])) {
    $user_id = getCurrentUserId();
    $user_points_data = getUserPoints($user_id);
    $user_info = getUserInfo($user_id);
    $is_premium = isPremium($user_id);
    $current_points = $user_points_data['current_points'] ?? 0;
}

$site_name = function_exists('getSetting') ? getSetting('site_name', 'VietStuDocs') : 'VietStuDocs';
$site_logo = function_exists('getSetting') ? getSetting('site_logo') : '';
?>

<div class="navbar bg-base-100/80 backdrop-blur-md border-b border-base-200 sticky top-0 z-50 transition-all duration-300 shadow-sm" id="main-navbar">
    <!-- Left: Mobile Menu & Logo -->
    <div class="navbar-start flex-1 lg:flex-none lg:w-1/4">
        <label for="drawer-toggle" class="btn btn-ghost btn-circle mr-1 lg:hidden">
            <i class="fa-solid fa-bars text-xl"></i>
        </label>
        
        <a href="/dashboard" class="flex items-center gap-2 group transition-all duration-300 hover:scale-105 shrink-0">
            <?php if (!empty($site_logo)): ?>
                <img src="<?= htmlspecialchars($site_logo) ?>" loading="lazy" alt="Logo" class="h-8 w-8 sm:h-9 sm:w-9 object-contain drop-shadow-sm">
            <?php else: ?>
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-primary to-primary-focus flex items-center justify-center text-primary-content shadow-lg shadow-primary/20">
                    <i class="fa-solid fa-graduation-cap text-lg"></i>
                </div>
            <?php endif; ?>
            <span class="font-black text-lg sm:text-xl text-[#b91c1c] tracking-tight">
                <?= htmlspecialchars($site_name) ?>
            </span>
        </a>
    </div>

    <!-- Center: Search Bar -->
    <div class="navbar-center hidden lg:flex flex-1 max-w-2xl px-4">
        <div class="w-full relative group/search">
            <div class="relative transition-all duration-300 transform origin-center group-focus-within/search:scale-[1.02]">
                <form action="/search" method="GET" class="flex w-full">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-magnifying-glass text-base-content/40 group-focus-within/search:text-primary transition-colors"></i>
                        </div>
                        <input 
                            type="text" 
                            name="q" 
                            placeholder="Tìm kiếm tài liệu, đề thi, giáo trình..." 
                            class="input input-bordered w-full pl-10 pr-4 bg-base-200/50 border-transparent focus:bg-base-100 focus:border-primary focus:shadow-lg focus:shadow-primary/10 rounded-2xl transition-all h-11 text-sm font-medium placeholder:text-base-content/40"
                            id="searchInput"
                            autocomplete="off"
                        />
                        <!-- Keyboard Shortcut Hint -->
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <kbd class="kbd kbd-sm bg-base-100 border-base-content/10 text-[10px] font-mono opacity-50 hidden group-hover/search:inline-flex">/</kbd>
                        </div>
                    </div>
                </form>
                <!-- Autocomplete suggestions -->
                <div class="absolute top-full left-0 right-0 mt-2 bg-base-100/95 backdrop-blur-xl shadow-2xl rounded-2xl border border-base-200 overflow-hidden z-[100] hidden transform origin-top transition-all duration-200" id="searchSuggestions"></div>
            </div>
        </div>
    </div>
    
    <!-- Right: Actions & User -->
    <div class="navbar-end flex-none w-auto gap-1 sm:gap-2">
        <!-- Search Mobile Toggle -->
        <button class="btn btn-ghost btn-circle lg:hidden text-base-content/70" onclick="toggleMobileSearch()">
            <i class="fa-solid fa-magnifying-glass text-lg"></i>
        </button>

        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Upload Button (Icon only) -->
            <a href="/upload" class="btn btn-primary btn-sm w-10 h-10 rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all duration-300 hidden sm:inline-flex items-center justify-center border-none bg-gradient-to-r from-primary to-primary-focus group tooltip tooltip-bottom" data-tip="Đăng tài liệu">
                <i class="fa-solid fa-cloud-arrow-up text-lg group-hover:animate-bounce"></i>
            </a>

            <!-- Points Display -->
            <div class="hidden md:flex items-center bg-base-200/50 rounded-xl px-3 h-10 border border-base-200 hover:border-warning/30 hover:bg-warning/5 transition-all cursor-help tooltip tooltip-bottom" data-tip="Số dư hiện tại">
                <div class="w-6 h-6 rounded-full bg-warning/20 flex items-center justify-center mr-2 text-warning">
                    <i class="fa-solid fa-coins text-xs"></i>
                </div>
                <span class="font-black text-sm tabular-nums text-base-content"><?= number_format($current_points) ?></span>
                <span class="text-[10px] font-bold text-base-content/50 ml-1">VSD</span>
                
                <a href="/shop" class="ml-2 btn btn-xs btn-circle btn-ghost text-success hover:bg-success/20" title="Nạp thêm">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                </a>
            </div>

            <!-- Notifications -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle h-10 w-10 relative hover:bg-base-200 transition-colors" onclick="markRead()">
                    <div class="indicator">
                        <i class="fa-solid fa-bell text-xl text-base-content/70"></i>
                        <span id="notif-badge" class="badge badge-primary badge-xs indicator-item hidden border-2 border-base-100 shadow-sm"></span>
                    </div>
                </div>
                <div tabindex="0" class="dropdown-content mt-4 z-[100] p-0 shadow-2xl bg-base-100 rounded-2xl w-80 sm:w-96 text-base-content border border-base-200 overflow-hidden transform origin-top-right transition-all">
                    <div class="p-4 bg-base-100/50 backdrop-blur-sm border-b border-base-200 flex justify-between items-center sticky top-0 z-10">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-base">Thông báo</span>
                            <span class="badge badge-sm badge-neutral" id="notif-count">0</span>
                        </div>
                        <button onclick="markRead()" class="text-xs font-bold text-primary hover:text-primary-focus transition-colors flex items-center gap-1">
                            <i class="fa-solid fa-check-double"></i> Đã xem
                        </button>
                    </div>
                    <ul id="notif-list" class="max-h-[28rem] overflow-y-auto custom-scrollbar custom-scrollbar-sm p-2 space-y-1">
                        <li class="p-12 text-center flex flex-col items-center gap-3 opacity-50">
                            <span class="loading loading-spinner loading-md text-primary"></span>
                            <span class="text-xs font-medium">Đang tải thông báo...</span>
                        </li>
                    </ul>
                    <div class="p-3 bg-base-200/50 border-t border-base-200 text-center">
                        <a href="/history?tab=notifications" class="text-xs font-bold text-base-content/60 hover:text-primary transition-colors flex items-center justify-center gap-2">
                            Xem tất cả <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="dropdown dropdown-end ml-1">
                <div tabindex="0" role="button" class="btn btn-ghost p-0 min-h-0 h-11 w-11 rounded-full group/avatar relative">
                    <div class="avatar h-full w-full flex items-center justify-center overflow-visible">
                        <!-- Multi-Tier Glow Ring -->
                        <div class="absolute -inset-[1px] rounded-full transition-all duration-500 <?= $is_premium ? 'bg-gradient-to-tr from-yellow-500 via-amber-200 to-yellow-600 animate-pulse-slow shadow-[0_0_10px_-3px_rgba(234,179,8,0.4)] opacity-100' : 'bg-primary/30 border border-primary/20 opacity-100' ?>"></div>
                        
                        <!-- Main Avatar Container -->
                        <div class="w-9 h-9 rounded-full bg-base-100 p-[1px] relative z-10 transition-transform duration-300 group-hover/avatar:scale-105">
                            <div class="w-full h-full rounded-full bg-primary/10 text-primary flex items-center justify-center overflow-hidden border border-white/10">
                                <?php if(!empty($user_info['avatar']) && file_exists('uploads/avatars/' . $user_info['avatar'])): ?>
                                    <img src="/uploads/avatars/<?= $user_info['avatar'] ?>" loading="lazy" alt="Avatar" class="object-cover w-full h-full" />
                                <?php else: ?>
                                    <span class="text-2xl flex items-center justify-center w-full h-full">
                                        <i class="fa-solid fa-circle-user"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Premium Crown Badge -->
                        <?php if($is_premium): ?>
                            <div class="absolute -top-1 -right-1 flex items-center justify-center bg-gradient-to-b from-yellow-300 to-yellow-600 text-black rounded-full w-5 h-5 border-2 border-base-100 shadow-md z-20 animate-bounce-slow">
                                <i class="fa-solid fa-crown text-[9px]"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-4 z-[100] p-2 shadow-2xl bg-base-100 rounded-2xl w-72 text-base-content border border-base-200 transform origin-top-right">
                    <!-- User Header -->
                    <li class="mb-2">
                        <div class="flex items-center gap-4 p-4 bg-base-200/50 rounded-xl hover:bg-base-200 transaction-colors">
                            <div class="avatar">
                                <div class="w-12 h-12 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                    <?php if(!empty($user_info['avatar'])): ?>
                                        <img src="/uploads/avatars/<?= $user_info['avatar'] ?>" />
                                    <?php else: ?>
                                        <div class="bg-neutral text-neutral-content w-full h-full flex items-center justify-center text-xl font-bold">
                                            <?= strtoupper(substr($user_info['username'] ?? 'U', 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex flex-col overflow-hidden">
                                <span class="font-bold text-base truncate"><?= htmlspecialchars($user_info['username'] ?? getCurrentUsername()) ?></span>
                                <span class="text-xs opacity-60 truncate"><?= htmlspecialchars($user_info['email'] ?? '') ?></span>
                                <?php if($is_premium): ?>
                                    <span class="badge badge-warning badge-xs mt-1 font-bold text-[9px] gap-1 px-1.5 w-fit">
                                        <i class="fa-solid fa-crown"></i> PREMIUM
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-ghost badge-xs mt-1 font-bold text-[9px] px-1.5 w-fit">FREE MEMBER</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    
                    <!-- Quick Stats (Mobile only) -->
                    <div class="grid grid-cols-2 gap-2 mb-2 px-1 md:hidden">
                        <div class="bg-base-200 rounded-lg p-2 text-center">
                            <div class="text-xs opacity-60">Điểm</div>
                            <div class="font-bold text-warning"><?= number_format($current_points) ?></div>
                        </div>
                        <a href="/upload" class="bg-primary/10 rounded-lg p-2 text-center text-primary hover:bg-primary/20">
                            <div class="text-xs font-bold">Đăng mới</div>
                            <div class="text-[10px] opacity-60"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        </a>
                    </div>
                    
                    <li><a href="/profile" class="py-3 font-medium hover:text-primary active:bg-primary/10 active:text-primary rounded-xl">
                        <i class="fa-regular fa-id-card w-5 text-center"></i> Thông tin cá nhân
                    </a></li>
                    <li><a href="/saved" class="py-3 font-medium hover:text-primary active:bg-primary/10 active:text-primary rounded-xl">
                        <i class="fa-regular fa-bookmark w-5 text-center"></i> Tài liệu đã lưu
                    </a></li>
                    <li><a href="/shop" class="py-3 font-medium hover:text-primary active:bg-primary/10 active:text-primary rounded-xl">
                        <i class="fa-solid fa-coins w-5 text-center text-warning"></i> Nạp điểm VSD
                    </a></li>
                    <li><a href="/premium" class="py-3 font-medium hover:text-primary active:bg-primary/10 active:text-primary rounded-xl">
                        <i class="fa-solid fa-crown w-5 text-center text-yellow-500"></i> Nâng cấp Premium
                    </a></li>
                    
                    <?php if($has_admin): ?>
                        <div class="divider my-1"></div>
                        <li><a href="/admin" class="py-3 font-bold text-error hover:bg-error/10 hover:text-error rounded-xl">
                            <i class="fa-solid fa-user-shield w-5 text-center"></i> Trang quản trị
                        </a></li>
                    <?php endif; ?>
                    
                    <div class="divider my-1"></div>
                    <li><a href="/logout" class="py-3 font-medium text-base-content/60 hover:bg-base-200 hover:text-base-content rounded-xl">
                        <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Đăng xuất
                    </a></li>
                </ul>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2">
                <a href="/login" class="btn btn-ghost btn-sm rounded-xl font-bold hover:bg-base-200">
                    Đăng nhập
                </a>
                <a href="/signup" class="btn btn-primary btn-sm rounded-xl font-bold shadow-lg shadow-primary/20 hover:shadow-primary/40 transition-all hidden sm:inline-flex">
                    Đăng ký <i class="fa-solid fa-arrow-right ml-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Search Shortcuts & Logic
    document.addEventListener('keydown', (e) => {
        // Press '/' to focus search
        if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            document.getElementById('searchInput')?.focus();
        }
    });

    // Autocomplete search suggestions
    let searchTimeout = null;
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');

    if (searchInput && searchSuggestions) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const keyword = this.value.trim();

            if (keyword.length < 2) {
                searchSuggestions.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                // Show loading state/placeholder
                // searchSuggestions.classList.remove('hidden');
                // searchSuggestions.innerHTML = '<div class="p-4 text-center text-xs opacity-50">Đang tìm...</div>';

                fetch(`/handler/search_suggestions.php?q=${encodeURIComponent(keyword)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.suggestions.length > 0) {
                            displaySuggestions(data.suggestions);
                            searchSuggestions.classList.remove('hidden');
                        } else {
                            searchSuggestions.classList.add('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Autocomplete error:', error);
                        searchSuggestions.classList.add('hidden');
                    });
            }, 300);
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(event) {
            if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                searchSuggestions.classList.add('hidden');
            }
        });
    }

    function displaySuggestions(suggestions) {
        searchSuggestions.innerHTML = suggestions.map(s => `
            <a href="/search?q=${encodeURIComponent(s.keyword)}" class="group block px-4 py-3 hover:bg-base-200/50 transition-colors border-b last:border-0 border-base-200/50">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-base-200 flex items-center justify-center text-base-content/50 group-hover:bg-primary group-hover:text-primary-content transition-colors">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-sm font-medium text-base-content group-hover:text-primary transition-colors truncate">${s.keyword}</span>
                        ${s.search_count ? `<span class="text-[10px] opacity-40 font-bold uppercase tracking-wider">Top tìm kiếm</span>` : ''}
                    </div>
                    <div class="ml-auto opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all">
                        <i class="fa-solid fa-arrow-right text-xs text-primary"></i>
                    </div>
                </div>
            </a>
        `).join('');
        
        // Add "View all" link at bottom
        searchSuggestions.innerHTML += `
            <a href="/search?q=${encodeURIComponent(searchInput.value)}" class="block px-4 py-2 bg-base-200/30 text-center text-xs font-bold text-primary hover:bg-base-200 transition-colors">
                Xem tất cả kết quả cho "${searchInput.value}"
            </a>
        `;
    }
    
    // Mobile Search Toggle Logic
    function toggleMobileSearch() {
        const overlay = document.getElementById('mobileSearchOverlay');
        const input = overlay.querySelector('input');
        if (overlay.classList.contains('hidden')) {
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.add('active');
                input.focus();
            }, 10);
        } else {
            overlay.classList.remove('active');
            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
        }
    }

    function closeMobileSearch() {
        const overlay = document.getElementById('mobileSearchOverlay');
        overlay.classList.remove('active');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300);
    }

    // Smooth Navbar on scroll
    window.addEventListener('scroll', () => {
        const nav = document.getElementById('main-navbar');
        if(window.scrollY > 10) {
            nav.classList.add('shadow-md', 'bg-base-100/90');
            nav.classList.remove('shadow-sm', 'bg-base-100/80');
        } else {
            nav.classList.remove('shadow-md', 'bg-base-100/90');
            nav.classList.add('shadow-sm', 'bg-base-100/80');
        }
    });
</script>

<!-- Mobile Search Overlay UI -->
<div id="mobileSearchOverlay" class="fixed inset-0 z-[200] bg-base-100 hidden opacity-0 transition-opacity duration-300 [&.active]:opacity-100">
    <div class="flex flex-col h-full">
        <div class="p-4 border-b border-base-200 flex items-center gap-3 bg-base-100">
            <button onclick="closeMobileSearch()" class="btn btn-ghost btn-circle btn-sm">
                <i class="fa-solid fa-arrow-left text-lg"></i>
            </button>
            <form action="/search" method="GET" class="flex-1">
                <div class="relative">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Tìm kiếm tài liệu..." 
                        class="input input-bordered w-full rounded-2xl bg-base-200 border-none h-11 focus:bg-base-100 focus:ring-2 focus:ring-primary/20 transition-all pr-12"
                        autocomplete="off"
                    >
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-primary font-bold">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="flex-1 overflow-y-auto p-4 bg-base-200/20">
            <div class="text-xs font-black uppercase tracking-widest opacity-30 mb-4 px-2">Tìm kiếm gần đây</div>
            <!-- Recent searches could be injected here -->
            <div class="space-y-1">
                <div class="p-4 text-center opacity-40 text-sm">Nhập từ khóa để bắt đầu tìm kiếm</div>
            </div>
        </div>
    </div>
</div>

<style>
    #mobileSearchOverlay {
        transition-property: opacity, visibility;
    }
    #mobileSearchOverlay.active {
        visibility: visible;
    }
</style>
