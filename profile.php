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
            <i class="fa-solid fa-user-gear text-primary text-3xl"></i>
            Profile Settings
        </h1>

        <?php if($error): ?>
            <div class="alert alert-error mb-4">
                    <i class="fa-solid fa-circle-xmark fa-lg"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success mb-4">
                    <i class="fa-solid fa-circle-check fa-lg"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Info Card -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-base-300">
                    <div class="avatar placeholder">
                        <div class="bg-primary text-primary-content rounded-full w-20 flex items-center justify-center">
                            <i class="fa-solid fa-user text-3xl text-primary-content"></i>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold"><?= htmlspecialchars($user['username']) ?></h2>
                        <p class="text-base-content/70 flex items-center gap-1">
                            <i class="fa-solid fa-envelope w-4 h-4"></i>
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
                        <i class="fa-solid fa-hourglass-half"></i>
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
                <i class="fa-solid fa-circle-check"></i>
                Your Premium membership is active and will expire on <strong><?= date('M d, Y', strtotime($premium_info['end_date'])) ?></strong>
            </p>
        </div>
        <?php else: ?>
        <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
            <p style="font-size: 14px; color: #333; margin-bottom: 10px;">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-gift"></i>
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
                    <i class="fa-solid fa-user-pen w-5 h-5"></i>
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
                    <i class="fa-solid fa-lock w-5 h-5"></i>
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