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
    <title>Login - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0B0E14;
            --bg-card: #151A22;
            --accent-primary: #00E701;
            --accent-hover: #00C801;
            --text-main: #FFFFFF;
            --text-muted: #8B94A3;
            --border-color: #242B38;
        }

        body { 
            background-color: var(--bg-main); 
            color: var(--text-main); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%);
        }

        .auth-card { 
            background: rgba(21, 26, 34, 0.8); 
            backdrop-filter: blur(15px);
            padding: 40px; 
            border-radius: 16px; 
            border: 1px solid var(--border-color); 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), #007BFF);
        }

        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h2 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -1px; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .auth-header h2 i { color: var(--accent-primary); }
        .auth-header p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }

        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-muted); }
        
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #4B5563; transition: 0.3s; }
        .input-wrapper input { 
            width: 100%; padding: 14px 14px 14px 45px; background: #0B0E14; border: 1px solid var(--border-color); 
            border-radius: 8px; color: var(--text-main); font-size: 15px; font-family: 'Inter', sans-serif; box-sizing: border-box; outline: none; transition: 0.3s; 
        }
        .input-wrapper input:focus { border-color: var(--accent-primary); box-shadow: 0 0 10px rgba(0, 231, 1, 0.1); }
        .input-wrapper input:focus + i { color: var(--accent-primary); }

        .btn { 
            width: 100%; padding: 14px; background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); 
            color: #000; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 800; 
            text-transform: uppercase; transition: 0.3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(0, 231, 1, 0.2);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 231, 1, 0.4); }

        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-size: 14px; border: 1px solid rgba(255, 60, 60, 0.2); }

        .link-text { text-align: center; margin-top: 25px; font-size: 14px; color: var(--text-muted); }
        .link-text a { color: var(--accent-primary); text-decoration: none; font-weight: 600; transition: 0.3s; }
        .link-text a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <h2><i class="fa-solid fa-bolt"></i> PredX</h2>
            <p>Login to place your predictions</p>
        </div>

        <?php if($error): ?>
            <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <input type="email" name="email" required placeholder="Enter your email">
                    <i class="fa-regular fa-envelope"></i>
                </div>
            </div>
            <div class="input-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" required placeholder="••••••••">
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>
            <button type="submit" class="btn">Log In <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left:5px;"></i></button>
        </form>
        
        <div class="link-text">
            Don't have an account? <a href="register.php">Sign Up here</a>
        </div>
    </div>
</body>
</html>