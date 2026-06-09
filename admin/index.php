<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক: শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ওয়েবসাইটের স্ট্যাটিসটিক্স (Statistics) বের করার জন্য কোয়েরি
$stats = [
    'total_users' => 0,
    'pending_deposits' => 0,
    'pending_withdrawals' => 0,
    'active_matches' => 0
];

// ১. টোটাল ইউজার
$res = $conn->query("SELECT COUNT(id) as count FROM users WHERE role = 'user'");
if($res) $stats['total_users'] = $res->fetch_assoc()['count'];

// ২. পেন্ডিং ডিপোজিট রিকোয়েস্ট
$res = $conn->query("SELECT COUNT(id) as count FROM deposits WHERE status = 'pending'");
if($res) $stats['pending_deposits'] = $res->fetch_assoc()['count'];

// ৩. পেন্ডিং উইথড্র রিকোয়েস্ট
$res = $conn->query("SELECT COUNT(id) as count FROM withdrawals WHERE status = 'pending'");
if($res) $stats['pending_withdrawals'] = $res->fetch_assoc()['count'];

// ৪. অ্যাক্টিভ ম্যাচ (Upcoming + Live)
$res = $conn->query("SELECT COUNT(id) as count FROM matches WHERE status != 'finished'");
if($res) $stats['active_matches'] = $res->fetch_assoc()['count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Prediction Web</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
        
        /* Navbar */
        .navbar { background-color: #1e293b; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-bottom: 1px solid #334155; }
        .navbar .logo { font-size: 24px; font-weight: bold; color: #ef4444; text-decoration: none; }
        .navbar .nav-right { display: flex; align-items: center; gap: 20px; }
        .btn-outline { padding: 8px 16px; border: 1px solid #ef4444; color: #ef4444; border-radius: 6px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .btn-outline:hover { background-color: #ef4444; color: white; }
        
        .main-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .welcome-text { font-size: 24px; margin-bottom: 30px; color: #cbd5e1; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: #1e293b; padding: 25px; border-radius: 12px; border: 1px solid #334155; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.2); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); border-color: #3b82f6; }
        .stat-card h3 { margin: 0; font-size: 36px; color: #e2e8f0; }
        .stat-card p { margin: 5px 0 0; color: #94a3b8; font-size: 14px; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; }
        
        .color-blue { color: #3b82f6 !important; }
        .color-green { color: #22c55e !important; }
        .color-yellow { color: #fbbf24 !important; }
        .color-purple { color: #a855f7 !important; }

        /* Quick Links Grid */
        h2 { border-bottom: 2px solid #334155; padding-bottom: 10px; color: #3b82f6; margin-bottom: 20px; }
        .links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .link-card { background: #0f172a; border: 1px solid #334155; padding: 20px; border-radius: 8px; text-decoration: none; color: #e2e8f0; font-weight: bold; display: flex; align-items: center; justify-content: space-between; transition: 0.3s; }
        .link-card:hover { background: #334155; }
        .badge { background: #ef4444; color: white; padding: 2px 8px; border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php" class="logo">Admin Panel</a>
        <div class="nav-right">
            <span>Hi, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> 👑</span>
            <a href="../logout.php" class="btn-outline">Logout</a>
        </div>
    </div>

    <div class="main-container">
        <div class="welcome-text">Dashboard Overview</div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3 class="color-blue"><?php echo $stats['total_users']; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3 class="color-yellow"><?php echo $stats['pending_deposits']; ?></h3>
                <p>Pending Deposits</p>
            </div>
            <div class="stat-card">
                <h3 class="color-green"><?php echo $stats['pending_withdrawals']; ?></h3>
                <p>Pending Withdraws</p>
            </div>
            <div class="stat-card">
                <h3 class="color-purple"><?php echo $stats['active_matches']; ?></h3>
                <p>Active Matches</p>
            </div>
        </div>

        <h2>⚡ Quick Actions</h2>
        <div class="links-grid">
            <a href="manage_matches.php" class="link-card">
                <span>🗓️ Schedule New Match</span>
                <span>→</span>
            </a>
            
            <a href="update_result.php" class="link-card">
                <span>🎯 Update Match Results</span>
                <span>→</span>
            </a>

            <a href="approve_deposits.php" class="link-card">
                <span>💰 Approve Deposits</span>
                <?php if($stats['pending_deposits'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending_deposits']; ?> New</span>
                <?php else: ?>
                    <span>→</span>
                <?php endif; ?>
            </a>

            <a href="approve_withdraws.php" class="link-card">
                <span>💸 Approve Withdrawals</span>
                <?php if($stats['pending_withdrawals'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending_withdrawals']; ?> New</span>
                <?php else: ?>
                    <span>→</span>
                <?php endif; ?>
            </a>
            
            <a href="../index.php" class="link-card" style="border-color: #3b82f6;">
                <span style="color: #3b82f6;">🌍 View Main Website</span>
                <span style="color: #3b82f6;">→</span>
            </a>
        </div>

    </div>

</body>
</html>