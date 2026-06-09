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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PredX - Premium Betting Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
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
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            padding: 0; 
            background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%);
            min-height: 100vh;
            overflow-x: hidden;
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
        }
        .navbar .logo span { color: var(--accent-primary); }
        
        .navbar .nav-right { display: flex; align-items: center; gap: 15px; }
        
        .balance-badge { 
            background: rgba(0, 231, 1, 0.1); 
            padding: 8px 16px; 
            border-radius: 30px; 
            font-size: 15px; 
            font-weight: 600; 
            color: var(--accent-primary); 
            border: 1px solid rgba(0, 231, 1, 0.3); 
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-outline { 
            padding: 8px 20px; 
            border: 1px solid var(--border-color); 
            color: var(--text-main); 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease; 
        }
        .btn-outline:hover { background-color: var(--border-color); }
        
        .btn-primary { 
            padding: 10px 24px; 
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); 
            color: #000; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: 800; 
            box-shadow: 0 4px 15px rgba(0, 231, 1, 0.3);
            transition: transform 0.2s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); }

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
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s ease;
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }

        /* --- Professional Offcanvas Sidebar --- */
        .sidebar {
            position: fixed;
            top: 0; right: -350px;
            width: 300px;
            height: 100vh;
            background: var(--bg-card);
            border-left: 1px solid var(--border-color);
            z-index: 1002;
            transition: right 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            flex-direction: column;
            box-shadow: -5px 0 25px rgba(0,0,0,0.5);
        }
        .sidebar.active { right: 0; }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info { display: flex; flex-direction: column; }
        .user-info .name { font-size: 18px; font-weight: 800; color: var(--text-main); }
        .user-info .role { font-size: 12px; color: var(--accent-primary); text-transform: uppercase; font-weight: 600; letter-spacing: 1px; }
        
        .close-sidebar {
            background: none; border: none; color: var(--text-muted);
            font-size: 20px; cursor: pointer; transition: 0.3s;
        }
        .close-sidebar:hover { color: #FF3C3C; transform: rotate(90deg); }

        .sidebar-wallet {
            padding: 20px;
            background: linear-gradient(145deg, #1A212D, #0F131A);
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }
        .sidebar-wallet .amt { font-size: 28px; font-weight: 800; color: var(--accent-primary); margin: 5px 0; }
        .sidebar-wallet p { margin: 0; font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;}
        
        .sidebar-menu { list-style: none; padding: 15px 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-menu li { padding: 0 15px; margin-bottom: 5px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 15px;
            padding: 12px 15px; border-radius: 8px;
            color: var(--text-muted); text-decoration: none; font-weight: 600; transition: 0.3s;
        }
        .sidebar-menu a i { width: 20px; text-align: center; font-size: 16px; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.05); color: var(--text-main); padding-left: 20px; }
        .sidebar-menu a.active { background: rgba(0, 231, 1, 0.1); color: var(--accent-primary); }

        .sidebar-footer { padding: 20px; border-top: 1px solid var(--border-color); }
        .logout-btn {
            display: block; width: 100%; padding: 12px; text-align: center;
            background: rgba(255, 60, 60, 0.1); color: #FF3C3C;
            border: 1px solid rgba(255, 60, 60, 0.2); border-radius: 8px;
            font-weight: 600; text-decoration: none; transition: 0.3s;
        }
        .logout-btn:hover { background: #FF3C3C; color: #fff; }

        /* --- Matches Section --- */
        .main-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .section-header h2 { margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .section-header h2 i { color: #FF3C3C; }
        
        .matches-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 25px; }
        
        /* Match Card */
        .match-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; transition: transform 0.3s ease; position: relative; }
        .match-card:hover { transform: translateY(-5px); border-color: #3B465A; }
        .card-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: rgba(255, 255, 255, 0.02); border-bottom: 1px solid var(--border-color); }
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 5px; }
        .status-upcoming { background: rgba(139, 148, 163, 0.1); color: var(--text-muted); }
        .status-live { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; }
        .status-live i { font-size: 8px; animation: blink 1.5s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }
        .match-time { font-size: 12px; font-weight: 600; color: var(--text-muted); }
        .teams-area { display: flex; justify-content: space-between; align-items: center; padding: 25px 20px; }
        .team { display: flex; flex-direction: column; align-items: center; width: 40%; gap: 12px; }
        .team-flag { width: 50px; height: 50px; border-radius: 50%; background: var(--bg-main); border: 2px solid var(--border-color); display: flex; justify-content: center; align-items: center; font-size: 20px; }
        .team-name { font-size: 15px; font-weight: 700; text-align: center; color: var(--text-main); }
        .vs-box { font-size: 14px; font-weight: 800; color: var(--text-muted); background: var(--bg-main); padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-color); }
        .card-action { padding: 0 20px 20px; }
        .predict-btn { display: block; width: 100%; padding: 14px; background: #1C232F; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.2); border-radius: 8px; text-align: center; font-size: 15px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: 0.3s; }
        .predict-btn:hover { background: var(--accent-primary); color: #000; box-shadow: 0 0 15px rgba(0, 231, 1, 0.3); }

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
                    <i class="fa-solid fa-coins"></i> <?php echo number_format($balance, 2); ?>
                </div>
                
                <button class="menu-trigger" id="openMenuBtn">
                    <i class="fa-regular fa-user"></i>
                    <span style="display: none;">Menu</span> <i class="fa-solid fa-bars" style="margin-left: 5px;"></i>
                </button>
            <?php else: ?>
                <a href="login.php" class="btn-outline">Log In</a>
                <a href="register.php" class="btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($is_logged_in): ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="sidebar" id="userSidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="role"><?php echo $user_role; ?> Account</span>
                </div>
                <button class="close-sidebar" id="closeMenuBtn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="sidebar-wallet">
                <p>Available Balance</p>
                <div class="amt"><?php echo number_format($balance, 2); ?> <i class="fa-solid fa-coins" style="font-size: 20px;"></i></div>
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <a href="deposit.php" style="flex:1; padding:8px; background:var(--accent-primary); color:#000; border-radius:6px; text-decoration:none; font-weight:bold; font-size:13px;">Deposit</a>
                    <a href="withdraw.php" style="flex:1; padding:8px; background:rgba(255,255,255,0.1); color:var(--text-main); border-radius:6px; text-decoration:none; font-weight:bold; font-size:13px;">Withdraw</a>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><i class="fa-solid fa-house"></i> Home (Feed)</a></li>
                <li><a href="bet_history.php"><i class="fa-solid fa-clock-rotate-left"></i> My Predictions</a></li>
                <li><a href="leaderboard.php"><i class="fa-solid fa-trophy"></i> Leaderboard</a></li>
                
                <?php if ($user_role === 'admin'): ?>
                    <li style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <span style="padding: 0 15px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Admin Area</span>
                    </li>
                    <li><a href="admin/index.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
                    <li><a href="admin/manage_matches.php"><i class="fa-solid fa-calendar-plus"></i> Manage Matches</a></li>
                    <li><a href="admin/update_result.php"><i class="fa-solid fa-check-double"></i> Update Results</a></li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Secure Logout</a>
            </div>
        </div>
    <?php endif; ?>


    <div class="main-container">
        <div class="section-header">
            <h2><i class="fa-solid fa-circle-play"></i> Betting Arena</h2>
        </div>

        <div class="matches-grid">
            <?php if($matches_result->num_rows > 0): ?>
                <?php while($match = $matches_result->fetch_assoc()): ?>
                    <div class="match-card">
                        <div class="card-header">
                            <?php if($match['status'] == 'live'): ?>
                                <div class="status-badge status-live"><i class="fa-solid fa-circle"></i> Live</div>
                            <?php else: ?>
                                <div class="status-badge status-upcoming">Upcoming</div>
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
                            <div class="vs-box">VS</div>
                            <div class="team">
                                <div class="team-flag"><?php echo strtoupper(substr($match['team2_name'], 0, 1)); ?></div>
                                <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                            </div>
                        </div>

                        <div class="card-action">
                            <?php if ($is_logged_in): ?>
                                <a href="place_bet.php?match_id=<?php echo $match['match_id']; ?>" class="predict-btn">Place Prediction</a>
                            <?php else: ?>
                                <a href="login.php" class="predict-btn" style="color:var(--accent-secondary); border-color:rgba(0,123,255,0.2);">
                                    <i class="fa-solid fa-lock"></i> Login to Predict
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align:center; padding: 50px; background:var(--bg-card); border-radius:12px; border:1px solid var(--border-color); color:var(--text-muted);">
                    <i class="fa-regular fa-calendar-xmark" style="font-size:40px; margin-bottom:15px;"></i>
                    <p>No matches available right now.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if ($is_logged_in): ?>
        const openBtn = document.getElementById('openMenuBtn');
        const closeBtn = document.getElementById('closeMenuBtn');
        const sidebar = document.getElementById('userSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            // Prevent body scroll when menu is open
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }

        openBtn.addEventListener('click', toggleMenu);
        closeBtn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
        <?php endif; ?>
    </script>

</body>
</html>