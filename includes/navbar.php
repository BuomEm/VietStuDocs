<?php
// Make sure variables are defined
if(!isset($user_id)) $user_id = null;
if(!isset($is_premium)) $is_premium = false;
if(!isset($current_page)) $current_page = '';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/premium.php';
require_once __DIR__ . '/../config/db.php';

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
?>

<div class="navbar bg-base-100/80 backdrop-blur border-b border-base-300 shadow-sm transition-all duration-300">
    <div class="flex-none">
        <label for="drawer-toggle" class="btn btn-square btn-ghost lg:hidden text-base-content">
            <i class="fa-regular fa-bars"></i>
        </label>
    </div>
    
    <div class="flex-none">
        <a href="dashboard.php" class="btn btn-ghost text-xl flex items-center gap-1 group">
            <i class="fa-regular fa-file-lines text-primary text-2xl transition-transform group-hover:scale-110"></i>
            <span class="font-bold text-primary tracking-tight">DocShare</span>
        </a>
    </div>
    
    <!-- Search Box -->
    <div class="flex-1 max-w-md mx-auto px-2 relative group/search">
        <div class="relative">
            <form action="search.php" method="GET" class="flex gap-0 w-full">
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
    
    <div class="flex-none gap-1 sm:gap-2 items-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Profile Dropdown -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar rounded-full bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                    <i class="fa-regular fa-user text-xl"></i>
                </div>
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow-xl bg-base-100 rounded-box w-52 text-base-content border border-base-300">
                    <li class="menu-title flex flex-col items-start gap-1 pb-2">
                        <span class="font-bold text-primary"><?= htmlspecialchars($user_info['username'] ?? getCurrentUsername()) ?></span>
                        <span class="text-xs opacity-60 font-normal">
                            <?= htmlspecialchars($user_info['email'] ?? '') ?>
                        </span>
                    </li>
                    <div class="divider my-0"></div>
                    <li><a href="saved.php" class="hover:text-primary">
                        <i class="fa-regular fa-bookmark"></i>
                        Đã Lưu
                    </a></li>
                    <li><a href="profile.php" class="hover:text-primary">
                        <i class="fa-regular fa-user"></i>
                        Hồ Sơ
                    </a></li>
                    <li><a href="premium.php" class="hover:text-primary">
                        <i class="fa-regular fa-star text-warning"></i>
                        Premium
                    </a></li>
                    <?php if($has_admin): ?>
                        <li><a href="admin/index.php" class="bg-primary/5 text-primary hover:bg-primary/10">
                            <i class="fa-regular fa-screwdriver-wrench"></i>
                            Quản trị viên
                        </a></li>
                    <?php endif; ?>
                    <div class="divider my-0"></div>
                    <li><a href="logout.php" class="text-error font-medium hover:bg-error/10">
                        <i class="fa-regular fa-right-from-bracket"></i>
                        Đăng xuất
                    </a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="index.php" class="btn btn-primary btn-sm rounded-btn">
                <i class="fa-regular fa-lock"></i>
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
                fetch(`handler/search_suggestions.php?q=${encodeURIComponent(keyword)}`)
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
            <a href="search.php?q=${encodeURIComponent(s.keyword)}" class="group block px-4 py-3 hover:bg-primary/5 transition-colors border-b last:border-0 border-base-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-base-200 flex items-center justify-center text-base-content/50 group-hover:bg-primary group-hover:text-primary-content transition-colors">
                        <i class="fa-regular fa-magnifying-glass text-sm"></i>
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
