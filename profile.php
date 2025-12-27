<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/premium.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$user = getUserInfo($user_id);
$is_premium = isPremium($user_id);
$premium_info = getPremiumInfo($user_id);

// Calculate days remaining for Premium
$days_remaining = null;
$hours_remaining = null;
$show_countdown = false;

if($is_premium && $premium_info) {
    $end_date = new DateTime($premium_info['end_date']);
    $now = new DateTime();
    $interval = $now->diff($end_date);
    
    $days_remaining = $interval->days;
    $hours_remaining = $interval->h;
    
    // Show countdown if less than 7 days
    if($days_remaining < 7) {
        $show_countdown = true;
    }
}

$page_title = "Profile - DocShare";
$current_page = 'profile';

$error = '';
$success = '';

// Handle profile update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    
    if(strlen($username) < 3) {
        $error = "Username must be at least 3 characters";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif(updateUserProfile($user_id, $username, $email)) {
        $success = "Profile updated successfully";
        $user = getUserInfo($user_id);
    } else {
        $error = "Failed to update profile";
    }
}

// Handle password change
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if(empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif(strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif(changePassword($user_id, $old_password, $new_password)) {
        $success = "Password changed successfully";
    } else {
        $error = "Old password is incorrect";
    }
}

// Count user's documents
$my_docs_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM documents WHERE user_id=$user_id"));
$saved_docs_count = mysqli_num_rows(mysqli_query($conn, "SELECT DISTINCT d.id FROM documents d JOIN document_interactions di ON d.id = di.document_id WHERE di.user_id=$user_id AND di.type='save'"));

?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <h1 class="text-3xl font-bold text-primary mb-6 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            Profile Settings
        </h1>

        <?php if($error): ?>
            <div class="alert alert-error mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Info Card -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-base-300">
                    <div class="avatar placeholder">
                        <div class="bg-primary text-primary-content rounded-full w-20 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold"><?= htmlspecialchars($user['username']) ?></h2>
                        <p class="text-base-content/70 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <?= htmlspecialchars($user['email']) ?>
                        </p>
                        <p class="text-sm text-base-content/50">Joined: <?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="stats stats-vertical lg:stats-horizontal shadow w-full">
                    <div class="stat">
                        <div class="stat-value text-primary"><?= $my_docs_count ?></div>
                        <div class="stat-title">Documents Uploaded</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value text-primary"><?= $saved_docs_count ?></div>
                        <div class="stat-title">Documents Saved</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value <?= $is_premium ? 'text-success' : 'text-error' ?>">
                            <?= $is_premium ? '✓' : '✗' ?>
                        </div>
                        <div class="stat-title">Premium Status</div>
                    </div>
                </div>

        <!-- Premium Countdown (if less than 7 days) -->
        <?php if($show_countdown): ?>
        <div style="background: linear-gradient(135deg, #ffd700, #ffed4e); padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ff9800;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div>
                    <h3 style="color: #333; font-size: 16px; margin-bottom: 5px;" class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Premium Expiring Soon
                    </h3>
                    <p style="color: #555; font-size: 13px;">Your Premium membership will expire in:</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px;">
                <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #ff9800;">
                        <?= $days_remaining ?>
                    </div>
                    <div style="font-size: 12px; color: #999; margin-top: 5px;">Days</div>
                </div>
                <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #ff9800;">
                        <?= $hours_remaining ?>
                    </div>
                    <div style="font-size: 12px; color: #999; margin-top: 5px;">Hours</div>
                </div>
            </div>

            <p style="color: #555; font-size: 13px; margin-top: 15px; text-align: center;">
                Expires on: <strong><?= date('M d, Y H:i', strtotime($premium_info['end_date'])) ?></strong>
            </p>

            <div style="margin-top: 15px;">
                <a href="premium.php" style="display: inline-block; width: 100%; padding: 12px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; text-align: center; font-weight: 600; transition: background 0.3s;">
                    Renew Premium Now
                </a>
            </div>
        </div>
        <?php elseif($is_premium): ?>
        <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745; color: #155724;">
            <p style="font-size: 14px; margin: 0;" class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Your Premium membership is active and will expire on <strong><?= date('M d, Y', strtotime($premium_info['end_date'])) ?></strong>
            </p>
        </div>
        <?php else: ?>
        <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
            <p style="font-size: 14px; color: #333; margin-bottom: 10px;">
                <span class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m-8.25 3.75h16.5" />
                    </svg>
                    You don't have Premium yet. <strong>Upload 3 documents to get 7 days free trial!</strong>
                </span>
            </p>
            <a href="premium.php" style="display: inline-block; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-weight: 600;">
                View Premium Plans
            </a>
        </div>
        <?php endif; ?>
            </div>
        </div>

        <!-- Update Profile Card -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h3 class="card-title text-primary border-b border-primary pb-2 mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Update Profile
                </h3>

                <form method="POST" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Username</span>
                        </label>
                        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                               class="input input-bordered" required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Email</span>
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                               class="input input-bordered" required>
                    </div>

                    <div class="form-control mt-6">
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h3 class="card-title text-primary border-b border-primary pb-2 mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Change Password
                </h3>

                <form method="POST" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Current Password</span>
                        </label>
                        <input type="password" name="old_password" class="input input-bordered" required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">New Password</span>
                        </label>
                        <input type="password" name="new_password" class="input input-bordered" required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Confirm New Password</span>
                        </label>
                        <input type="password" name="confirm_password" class="input input-bordered" required>
                    </div>

                    <div class="form-control mt-6">
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>

<?php 
mysqli_close($conn);
?>