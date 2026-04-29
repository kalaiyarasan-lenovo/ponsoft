<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit();
}

$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Ponsoft</title>
    <meta name="description" content="Secure login portal for Ponsoft applications.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h2>Ponsoft</h2>
                <p>Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php 
                        if ($error == 'invalid') echo "Invalid email or password.";
                        else if ($error == 'empty') echo "Please fill in all fields.";
                        else echo "An error occurred. Please try again.";
                    ?>
                </div>
            <?php endif; ?>

            <form action="includes/auth.php" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>

                <button type="submit" name="login" class="btn-login">Sign In</button>
            </form>

            <div class="login-footer">
                <a href="#">Forgot password?</a>
            </div>
        </div>
    </div>
</body>
</html>
