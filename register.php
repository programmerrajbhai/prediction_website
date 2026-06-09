<?php
require_once 'config.php';
require_once 'functions.php';

// যদি ইউজার আগে থেকেই লগইন করা থাকে, তাকে ইনডেক্সে পাঠিয়ে দিবে
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitizeInput($_POST['username'], $conn);
    $email = sanitizeInput($_POST['email'], $conn);
    $password = $_POST['password']; 

    // ইমেইল বা ইউজারনেম আগে থেকে আছে কিনা চেক করা
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Username or Email already exists!";
    } else {
        // পাসওয়ার্ড এনক্রিপ্ট বা হ্যাশ করা
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // ডাটাবেসে নতুন ইউজার ইনসার্ট করা
        $insert_sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        $stmt_insert->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt_insert->execute()) {
            $success = "Registration successful! You can now <a href='login.php' style='color: #22c55e;'>Login</a>.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Prediction Web</title>
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
        .success { color: #22c55e; }
        .link { text-align: center; margin-top: 15px; font-size: 14px; }
        .link a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-card">
        <h2>Create Account</h2>
        <?php if($error) echo "<div class='msg error'>$error</div>"; ?>
        <?php if($success) echo "<div class='msg success'>$success</div>"; ?>
        
        <form action="" method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        <div class="link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>