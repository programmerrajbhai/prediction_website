<?php
require_once 'config.php';
require_once 'functions.php';

$is_logged_in = isset($_SESSION['user_id']);
$balance = 0.00;
$user_role = 'user';

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT balance, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $balance = $row['balance'];
        $user_role = $row['role'];
    }
}

// ডাটাবেস থেকে সব আপকামিং এবং লাইভ ম্যাচগুলো নিয়ে আসা
$match_sql = "
    SELECT m.id as match_id, m.match_time, m.status, 
           t1.name as team1_name, t1.flag as team1_flag, 
           t2.name as team2_name, t2.flag as team2_flag
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    WHERE m.status IN ('upcoming', 'live')
    ORDER BY m.match_time ASC
";
$matches_result = $conn->query($match_sql);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PredX - প্রিমিয়াম বেটিং অ্যারেনা</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- Base Setup --- */
        :root {
            --bg-main: #0B0E14;        
            --bg-card: #151A22;        
            --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-primary: #00E701; 
            --accent-hover: #00C801;
            --accent-secondary: #007BFF; 
            --text-main: #FFFFFF;
            --text-muted: #8B94A3;
            --border-color: #242B38;
        }

        body { 
            background-color: var(--bg-main); 
            color: var(--text-main); 
            font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; 
            padding: 0; 
            background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Modern Navbar --- */
        .navbar { 
            background: var(--bg-glass); 
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 15px 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid var(--border-color); 
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar .logo { 
            font-size: 26px; 
            font-weight: 800; 
            color: var(--text-main); 
            text-decoration: none; 
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }
        .navbar .logo span { color: var(--accent-primary); }
        
        .navbar .nav-right { display: flex; align-items: center; gap: 12px; }
        
        .balance-badge { 
            background: rgba(0, 231, 1, 0.1); 
            padding: 8px 16px; 
            border-radius: 30px; 
            font-size: 15px; 
            font-weight: 800; 
            color: var(--accent-primary); 
            border: 1px solid rgba(0, 231, 1, 0.3); 
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-outline { 
            padding: 8px 18px; 
            border: 1px solid var(--border-color); 
            color: var(--text-main); 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease; 
        }
        .btn-outline:hover { background-color: rgba(255,255,255,0.05); border-color: var(--text-muted); }
        
        .btn-primary { 
            padding: 8px 18px; 
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); 
            color: #000; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 800; 
            box-shadow: 0 4px 15px rgba(0, 231, 1, 0.3);
            transition: transform 0.2s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 231, 1, 0.4); }

        /* --- Sidebar Menu Button --- */
        .menu-trigger {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        .menu-trigger:hover { background: #1C232F; border-color: #3B465A; }

        /* --- Offcanvas Sidebar Overlay --- */
        .sidebar-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px);
            z-index: 1001; opacity: 0; visibility: hidden; transition: 0.3s ease;
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }

        /* --- Professional Offcanvas Sidebar --- */
        .sidebar {
            position: fixed; top: 0; right: -350px;
            width: 300px; max-width: 80vw; height: 100vh;
            background: var(--bg-card); border-left: 1px solid var(--border-color);
            z-index: 1002; transition: right 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex; flex-direction: column; box-shadow: -5px 0 25px rgba(0,0,0,0.5);
        }
        .sidebar.active { right: 0; }

        .sidebar-header {
            padding: 25px 20px; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .user-info { display: flex; flex-direction: column; }
        .user-info .name { font-size: 18px; font-weight: 800; color: var(--text-main); font-family: 'Inter', sans-serif;}
        .user-info .role { font-size: 12px; color: var(--accent-primary); text-transform: uppercase; font-weight: 600; letter-spacing: 1px; font-family: 'Inter', sans-serif;}
        
        .close-sidebar {
            background: none; border: none; color: var(--text-muted);
            font-size: 24px; cursor: pointer; transition: 0.3s; padding: 0;
        }
        .close-sidebar:hover { color: #FF3C3C; transform: rotate(90deg); }

        .sidebar-wallet {
            padding: 20px; background: linear-gradient(145deg, #1A212D, #0F131A);
            border-bottom: 1px solid var(--border-color); text-align: center;
        }
        .sidebar-wallet .amt { font-size: 28px; font-weight: 800; color: var(--accent-primary); margin: 5px 0; font-family: 'Inter', sans-serif;}
        .sidebar-wallet p { margin: 0; font-size: 13px; color: var(--text-muted); font-weight: 600; }
        
        .sidebar-menu { list-style: none; padding: 15px 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-menu li { padding: 0 15px; margin-bottom: 5px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 15px;
            padding: 12px 15px; border-radius: 8px;
            color: var(--text-muted); text-decoration: none; font-weight: 600; transition: 0.3s;
        }
        .sidebar-menu a i { width: 20px; text-align: center; font-size: 18px; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.05); color: var(--text-main); padding-left: 20px; }
        .sidebar-menu a.active { background: rgba(0, 231, 1, 0.1); color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.2);}

        .sidebar-footer { padding: 20px; border-top: 1px solid var(--border-color); }
        .logout-btn {
            display: block; width: 100%; padding: 12px; text-align: center;
            background: rgba(255, 60, 60, 0.1); color: #FF3C3C;
            border: 1px solid rgba(255, 60, 60, 0.2); border-radius: 8px;
            font-weight: 800; text-decoration: none; transition: 0.3s;
        }
        .logout-btn:hover { background: #FF3C3C; color: #fff; box-shadow: 0 4px 15px rgba(255, 60, 60, 0.3);}

        /* --- Matches Section --- */
        .main-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .section-header h2 { margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .section-header h2 i { color: var(--accent-primary); text-shadow: 0 0 10px rgba(0, 231, 1, 0.5); }
        
        /* Responsive Grid: minmax adjusted for smaller mobiles */
        .matches-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        
        /* Match Card */
        .match-card { 
            background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); 
            overflow: hidden; transition: all 0.3s ease; position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .match-card:hover { transform: translateY(-5px); border-color: #3B465A; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        
        .card-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: rgba(255, 255, 255, 0.02); border-bottom: 1px solid var(--border-color); }
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 5px; font-family: 'Inter', sans-serif;}
        .status-upcoming { background: rgba(139, 148, 163, 0.1); color: var(--text-muted); }
        .status-live { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.2);}
        .status-live i { font-size: 8px; animation: blink 1.5s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }
        
        .match-time { font-size: 12px; font-weight: 600; color: var(--text-muted); font-family: 'Inter', sans-serif;}
        
        .teams-area { display: flex; justify-content: space-between; align-items: center; padding: 25px 20px; }
        .team { display: flex; flex-direction: column; align-items: center; width: 40%; gap: 10px; }
        .team-flag { width: 45px; height: 45px; border-radius: 50%; background: var(--bg-main); border: 2px solid var(--border-color); display: flex; justify-content: center; align-items: center; font-size: 18px; font-weight: 800; color: var(--text-muted); font-family: 'Inter', sans-serif;}
        .team-name { font-size: 14px; font-weight: 800; text-align: center; color: var(--text-main); }
        
        .vs-box { font-size: 12px; font-weight: 800; color: var(--text-muted); background: var(--bg-main); padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border-color); }
        
        .card-action { padding: 0 20px 20px; }
        .predict-btn { 
            display: block; width: 100%; padding: 12px; background: #1C232F; color: var(--accent-primary); 
            border: 1px solid rgba(0, 231, 1, 0.2); border-radius: 8px; text-align: center; font-size: 15px; 
            font-weight: 800; text-decoration: none; transition: 0.3s;
        }
        .predict-btn:hover { background: var(--accent-primary); color: #000; box-shadow: 0 0 15px rgba(0, 231, 1, 0.3); }

        /* Media Queries for Mobile Responsiveness */
        @media (max-width: 600px) {
            .navbar { padding: 15px; }
            .btn-outline, .btn-primary { display: none; } /* Hide auth buttons on small screens, user menu takes over */
            .navbar .nav-right .menu-trigger { padding: 8px 12px; }
            .section-header h2 { font-size: 20px; }
            .teams-area { padding: 20px 15px; }
            .team-name { font-size: 13px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">
            <i class="fa-solid fa-bolt"></i> Pred<span>X</span>
        </a>
        
        <div class="nav-right">
            <?php if ($is_logged_in): ?>
                <div class="balance-badge">
                    <i class="fa-solid fa-coins"></i> <span style="margin-top: 2px;"><?php echo number_format($balance, 2); ?></span>
                </div>
                
                <button class="menu-trigger" id="openMenuBtn">
                    <i class="fa-regular fa-user"></i>
                    <i class="fa-solid fa-bars" style="margin-left: 5px;"></i>
                </button>
            <?php else: ?>
                <a href="login.php" class="btn-outline">লগইন</a>
                <a href="register.php" class="btn-primary">রেজিস্ট্রেশন</a>
                <a href="login.php" class="menu-trigger" style="display: none;" id="mobileLoginBtn"><i class="fa-solid fa-arrow-right-to-bracket"></i></a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($is_logged_in): ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="sidebar" id="userSidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="role"><?php echo $user_role; ?> একাউন্ট</span>
                </div>
                <button class="close-sidebar" id="closeMenuBtn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="sidebar-wallet">
                <p>বর্তমান ব্যালেন্স</p>
                <div class="amt"><?php echo number_format($balance, 2); ?> <i class="fa-solid fa-coins" style="font-size: 18px;"></i></div>
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <a href="deposit.php" style="flex:1; padding:10px; background:var(--accent-primary); color:#000; border-radius:6px; text-decoration:none; font-weight:800; font-size:13px;">ডিপোজিট</a>
                    <a href="withdraw.php" style="flex:1; padding:10px; background:rgba(255,255,255,0.1); color:var(--text-main); border-radius:6px; text-decoration:none; font-weight:800; font-size:13px; border: 1px solid var(--border-color);">উইথড্র</a>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><i class="fa-solid fa-house"></i> হোম (ফিড)</a></li>
                <li><a href="bet_history.php"><i class="fa-solid fa-clock-rotate-left"></i> আমার প্রেডিকশন</a></li>
                <li><a href="leaderboard.php"><i class="fa-solid fa-trophy"></i> লিডারবোর্ড</a></li>
                
                <?php if ($user_role === 'admin'): ?>
                    <li style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <span style="padding: 0 15px; font-size: 11px; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-family: 'Inter', sans-serif;">Admin Area</span>
                    </li>
                    <li><a href="admin/index.php"><i class="fa-solid fa-gauge-high"></i> ড্যাশবোর্ড</a></li>
                    <li><a href="admin/manage_matches.php"><i class="fa-solid fa-calendar-plus"></i> ম্যাচ ম্যানেজমেন্ট</a></li>
                    <li><a href="admin/update_result.php"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a></li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> লগআউট করুন</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="main-container">
        <div class="section-header">
            <h2><i class="fa-solid fa-fire"></i> বেটিং অ্যারেনা</h2>
        </div>

        <div class="matches-grid">
            <?php if($matches_result->num_rows > 0): ?>
                <?php while($match = $matches_result->fetch_assoc()): ?>
                    <div class="match-card">
                        <div class="card-header">
                            <?php if($match['status'] == 'live'): ?>
                                <div class="status-badge status-live"><i class="fa-solid fa-circle"></i> লাইভ</div>
                            <?php else: ?>
                                <div class="status-badge status-upcoming">আসছে</div>
                            <?php endif; ?>
                            <div class="match-time">
                                <i class="fa-regular fa-clock" style="margin-right:4px;"></i>
                                <?php echo date("d M, H:i", strtotime($match['match_time'])); ?>
                            </div>
                        </div>
                        
                        <div class="teams-area">
                            <div class="team">
                                <div class="team-flag"><?php echo strtoupper(substr($match['team1_name'], 0, 1)); ?></div>
                                <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                            </div>
                            <div class="vs-box">বনাম</div>
                            <div class="team">
                                <div class="team-flag"><?php echo strtoupper(substr($match['team2_name'], 0, 1)); ?></div>
                                <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                            </div>
                        </div>

                        <div class="card-action">
                            <?php if ($is_logged_in): ?>
                                <a href="place_bet.php?match_id=<?php echo $match['match_id']; ?>" class="predict-btn">প্রেডিকশন করুন</a>
                            <?php else: ?>
                                <a href="login.php" class="predict-btn" style="color:var(--accent-secondary); border-color:rgba(0,123,255,0.2);">
                                    <i class="fa-solid fa-lock"></i> প্রেডিক্ট করতে লগইন করুন
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align:center; padding: 60px 20px; background:var(--bg-card); border-radius:16px; border:1px dashed var(--border-color); color:var(--text-muted);">
                    <i class="fa-regular fa-calendar-xmark" style="font-size:48px; margin-bottom:15px; opacity: 0.5;"></i>
                    <p style="font-size: 16px; font-weight: 600;">এই মুহূর্তে কোনো ম্যাচ উপলব্ধ নেই। অ্যাডমিন ম্যাচ যুক্ত করলে এখানে দেখতে পাবেন!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Handle Mobile Auth Buttons
        if (window.innerWidth <= 600 && !<?php echo $is_logged_in ? 'true' : 'false'; ?>) {
            document.getElementById('mobileLoginBtn').style.display = 'flex';
        }

        <?php if ($is_logged_in): ?>
        const openBtn = document.getElementById('openMenuBtn');
        const closeBtn = document.getElementById('closeMenuBtn');
        const sidebar = document.getElementById('userSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            // Prevent body scroll when menu is open on mobile
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }
        
        openBtn.addEventListener('click', toggleMenu);
        closeBtn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
        <?php endif; ?>
    </script>

</body>
</html>