<?php
require_once 'config.php';
require_once 'functions.php';

// যদি ইউজার আগে থেকেই লগইন করা থাকে
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email'], $conn);
    $password = $_POST['password'];

    // ইমেইল দিয়ে ডাটাবেসে ইউজার খোঁজা
    $sql = "SELECT id, username, password, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        // পাসওয়ার্ড ভেরিফাই করা (হ্যাশ মিলানো)
        if (password_verify($password, $user['password'])) {
            // লগইন সেশন ক্রিয়েট করা
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // সাকসেসফুল হলে হোমপেজে পাঠানো
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No user found with this email!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Prediction Web</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .auth-card { background: #1e293b; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); width: 100%; max-width: 400px; }
        .auth-card h2 { text-align: center; margin-bottom: 20px; color: #e2e8f0; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #94a3b8; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; outline: none; box-sizing: border-box; }
        .input-group input:focus { border-color: #3b82f6; }
        .btn { width: 100%; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; }
        .btn:hover { background: #2563eb; }
        .msg { text-align: center; margin-bottom: 15px; font-size: 14px; }
        .error { color: #ef4444; }
        .link { text-align: center; margin-top: 15px; font-size: 14px; }
        .link a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-card">
        <h2>Welcome Back</h2>
        <?php if($error) echo "<div class='msg error'>$error</div>"; ?>
        
        <form action="" method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>