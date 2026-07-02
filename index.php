<?php
// index.php (Login Page)
session_start();
require 'config.php';

// If the user is already logged in, send them straight to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Query the USERS table safely using prepared statements
        $stmt = $conn->prepare("SELECT User_ID, Password FROM USERS WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Check password. 
            // NOTE: password_verify() is standard, but I added a direct comparison 
            // as a fallback in case you stored passwords as plain text for this project.
            if (password_verify($password, $row['Password']) || $password === $row['Password']) {
                
                // Store user details in the session
                $_SESSION['user_id'] = $row['User_ID'];
                $_SESSION['username'] = $username;
                
                // Redirect to the search portal
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Username not found.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LyricalLens</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-card h2 { text-align: center; color: #2c3e50; margin-top: 0; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; color: #34495e; display: block; margin-bottom: 8px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        button { background-color: #3498db; color: white; border: none; padding: 12px; width: 100%; font-size: 1.1em; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; }
        button:hover { background-color: #2980b9; }
        .error-msg { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>LyricalLens Login</h2>
    
    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="index.php" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required placeholder="Enter your username">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="Enter your password">
        </div>
        
        <button type="submit">Access Database</button>
    </form>
    <div style="text-align: center; margin-top: 20px;">
        <a href="register.php" style="color: #7f8c8d; text-decoration: none; font-size: 0.9em;">Don't have an account? Register here.</a>
    </div>
</div>
</div>

</body>
</html>