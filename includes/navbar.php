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

<div class="navbar bg-primary text-primary-content shadow-lg">
    <div class="flex-none">
        <label for="drawer-toggle" class="btn btn-square btn-ghost lg:hidden">
            <i class="fa-regular fa-bars"></i>
        </label>
    </div>
    
    <div class="flex-none">
        <a href="dashboard.php" class="btn btn-ghost text-xl">
            <i class="fa-regular fa-file-lines mr-2"></i>
            DocShare
        </a>
    </div>
    
    <!-- Search Box -->
    <div class="flex-1 max-w-md mx-auto px-2">
        <form action="search.php" method="GET" class="flex gap-2 w-full">
            <input 
                type="text" 
                name="q" 
                placeholder="Tìm kiếm tài liệu..." 
                class="input input-bordered flex-1 bg-base-100 text-base-content"
                id="searchInput"
                autocomplete="off"
            />
            <button type="submit" class="btn btn-square">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>
        <!-- Autocomplete suggestions -->
        <div class="dropdown-content bg-base-100 shadow-lg rounded-box w-full mt-2 z-50 hidden" id="searchSuggestions"></div>
    </div>
    
    <div class="flex-none gap-1 sm:gap-2 items-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Points Display -->
            <!-- <?php if($user_points): ?>
                <div class="badge badge-secondary gap-1.5 px-2 sm:px-3 py-2 text-xs sm:text-sm">
                    <i class="fa-regular fa-sack-dollar"></i>
                    <span class="hidden sm:inline"><?= number_format($user_points['current_points']) ?></span>
                    <span class="sm:hidden"><?= number_format($user_points['current_points']) ?></span>
                </div>
            <?php endif; ?> -->
            
            <!-- Premium Badge -->
            <!-- <?php if($is_premium): ?>
                <div class="badge badge-warning gap-1.5 px-2 sm:px-3 py-2 text-xs sm:text-sm">
                    <i class="fa-regular fa-star"></i>
                    <span class="hidden sm:inline">Premium</span>
                </div>
            <?php endif; ?> -->
            
            <!-- Profile Dropdown -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar rounded-full bg-secondary text-secondary-content grid place-items-center">
                    <i class="fa-regular fa-user text-xl"></i>
                </div>
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow-lg bg-base-100 rounded-box w-52 text-base-content">
                    <li class="menu-title flex flex-col items-start gap-1">
                        <span><?= htmlspecialchars($user_info['username'] ?? getCurrentUsername()) ?></span>
                        <span class="text-xs text-base-content/70 font-normal">
                            <?= htmlspecialchars($user_info['email'] ?? '') ?>
                        </span>
                        <hr class="border-t border-base-300 w-full mt-2 mb-0">
                    </li>
                    <li><a href="saved.php">
                        <i class="fa-regular fa-bookmark"></i>
                        Saved
                    </a></li>
                    <li><a href="profile.php">
                        <i class="fa-regular fa-user"></i>
                        Profile
                    </a></li>
                    <li><a href="premium.php">
                        <i class="fa-regular fa-star"></i>
                        Premium
                    </a></li>
                    <?php if($has_admin): ?>
                        <li><a href="admin/index.php">
                            <i class="fa-regular fa-screwdriver-wrench"></i>
                            Admin
                        </a></li>
                    <?php endif; ?>
                    <li><hr class="my-2"></li>
                    <li><a href="logout.php" class="text-error">
                        <i class="fa-regular fa-right-from-bracket"></i>
                        Logout
                    </a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="index.php" class="btn btn-ghost">
                <i class="fa-regular fa-lock"></i>
                Login
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
            <a href="search.php?q=${encodeURIComponent(s.keyword)}" class="block px-4 py-2 hover:bg-base-200 rounded-box">
                <div class="flex items-center gap-2">
                    <i class="fa-regular fa-magnifying-glass"></i>
                    <span>${s.keyword}</span>
                    ${s.search_count ? `<span class="ml-auto text-xs opacity-70">${s.search_count} lần</span>` : ''}
                </div>
            </a>
        `).join('');
    }
</script>
