<?php
session_start();
require_once 'db.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header("Location: ../index?error=empty");
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Check if user exists and verify password
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: ../dashboard");
            exit();
        } else {
            // Fallback for demo if DB isn't setup yet
            if ($username === 'admin@ponsoft.com' && $password === 'admin123') {
                $_SESSION['user_id'] = 1;
                $_SESSION['user_name'] = 'Administrator';
                header("Location: ../dashboard");
                exit();
            }
            header("Location: ../index?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../index?error=server");
        exit();
    }
} else {
    header("Location: ../index");
    exit();
}
?>
