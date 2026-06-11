<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক: শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// সময় অনুযায়ী গ্রিটিংস (Greeting)
$hour = date('H');
if ($hour < 12) {
    $greeting = 'শুভ সকাল ☀️';
} elseif ($hour < 18) {
    $greeting = 'শুভ অপরাহ্ন 🌤️';
} else {
    $greeting = 'শুভ সন্ধ্যা 🌙';
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
$res = $conn->query("SELECT COUNT(id) as count FROM matches WHERE status != 'finished' AND status != 'canceled'");
if($res) $stats['active_matches'] = $res->fetch_assoc()['count'];

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাডমিন ড্যাশবোর্ড - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-admin: #FF3C3C; --accent-admin-hover: #D32F2F; --text-main: #FFFFFF;
            --text-muted: #8B94A3; --border-color: #242B38; --sidebar-width: 280px;
        }

        body { 
            background-color: var(--bg-main); color: var(--text-main); 
            font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding: 0; display: flex; min-height: 100vh; overflow-x: hidden;
            background-image: radial-gradient(circle at 50% -20%, #2a1111, var(--bg-main) 60%);
        }

        /* --- Animations --- */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-up { animation: slideUp 0.5s ease forwards; opacity: 0; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        /* --- Sidebar --- */
        .sidebar {
            width: var(--sidebar-width); background: var(--bg-card); border-right: 1px solid var(--border-color);
            height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000;
            display: flex; flex-direction: column; transition: 0.3s ease;
        }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .sidebar-header i { font-size: 28px; color: var(--accent-admin); }
        .sidebar-header h2 { margin: 0; font-size: 24px; font-weight: 800; font-family: 'Inter', sans-serif; letter-spacing: -1px; }
        
        .nav-menu { list-style: none; padding: 20px 15px; margin: 0; flex-grow: 1; overflow-y: auto; }
        .nav-title { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 800; margin: 15px 0 10px 10px; font-family: 'Inter', sans-serif; }
        .nav-item { margin-bottom: 5px; }
        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px;
            color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.3s;
        }
        .nav-link i { width: 20px; text-align: center; font-size: 16px; }
        .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
        .nav-link.active { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-left: 3px solid var(--accent-admin); }
        
        .badge { background: var(--accent-admin); color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 11px; margin-left: auto; font-weight: 800; }
        .badge-warning { background: #F59E0B; }

        /* --- Main Content Area --- */
        .main-content {
            margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh;
            display: flex; flex-direction: column; width: calc(100% - var(--sidebar-width));
        }

        /* Topbar */
        .topbar {
            background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100;
        }
        .mobile-toggle { display: none; background: none; border: none; color: var(--text-main); font-size: 24px; cursor: pointer; }
        .admin-profile { display: flex; align-items: center; gap: 15px; }
        .admin-profile .info { text-align: right; }
        .admin-profile .name { display: block; font-weight: 800; font-size: 14px; font-family: 'Inter', sans-serif;}
        .admin-profile .role { display: block; font-size: 11px; color: var(--accent-admin); text-transform: uppercase; font-weight: 800;}
        .logout-btn { padding: 8px 15px; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .logout-btn:hover { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-color: var(--accent-admin); }

        /* Dashboard Container */
        .dashboard-container { padding: 30px; flex-grow: 1;}
        .welcome-box { margin-bottom: 30px; }
        .welcome-box h1 { margin: 0; font-size: 28px; font-weight: 800; color: var(--text-main); }
        .welcome-box p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card {
            background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color);
            display: flex; align-items: center; gap: 20px; transition: 0.3s; position: relative; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .stat-card:hover { transform: translateY(-5px); border-color: #3B465A; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 24px; }
        .stat-info h3 { margin: 0; font-size: 32px; font-weight: 800; font-family: 'Inter', sans-serif; }
        .stat-info p { margin: 0; font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Icon Colors */
        .ic-users { background: rgba(0, 123, 255, 0.1); color: #007BFF; border: 1px solid rgba(0, 123, 255, 0.2);}
        .ic-deposit { background: rgba(245, 158, 11, 0.1); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.2);}
        .ic-withdraw { background: rgba(0, 231, 1, 0.1); color: #00E701; border: 1px solid rgba(0, 231, 1, 0.2);}
        .ic-match { background: rgba(168, 85, 247, 0.1); color: #A855F7; border: 1px solid rgba(168, 85, 247, 0.2);}

        /* Quick Actions Grid */
        .section-title { font-size: 18px; font-weight: 800; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; }
        .action-card {
            background: linear-gradient(145deg, #151A22, #0F131A); border: 1px solid var(--border-color);
            padding: 20px; border-radius: 12px; text-decoration: none; color: var(--text-main);
            display: flex; align-items: center; justify-content: space-between; font-weight: 600; transition: 0.3s;
        }
        .action-card:hover { background: #1C232F; border-color: var(--accent-admin); transform: scale(1.02); }
        .action-card .left { display: flex; align-items: center; gap: 12px; }
        .action-card .left i { font-size: 20px; color: var(--text-muted); transition: 0.3s; width: 25px; text-align: center;}
        .action-card:hover .left i { color: var(--accent-admin); }

        /* Footer */
        .admin-footer { text-align: center; padding: 20px; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 12px; font-family: 'Inter', sans-serif;}

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { left: -300px; }
            .sidebar.active { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.8); }
            .main-content { margin-left: 0; width: 100%; }
            .mobile-toggle { display: block; }
        }
    </style>
</head>
<body>

    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-shield-halved"></i>
            <h2>Admin Panel</h2>
        </div>

        <ul class="nav-menu">
            <div class="nav-title">মেইন</div>
            <li class="nav-item"><a href="index.php" class="nav-link active"><i class="fa-solid fa-gauge-high"></i> ড্যাশবোর্ড</a></li>
            <li class="nav-item"><a href="../index.php" class="nav-link" target="_blank"><i class="fa-solid fa-globe"></i> মেইন ওয়েবসাইট</a></li>
            
            <div class="nav-title">ম্যানেজমেন্ট</div>
            <li class="nav-item"><a href="manage_matches.php" class="nav-link"><i class="fa-solid fa-earth-americas"></i> ম্যাচ ও দেশ ম্যানেজ</a></li>
            <li class="nav-item"><a href="update_result.php" class="nav-link"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a></li>
            <li class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> ইউজার ম্যানেজমেন্ট</a></li>

            <div class="nav-title">ফাইন্যান্স (অর্থ)</div>
            <li class="nav-item">
                <a href="approve_deposits.php" class="nav-link">
                    <i class="fa-solid fa-money-bill-transfer"></i> ডিপোজিট রিকোয়েস্ট
                    <?php if($stats['pending_deposits'] > 0): ?><span class="badge badge-warning"><?php echo $stats['pending_deposits']; ?></span><?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="approve_withdraws.php" class="nav-link">
                    <i class="fa-solid fa-hand-holding-dollar"></i> উইথড্র রিকোয়েস্ট
                    <?php if($stats['pending_withdrawals'] > 0): ?><span class="badge"><?php echo $stats['pending_withdrawals']; ?></span><?php endif; ?>
                </a>
            </li>

            <div class="nav-title">সিস্টেম</div>
            <li class="nav-item"><a href="settings.php" class="nav-link"><i class="fa-solid fa-gear"></i> গ্লোবাল সেটিংস</a></li>
        </ul>
    </aside>

    <div style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; display:none;" id="mobileOverlay"></div>

    <div class="main-content">
        
        <header class="topbar">
            <button class="mobile-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <div style="flex-grow: 1;"></div>
            
            <div class="admin-profile">
                <div class="info" style="display: <?php echo (window.innerWidth <= 600) ? 'none' : 'block'; ?>;">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="role">Super Admin</span>
                </div>
                <div style="width: 40px; height: 40px; background: var(--accent-admin); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-family: Inter;">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <a href="../logout.php" class="logout-btn" style="margin-left: 10px;"><i class="fa-solid fa-arrow-right-from-bracket"></i> লগআউট</a>
            </div>
        </header>

        <div class="dashboard-container">
            <div class="welcome-box animate-up">
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
                <p>PredX সিস্টেমের বর্তমান অবস্থা এবং ইউজার রিকোয়েস্ট একনজরে দেখে নিন।</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card animate-up delay-1">
                    <div class="stat-icon ic-users"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>মোট ইউজার</p>
                    </div>
                </div>
                
                <div class="stat-card animate-up delay-2">
                    <div class="stat-icon ic-deposit"><i class="fa-solid fa-coins"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_deposits']; ?></h3>
                        <p>পেন্ডিং ডিপোজিট</p>
                    </div>
                </div>
                
                <div class="stat-card animate-up delay-3">
                    <div class="stat-icon ic-withdraw"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_withdrawals']; ?></h3>
                        <p>পেন্ডিং উইথড্র</p>
                    </div>
                </div>
                
                <div class="stat-card animate-up delay-4">
                    <div class="stat-icon ic-match"><i class="fa-solid fa-fire"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['active_matches']; ?></h3>
                        <p>অ্যাক্টিভ ম্যাচ</p>
                    </div>
                </div>
            </div>

            <div class="section-title animate-up delay-4"><i class="fa-solid fa-bolt" style="color: var(--accent-admin);"></i> কুইক অ্যাকশনস (Quick Actions)</div>
            
            <div class="actions-grid animate-up delay-4">
                <a href="manage_matches.php" class="action-card">
                    <div class="left"><i class="fa-solid fa-earth-americas"></i> নতুন ম্যাচ ও দেশ ম্যানেজ করুন</div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
                
                <a href="update_result.php" class="action-card">
                    <div class="left"><i class="fa-solid fa-gavel"></i> ম্যাচের রেজাল্ট ও উইনার আপডেট</div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>

                <a href="approve_deposits.php" class="action-card" style="<?php if($stats['pending_deposits'] > 0) echo 'border-color: #F59E0B; background: rgba(245, 158, 11, 0.05);'; ?>">
                    <div class="left"><i class="fa-solid fa-money-bill-transfer"></i> ডিপোজিট রিকোয়েস্ট চেক করুন</div>
                    <?php if($stats['pending_deposits'] > 0): ?>
                        <span class="badge badge-warning"><?php echo $stats['pending_deposits']; ?> New</span>
                    <?php else: ?>
                        <i class="fa-solid fa-chevron-right"></i>
                    <?php endif; ?>
                </a>

                <a href="approve_withdraws.php" class="action-card" style="<?php if($stats['pending_withdrawals'] > 0) echo 'border-color: #00E701; background: rgba(0, 231, 1, 0.05);'; ?>">
                    <div class="left"><i class="fa-solid fa-hand-holding-dollar"></i> উইথড্র রিকোয়েস্ট চেক করুন</div>
                    <?php if($stats['pending_withdrawals'] > 0): ?>
                        <span class="badge" style="background: #00E701;"><?php echo $stats['pending_withdrawals']; ?> New</span>
                    <?php else: ?>
                        <i class="fa-solid fa-chevron-right"></i>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <div class="admin-footer">
            &copy; <?php echo date('Y'); ?> PredX Sportsbook. Admin Portal v2.0
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('mobileOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
    </script>

</body>
</html>