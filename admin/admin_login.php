<?php
// কনফিগ ফাইলে অলরেডি session_start() করা আছে
require_once '../config.php';
require_once '../functions.php';

// যদি অলরেডি অ্যাডমিন হিসেবে লগইন করা থাকে, তবে ড্যাশবোর্ডে পাঠিয়ে দেবে
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email'], $conn);
    $password = $_POST['password'];

    // ইমেইল দিয়ে ইউজার খোঁজা
    $sql = "SELECT id, username, password, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // পাসওয়ার্ড ভেরিফাই করা
        if (password_verify($password, $user['password'])) {
            
            // সিকিউরিটি চেক: ইউজার কি আসলেই অ্যাডমিন?
            if ($user['role'] === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // সব ঠিক থাকলে অ্যাডমিন ড্যাশবোর্ডে রিডাইরেক্ট
                header("Location: index.php");
                exit();
            } else {
                $error = "Access Denied! You do not have administrator privileges.";
            }
            
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No admin account found with this email!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- Base Setup --- */
        :root {
            --bg-main: #0B0E14;
            --bg-card: #151A22;
            --accent-primary: #FF3C3C; /* Red accent for Admin */
            --accent-hover: #D32F2F;
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
            background-image: radial-gradient(circle at 50% -20%, #2a1111, var(--bg-main) 60%);
        }

        /* --- Login Card --- */
        .auth-card { 
            background: var(--bg-card); 
            padding: 40px; 
            border-radius: 16px; 
            border: 1px solid var(--border-color); 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        /* Top Red Line indicating secure zone */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), #FF8A8A);
        }

        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header i { font-size: 40px; color: var(--accent-primary); margin-bottom: 10px; }
        .auth-header h2 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .auth-header p { margin: 5px 0 0; color: var(--text-muted); font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }

        /* --- Inputs --- */
        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-muted); }
        
        .input-wrapper { position: relative; }
        .input-wrapper i { 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #4B5563; 
            font-size: 16px; 
            transition: 0.3s;
        }
        .input-wrapper input { 
            width: 100%; 
            padding: 14px 14px 14px 45px; 
            background: #0B0E14; 
            border: 1px solid var(--border-color); 
            border-radius: 8px; 
            color: var(--text-main); 
            font-size: 15px; 
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
            outline: none;
            transition: all 0.3s ease; 
        }
        .input-wrapper input:focus { border-color: var(--accent-primary); box-shadow: 0 0 10px rgba(255, 60, 60, 0.1); }
        .input-wrapper input:focus + i { color: var(--accent-primary); }

        /* --- Button & Messages --- */
        .btn { 
            width: 100%; 
            padding: 14px; 
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s; 
            margin-top: 10px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 60, 60, 0.3); }

        .error-msg { 
            background: rgba(255, 60, 60, 0.1); 
            color: #FF3C3C; 
            padding: 12px; 
            border-radius: 8px; 
            text-align: center; 
            margin-bottom: 20px; 
            font-size: 14px; 
            border: 1px solid rgba(255, 60, 60, 0.2); 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .back-link { 
            display: block; 
            text-align: center; 
            margin-top: 25px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600;
            transition: 0.3s;
        }
        .back-link:hover { color: var(--text-main); }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <i class="fa-solid fa-shield-halved"></i>
            <h2>Admin Portal</h2>
            <p>Authorized Personnel Only</p>
        </div>

        <?php if($error): ?>
            <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Admin Email</label>
                <div class="input-wrapper">
                    <input type="email" name="email" required placeholder="admin@predx.com">
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

            <button type="submit" class="btn">Authenticate <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left: 5px;"></i></button>
        </form>

        <a href="../index.php" class="back-link"><i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i> Return to Main Website</a>
    </div>

</body>
</html>