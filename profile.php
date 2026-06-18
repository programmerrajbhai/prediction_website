<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// লগইন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// পাসওয়ার্ড পরিবর্তন লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // ইউজারের বর্তমান পাসওয়ার্ড আনা
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();

    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                if ($update_stmt->execute()) {
                    $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!</div>";
                } else {
                    $msg = "<div class='error-msg'>সিস্টেম এরর! আবার চেষ্টা করুন।</div>";
                }
            } else {
                $msg = "<div class='error-msg'>নতুন পাসওয়ার্ড অন্তত ৬ অক্ষরের হতে হবে!</div>";
            }
        } else {
            $msg = "<div class='error-msg'>নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না!</div>";
        }
    } else {
        $msg = "<div class='error-msg'>আপনার বর্তমান পাসওয়ার্ড ভুল!</div>";
    }
}

// ইউজারের বেসিক ইনফো আনা
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

$balance = $user['balance'];
$user_role = $user['role'];

// ইউজারের বেটিং স্ট্যাটিস্টিক্স (Stats) আনা
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(id) as total_bets,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_bets,
        SUM(CASE WHEN status = 'won' THEN points_earned ELSE 0 END) as total_earned
    FROM predictions 
    WHERE user_id = ?
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$total_bets = $stats['total_bets'] ? $stats['total_bets'] : 0;
$won_bets = $stats['won_bets'] ? $stats['won_bets'] : 0;
$total_earned = $stats['total_earned'] ? $stats['total_earned'] : 0;
$win_rate = ($total_bets > 0) ? round(($won_bets / $total_bets) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>আমার প্রোফাইল - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-primary: #00E701; --accent-hover: #00C801; --accent-secondary: #007BFF; 
            --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38;
        }

        body { 
            background-color: var(--bg-main); color: var(--text-main); 
            font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding: 0; 
            background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%);
            min-height: 100vh; overflow-x: hidden; -webkit-font-smoothing: antialiased;
        }

        /* Top Navbar */
        .navbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; }
        .navbar .logo { font-size: 26px; font-weight: 800; color: var(--text-main); text-decoration: none; display: flex; align-items: center; gap: 8px; font-family: 'Inter', sans-serif; }
        .navbar .logo span { color: var(--accent-primary); }
        .navbar .nav-right { display: flex; align-items: center; gap: 12px; }
        .balance-badge { background: rgba(0, 231, 1, 0.1); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 800; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); display: flex; align-items: center; gap: 6px; font-family: 'Inter', sans-serif;}
        .menu-trigger { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.3s; }

        /* Main Container */
        .profile-container { max-width: 800px; margin: 40px auto; padding: 0 15px; animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Profile Header Card */
        .profile-header { background: linear-gradient(145deg, var(--bg-card), #0D1117); border-radius: 20px; border: 1px solid var(--border-color); padding: 30px; text-align: center; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); margin-bottom: 30px;}
        .profile-header::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 8px; background: linear-gradient(90deg, var(--accent-secondary), var(--accent-primary)); }
        
        .avatar { width: 90px; height: 90px; background: var(--bg-main); border: 3px solid var(--accent-primary); border-radius: 50%; margin: 0 auto 15px; display: flex; justify-content: center; align-items: center; font-size: 36px; font-weight: 800; color: var(--text-main); font-family: 'Inter', sans-serif; box-shadow: 0 0 20px rgba(0,231,1,0.2); }
        .username { font-size: 24px; font-weight: 800; margin: 0 0 5px; color: var(--text-main); font-family: 'Inter', sans-serif; letter-spacing: -0.5px;}
        .email { font-size: 14px; color: var(--text-muted); margin: 0 0 15px; }
        
        .role-badge { display: inline-block; background: rgba(0, 123, 255, 0.1); color: var(--accent-secondary); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; border: 1px solid rgba(0, 123, 255, 0.3); letter-spacing: 1px;}
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-box { background: var(--bg-card); border: 1px solid var(--border-color); padding: 20px; border-radius: 16px; text-align: center; transition: 0.3s; }
        .stat-box:hover { border-color: var(--accent-primary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,231,1,0.1);}
        .stat-icon { font-size: 24px; color: var(--accent-primary); margin-bottom: 10px; }
        .stat-value { font-size: 22px; font-weight: 800; color: var(--text-main); font-family: 'Inter', sans-serif; margin-bottom: 5px;}
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        /* Settings Card */
        .settings-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); padding: 25px; margin-bottom: 30px; }
        .settings-header { font-size: 18px; font-weight: 800; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;}
        .settings-header i { color: var(--text-muted); }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 13px; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); transition: 0.3s; }
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; border: 1px solid var(--border-color); border-radius: 8px; background: #0B0E14; color: var(--text-main); font-size: 14px; font-family: 'Inter', sans-serif; outline: none; transition: 0.3s; box-sizing: border-box; }
        .input-wrapper input:focus { border-color: var(--accent-primary); box-shadow: 0 0 10px rgba(0, 231, 1, 0.1); }
        .input-wrapper input:focus + i { color: var(--accent-primary); }

        .btn { width: 100%; padding: 14px; background: linear-gradient(90deg, var(--accent-secondary), #0056b3); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 800; text-transform: uppercase; transition: 0.3s; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);}
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4); }

        /* Messages */
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(0, 231, 1, 0.2); }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(255, 60, 60, 0.2); }

        /* Mobile */
        @media (max-width: 600px) {
            .navbar { padding: 15px; }
            .navbar .nav-right .menu-trigger { display: none; }
            .stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .stat-box { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; text-align: left;}
            .stat-icon { margin-bottom: 0; }
            .stat-value { font-size: 20px; margin-bottom: 0;}
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">
            <i class="fa-solid fa-bolt"></i> Pred<span>X</span>
        </a>
        
        <div class="nav-right">
            <div class="balance-badge">
                <i class="fa-solid fa-coins" style="color: #F59E0B;"></i> <span style="margin-top: 2px;"><?php echo number_format($balance, 2); ?></span>
            </div>
            <button class="menu-trigger" id="openMenuBtn" style="display: <?php echo (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile/', $_SERVER['HTTP_USER_AGENT'])) ? 'none' : 'flex'; ?>;">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="profile-container">
        
        <div class="profile-header">
            <div class="avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
            <h2 class="username"><?php echo htmlspecialchars($user['username']); ?></h2>
            <p class="email"><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            <div class="role-badge"><?php echo ($user_role == 'admin') ? 'Super Admin' : 'Verified Member'; ?></div>
            <p style="margin-top: 15px; font-size: 12px; color: var(--text-muted);"><i class="fa-regular fa-calendar"></i> জয়েন করেছেন: <?php echo date("d M, Y", strtotime($user['created_at'])); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-chart-pie" style="color: #007BFF;"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_bets; ?></div>
                    <div class="stat-label">মোট প্রেডিকশন</div>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-trophy" style="color: #F59E0B;"></i></div>
                <div>
                    <div class="stat-value"><?php echo $won_bets; ?> <span style="font-size:12px; color:var(--text-muted);">/ <?php echo $win_rate; ?>%</span></div>
                    <div class="stat-label">প্রেডিকশন জয়</div>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-coins" style="color: #00E701;"></i></div>
                <div>
                    <div class="stat-value"><?php echo number_format($total_earned, 0); ?></div>
                    <div class="stat-label">মোট আয় (কয়েন)</div>
                </div>
            </div>
        </div>

        <?php echo $msg; ?>

        <div class="settings-card">
            <div class="settings-header">
                <i class="fa-solid fa-shield-halved"></i> অ্যাকাউন্ট নিরাপত্তা
            </div>

            <form action="profile.php" method="POST">
                <div class="input-group">
                    <label>বর্তমান পাসওয়ার্ড</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="current_password" required placeholder="আপনার বর্তমান পাসওয়ার্ড দিন">
                    </div>
                </div>
                
                <div class="input-group">
                    <label>নতুন পাসওয়ার্ড</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-key"></i>
                        <input type="password" name="new_password" required placeholder="অন্তত ৬ অক্ষরের নতুন পাসওয়ার্ড">
                    </div>
                </div>
                
                <div class="input-group">
                    <label>কনফার্ম নতুন পাসওয়ার্ড</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-circle-check"></i>
                        <input type="password" name="confirm_password" required placeholder="নতুন পাসওয়ার্ডটি পুনরায় লিখুন">
                    </div>
                </div>

                <button type="submit" name="change_password" class="btn">পাসওয়ার্ড আপডেট করুন <i class="fa-solid fa-arrow-right-long" style="margin-left: 5px;"></i></button>
            </form>
        </div>

    </div>

    <?php include 'bottom_nav.php'; ?>

</body>
</html>