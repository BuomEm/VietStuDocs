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
$user_points = null;
$user_info = null;
if(isset($_SESSION['user_id'])) {
    $user_id = getCurrentUserId();
    $user_points = getUserPoints($user_id);
    $user_info = getUserInfo($user_id);
    $is_premium = isPremium($user_id);
}
$site_name = function_exists('getSetting') ? getSetting('site_name', 'VietStuDocs') : 'VietStuDocs';
$site_logo = function_exists('getSetting') ? getSetting('site_logo') : '';
?>

<div class="navbar bg-base-100/80 backdrop-blur border-b border-base-300 shadow-sm transition-all duration-300">
    <div class="flex-none">
        <label for="drawer-toggle" class="btn btn-square btn-ghost text-base-content">
            <i class="fa-solid fa-bars text-xl"></i>
        </label>
    </div>

    <!-- Mobile Logo -->
    <div class="flex-none lg:hidden mr-2">
        <a href="/dashboard" class="flex items-center gap-2">
            <?php if (!empty($site_logo)): ?>
                <img src="<?= htmlspecialchars($site_logo) ?>" loading="lazy" alt="Logo" class="h-8 w-8 object-contain">
            <?php else: ?>
                <i class="fa-solid fa-file-contract text-primary text-xl"></i>
            <?php endif; ?>
            <span class="font-bold text-lg text-primary truncate max-w-[120px]"><?= htmlspecialchars($site_name) ?></span>
        </a>
    </div>
    
    <!-- Search Box -->
    <div class="flex-1 px-4">
        <div class="max-w-3xl mx-auto relative group/search">
            <div class="relative">
                <form action="/search" method="GET" class="flex gap-0 w-full">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Tìm kiếm tài liệu..." 
                        class="input input-bordered w-full bg-base-100 text-base-content rounded-r-none focus:outline-none focus:border-primary transition-all"
                        id="searchInput"
                        autocomplete="off"
                    />
                    <button type="submit" class="btn btn-primary rounded-l-none border-l-0">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>
                <!-- Autocomplete suggestions -->
                <div class="absolute top-full left-0 right-0 mt-2 bg-base-100 shadow-2xl rounded-box border border-base-300 overflow-hidden z-[100] hidden" id="searchSuggestions"></div>
            </div>
        </div>
    </div>
    
    <div class="flex-none gap-1 sm:gap-2 items-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Notifications Dropdown -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle relative" onclick="markRead()">
                    <i class="fa-solid fa-bell text-xl"></i>
                    <span id="notif-badge" class="badge badge-primary badge-xs absolute top-2 right-2 hidden animate-ping"></span>
                    <span id="notif-dot" class="badge badge-primary badge-xs absolute top-2 right-2 hidden scale-75"></span>
                </div>
                <div tabindex="0" class="dropdown-content mt-3 z-[100] p-0 shadow-2xl bg-base-100 rounded-box w-80 text-base-content border border-base-300 overflow-hidden">
                    <div class="p-3 bg-base-200 flex justify-between items-center">
                        <span class="font-bold text-sm">Thông báo (<span id="notif-count">0</span>)</span>
                        <button onclick="markRead()" class="text-[10px] text-primary hover:underline">Đã xem tất cả</button>
                    </div>
                    <ul id="notif-list" class="max-h-80 overflow-y-auto divide-y divide-base-200">
                        <li class="p-8 text-center"><span class="loading loading-spinner loading-sm opacity-20"></span></li>
                    </ul>
                    <div class="p-2 border-t border-base-200 text-center">
                        <a href="/history" class="text-xs text-primary hover:underline font-medium">Xem tất cả lịch sử</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="dropdown dropdown-end">
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

                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow-xl bg-base-100 rounded-box w-64 text-base-content border border-base-300">
                    <li class="menu-title flex flex-row items-center gap-3 px-4 py-3">
                        <div class="avatar">
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden border border-primary/20">
                                <?php if(!empty($user_info['avatar']) && file_exists('uploads/avatars/' . $user_info['avatar'])): ?>
                                    <img src="/uploads/avatars/<?= $user_info['avatar'] ?>" loading="lazy" alt="Avatar" />
                                <?php else: ?>
                                    <span class="text-3xl flex items-center justify-center w-full h-full mb-0.5">
                                        <i class="fa-solid fa-circle-user text-primary leading-none"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <span class="font-bold text-base-content"><?= htmlspecialchars($user_info['username'] ?? getCurrentUsername()) ?></span>
                            <span class="text-[10px] opacity-60 font-normal truncate w-32">
                                <?= htmlspecialchars($user_info['email'] ?? '') ?>
                            </span>
                        </div>
                    </li>
                    <div class="divider my-0"></div>
                    <li><a href="/saved" class="hover:text-primary py-3">
                        <i class="fa-solid fa-bookmark text-primary"></i>
                        Đã lưu
                    </a></li>
                    <li><a href="/profile" class="hover:text-primary py-3">
                        <i class="fa-solid fa-user-gear"></i>
                        Thông tin cá nhân
                    </a></li>
                    <li><a href="/premium" class="hover:text-primary py-3">
                        <i class="fa-solid fa-crown text-warning"></i>
                        Gói Premium
                    </a></li>
                    <?php if($has_admin): ?>
                        <li><a href="/admin" class="bg-primary/5 text-primary hover:bg-primary/10 py-3">
                            <i class="fa-solid fa-user-shield"></i>
                            Quản trị viên
                        </a></li>
                    <?php endif; ?>
                    <div class="divider my-0"></div>
                    <li><a href="/logout" class="text-error font-medium hover:bg-error/10 py-3">
                        <i class="fa-solid fa-power-off"></i>
                        Đăng xuất
                    </a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="/login" class="btn btn-primary btn-sm rounded-btn px-4">
                <i class="fa-solid fa-right-to-bracket"></i>
                Đăng nhập
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
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
            <a href="/search?q=${encodeURIComponent(s.keyword)}" class="group block px-4 py-3 hover:bg-primary/5 transition-colors border-b last:border-0 border-base-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-base-200 flex items-center justify-center text-base-content/50 group-hover:bg-primary group-hover:text-primary-content transition-colors">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-base-content group-hover:text-primary transition-colors">${s.keyword}</span>
                        ${s.search_count ? `<span class="text-[10px] opacity-50 uppercase tracking-wider font-bold">Tìm kiếm ${s.search_count} lần</span>` : ''}
                    </div>
                    <i class="fa-solid fa-arrow-right ml-auto text-xs opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-primary"></i>
                </div>
            </a>
        `).join('');
    }
</script>
