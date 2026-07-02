<?php
// register.php
session_start();
require 'config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Basic Validation
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // 2. Check if username already exists
        $checkStmt = $conn->prepare("SELECT User_ID FROM USERS WHERE Username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "That username is already taken. Please choose another.";
        } else {
            // 3. Create the new user securely
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $insertStmt = $conn->prepare("INSERT INTO USERS (Username, Password) VALUES (?, ?)");
            $insertStmt->bind_param("ss", $username, $hashedPassword);
            
            if ($insertStmt->execute()) {
                $success = "Account created successfully! You can now login.";
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LyricalLens</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-card h2 { text-align: center; color: #2c3e50; margin-top: 0; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; color: #34495e; display: block; margin-bottom: 8px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        button { background-color: #2ecc71; color: white; border: none; padding: 12px; width: 100%; font-size: 1.1em; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; }
        button:hover { background-color: #27ae60; }
        .error-msg { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #f5c6cb; }
        .success-msg { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #c3e6cb; }
        .link-text { text-align: center; margin-top: 20px; display: block; color: #7f8c8d; text-decoration: none; font-size: 0.9em; }
        .link-text:hover { color: #3498db; text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Create Account</h2>
    
    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <a href="index.php" style="display:block; text-align:center; background:#3498db; color:white; padding:10px; border-radius:4px; text-decoration:none; font-weight:bold;">Go to Login</a>
    <?php else: ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username">Choose a Username</label>
                <input type="text" id="username" name="username" required placeholder="e.g. Mamba123">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Type password again">
            </div>
            
            <button type="submit">Register</button>
        </form>
    <?php endif; ?>

    <a href="index.php" class="link-text">Already have an account? Login here.</a>
</div>

</body>
</html>