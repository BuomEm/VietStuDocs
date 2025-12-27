<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
}

require_once 'config/db.php';
require_once 'config/auth.php';

$error = '';
$success = '';

// Handle Registration
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $result = registerUser($_POST['username'], $_POST['email'], $_POST['password']);
    if($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Handle Login
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    if(loginUser($email, $password)) {
        header("Location: dashboard.php");
    } else {
        $error = "Invalid email or password";
    }
}
?>
<?php include 'includes/head.php'; ?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary to-secondary p-4">
    <div class="card w-full max-w-md bg-base-100 shadow-2xl">
        <div class="card-body">
            <h1 class="card-title text-3xl justify-center mb-6 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-3-8h.01M4 6a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                </svg>
                DocShare
            </h1>
            
            <!-- Error/Success Messages -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div id="login-form" class="form-container">
                <form method="POST" class="space-y-4">
                    <div class="form-control">
                        <label class="label" for="login-email">
                            <span class="label-text">Email</span>
                        </label>
                        <input type="email" id="login-email" name="email" placeholder="Enter your email" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="login-password">
                            <span class="label-text">Password</span>
                        </label>
                        <input type="password" id="login-password" name="password" placeholder="Enter your password" class="input input-bordered" required>
                    </div>
                    <div class="form-control mt-6">
                        <button type="submit" name="login" class="btn btn-primary">Login</button>
                    </div>
                </form>
                <div class="text-center mt-4">
                    <span class="text-sm">Don't have an account? </span>
                    <a onclick="toggleForms()" class="link link-primary text-sm font-semibold">Register here</a>
                </div>
            </div>

            <!-- Register Form -->
            <div id="register-form" class="form-container hidden">
                <form method="POST" class="space-y-4">
                    <div class="form-control">
                        <label class="label" for="reg-username">
                            <span class="label-text">Username</span>
                        </label>
                        <input type="text" id="reg-username" name="username" placeholder="Enter your username" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="reg-email">
                            <span class="label-text">Email</span>
                        </label>
                        <input type="email" id="reg-email" name="email" placeholder="Enter your email" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="reg-password">
                            <span class="label-text">Password</span>
                        </label>
                        <input type="password" id="reg-password" name="password" placeholder="Enter your password" class="input input-bordered" required>
                    </div>
                    <div class="form-control">
                        <label class="label" for="reg-password-confirm">
                            <span class="label-text">Confirm Password</span>
                        </label>
                        <input type="password" id="reg-password-confirm" name="password_confirm" placeholder="Confirm your password" class="input input-bordered" required>
                    </div>
                    <div class="form-control mt-6">
                        <button type="submit" name="register" class="btn btn-primary">Register</button>
                    </div>
                </form>
                <div class="text-center mt-4">
                    <span class="text-sm">Already have an account? </span>
                    <a onclick="toggleForms()" class="link link-primary text-sm font-semibold">Login here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleForms() {
        document.getElementById('login-form').classList.toggle('hidden');
        document.getElementById('register-form').classList.toggle('hidden');
    }
</script>


