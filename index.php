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

$check_odds = $conn->query("SHOW COLUMNS FROM matches LIKE 'team1_odds'");
if($check_odds && $check_odds->num_rows == 0) {
    $conn->query("ALTER TABLE matches ADD COLUMN team1_odds DECIMAL(5,2) DEFAULT 2.00 AFTER match_time");
    $conn->query("ALTER TABLE matches ADD COLUMN draw_odds DECIMAL(5,2) DEFAULT 3.00 AFTER team1_odds");
    $conn->query("ALTER TABLE matches ADD COLUMN team2_odds DECIMAL(5,2) DEFAULT 2.00 AFTER draw_odds");
}
$check_flag = $conn->query("SHOW COLUMNS FROM teams LIKE 'flag'");
if($check_flag && $check_flag->num_rows == 0) {
    $conn->query("ALTER TABLE teams ADD COLUMN flag VARCHAR(10) DEFAULT '🛡️' AFTER name");
}

// ১. লাইভ এবং আসন্ন ম্যাচগুলোর কোয়েরি
$sql_upcoming = "
    SELECT m.*, 
           t1.name as team1_name, t1.flag as team1_flag, 
           t2.name as team2_name, t2.flag as team2_flag
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    WHERE m.status IN ('upcoming', 'live')
    ORDER BY m.status ASC, m.match_time ASC
";
$matches_upcoming = $conn->query($sql_upcoming);

// ২. শেষ হয়ে যাওয়া (Finished) ম্যাচগুলোর কোয়েরি
$sql_finished = "
    SELECT m.*, 
           t1.name as team1_name, t1.flag as team1_flag, 
           t2.name as team2_name, t2.flag as team2_flag
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    WHERE m.status = 'finished'
    ORDER BY m.match_time DESC LIMIT 30
";
$matches_finished = $conn->query($sql_finished);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PredX - প্রিমিয়াম বেটিং অ্যারেনা</title>
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
        .btn-outline { padding: 8px 18px; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary { padding: 8px 18px; background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); color: #000; border-radius: 8px; text-decoration: none; font-weight: 800; box-shadow: 0 4px 15px rgba(0, 231, 1, 0.3); }
        .menu-trigger { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.3s; }

        /* Matches Section */
        .main-container { max-width: 1000px; margin: 30px auto; padding: 0 15px; }
        .section-header { margin-bottom: 20px; }
        .section-header h2 { margin: 0; font-size: 22px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .section-header h2 i { color: var(--accent-primary); text-shadow: 0 0 10px rgba(0, 231, 1, 0.5); }
        
        /* --- TABS SYSTEM --- */
        .tabs-container {
            display: flex; gap: 10px; margin-bottom: 25px;
            background: rgba(255,255,255,0.02); padding: 6px; 
            border-radius: 12px; border: 1px solid var(--border-color);
        }
        .tab-btn {
            flex: 1; padding: 12px; background: transparent; border: none;
            color: var(--text-muted); font-weight: 800; font-size: 14px;
            border-radius: 8px; cursor: pointer; transition: 0.3s;
            font-family: 'Inter', sans-serif; display: flex; justify-content: center; gap: 8px; align-items: center;
        }
        .tab-btn.active { background: rgba(0, 231, 1, 0.1); color: var(--accent-primary); }
        .tab-content { display: none; animation: fadeInTab 0.4s ease forwards; }
        .tab-content.active { display: block; }
        @keyframes fadeInTab { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .matches-grid { display: flex; flex-direction: column; gap: 20px; }
        
        /* Match Card Base */
        .match-card { background: linear-gradient(145deg, #151A22, #0D1117); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; transition: all 0.3s ease; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.3); text-decoration: none; display: block; color: var(--text-main); width: 100%; }
        .match-card:hover { transform: translateY(-3px); border-color: var(--accent-primary); box-shadow: 0 10px 30px rgba(0,231,1,0.1); }
        
        /* Finished Match Card Overrides */
        .match-card.finished { filter: grayscale(40%); opacity: 0.85; cursor: default; }
        .match-card.finished:hover { transform: none; border-color: var(--border-color); box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .status-finished { background: rgba(255, 255, 255, 0.1); color: #FFF; border: 1px solid rgba(255,255,255,0.2); }

        .card-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: rgba(255, 255, 255, 0.02); border-bottom: 1px solid var(--border-color); }
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 5px; font-family: 'Inter', sans-serif;}
        .status-upcoming { background: rgba(139, 148, 163, 0.1); color: var(--text-muted); border: 1px solid rgba(139,148,163,0.2);}
        .status-live { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.3); box-shadow: 0 0 10px rgba(255,60,60,0.2);}
        .status-live i { font-size: 8px; animation: blink 1.5s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }
        
        .match-time { font-size: 13px; font-weight: 600; color: var(--text-muted); font-family: 'Inter', sans-serif;}
        
        .teams-layout { display: flex; justify-content: space-between; align-items: center; padding: 25px 20px; }
        .team-side { display: flex; align-items: center; gap: 15px; width: 42%; }
        .team-side.right { flex-direction: row-reverse; text-align: right; }
        
        /* Updated Image CSS for Teams */
        .team-flag { display: flex; align-items: center; justify-content: center; font-size: 36px; line-height: 1; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.4)); }
        .team-flag img { width: 40px; height: 40px; object-fit: contain; }
        
        .team-name { font-size: 18px; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'Inter', 'Noto Sans Bengali', sans-serif;}
        .vs-text { width: 16%; text-align: center; font-size: 14px; font-weight: 800; color: var(--text-muted); background: rgba(255,255,255,0.05); padding: 6px 0; border-radius: 8px;}

        .odds-container { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; padding: 0 20px 20px; }
        .odd-box { background: #0B0E14; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; text-align: center; transition: 0.3s; position: relative; overflow: hidden;}
        .match-card:not(.finished) .odd-box:hover { border-color: var(--accent-primary); background: rgba(0, 231, 1, 0.05); box-shadow: inset 0 0 10px rgba(0,231,1,0.1);}
        .odd-label { display: block; font-size: 12px; color: var(--text-muted); font-weight: 800; margin-bottom: 5px; font-family: 'Inter', sans-serif;}
        .odd-value { display: block; font-size: 18px; font-weight: 800; color: var(--accent-primary); font-family: 'Inter', sans-serif;}

        /* Lock Overlay for non-logged users */
        .lock-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 14, 20, 0.8); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; opacity: 0; transition: 0.3s; border-radius: 16px;}
        .match-card:hover .lock-overlay { opacity: 1; }
        .lock-btn { background: var(--accent-secondary); color: white; padding: 10px 25px; border-radius: 8px; font-weight: 800; font-size: 15px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);}

        @media (max-width: 768px) {
            .navbar { padding: 15px; }
            .btn-outline, .btn-primary, .menu-trigger { display: none; } 
            .teams-layout { padding: 20px 15px; gap: 5px;}
            .team-side { width: 40%; gap: 8px; }
            .team-name { font-size: 14px; }
            .team-flag img { width: 30px; height: 30px; }
            .odds-container { padding: 0 15px 20px; gap: 10px;}
            .odd-value { font-size: 16px; }
            .vs-text { font-size: 12px; }
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
                <button class="menu-trigger" id="openMenuBtn" style="display: <?php echo (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile/', $_SERVER['HTTP_USER_AGENT'])) ? 'none' : 'flex'; ?>;">
                    <i class="fa-solid fa-bars"></i>
                </button>
            <?php else: ?>
                <a href="login.php" class="btn-outline">লগইন</a>
                <a href="register.php" class="btn-primary">রেজিস্ট্রেশন</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="main-container">
        <div class="section-header">
            <h2><i class="fa-solid fa-fire"></i> স্পোর্টস হাইলাইটস</h2>
        </div>

        <div class="tabs-container">
            <button class="tab-btn active" onclick="switchTab('upcomingTab', this)"><i class="fa-regular fa-clock"></i> লাইভ ও আসন্ন</button>
            <button class="tab-btn" onclick="switchTab('finishedTab', this)"><i class="fa-solid fa-check-double"></i> ফলাফল</button>
        </div>

        <div id="upcomingTab" class="tab-content active">
            <div class="matches-grid">
                <?php if($matches_upcoming->num_rows > 0): ?>
                    <?php while($match = $matches_upcoming->fetch_assoc()): 
                        
                        // L O G O   R E N D E R I N G   L O G I C
                        $t1_flag_raw = !empty($match['team1_flag']) ? $match['team1_flag'] : '🛡️';
                        $t2_flag_raw = !empty($match['team2_flag']) ? $match['team2_flag'] : '🛡️';
                        
                        $t1_flag = filter_var($t1_flag_raw, FILTER_VALIDATE_URL) ? "<img src='{$t1_flag_raw}' alt='logo'>" : $t1_flag_raw;
                        $t2_flag = filter_var($t2_flag_raw, FILTER_VALIDATE_URL) ? "<img src='{$t2_flag_raw}' alt='logo'>" : $t2_flag_raw;
                        
                        $t1_odds = isset($match['team1_odds']) ? number_format($match['team1_odds'], 2) : '2.00';
                        $draw_odds = isset($match['draw_odds']) ? number_format($match['draw_odds'], 2) : '3.00';
                        $t2_odds = isset($match['team2_odds']) ? number_format($match['team2_odds'], 2) : '2.00';
                        $link = $is_logged_in ? "place_bet.php?match_id={$match['id']}" : "login.php";
                    ?>
                        <a href="<?php echo $link; ?>" class="match-card">
                            <div class="card-header">
                                <?php if($match['status'] == 'live'): ?>
                                    <div class="status-badge status-live"><i class="fa-solid fa-circle"></i> লাইভ</div>
                                <?php else: ?>
                                    <div class="status-badge status-upcoming">আসছে</div>
                                <?php endif; ?>
                                <div class="match-time">
                                    <i class="fa-regular fa-clock" style="margin-right:4px;"></i>
                                    <?php echo date("d M, h:i A", strtotime($match['match_time'])); ?>
                                </div>
                            </div>
                            
                            <div class="teams-layout">
                                <div class="team-side left">
                                    <span class="team-flag"><?php echo $t1_flag; ?></span>
                                    <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                </div>
                                <div class="vs-text">VS</div>
                                <div class="team-side right">
                                    <span class="team-flag"><?php echo $t2_flag; ?></span>
                                    <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                </div>
                            </div>

                            <div class="odds-container">
                                <div class="odd-box">
                                    <span class="odd-label">1 (Home)</span>
                                    <span class="odd-value"><?php echo $t1_odds; ?></span>
                                </div>
                                <div class="odd-box">
                                    <span class="odd-label">X (Draw)</span>
                                    <span class="odd-value"><?php echo $draw_odds; ?></span>
                                </div>
                                <div class="odd-box">
                                    <span class="odd-label">2 (Away)</span>
                                    <span class="odd-value"><?php echo $t2_odds; ?></span>
                                </div>
                            </div>

                            <?php if (!$is_logged_in): ?>
                            <div class="lock-overlay">
                                <div class="lock-btn"><i class="fa-solid fa-lock"></i> লগইন করুন</div>
                            </div>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 60px 20px; background:var(--bg-card); border-radius:16px; border:1px dashed var(--border-color); color:var(--text-muted);">
                        <i class="fa-regular fa-calendar-xmark" style="font-size:48px; margin-bottom:15px; opacity: 0.5;"></i>
                        <p style="font-size: 16px; font-weight: 600;">এই মুহূর্তে কোনো ম্যাচ উপলব্ধ নেই।</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="finishedTab" class="tab-content">
            <div class="matches-grid">
                <?php if($matches_finished->num_rows > 0): ?>
                    <?php while($match = $matches_finished->fetch_assoc()): 
                        
                        // L O G O   R E N D E R I N G   L O G I C
                        $t1_flag_raw = !empty($match['team1_flag']) ? $match['team1_flag'] : '🛡️';
                        $t2_flag_raw = !empty($match['team2_flag']) ? $match['team2_flag'] : '🛡️';
                        
                        $t1_flag = filter_var($t1_flag_raw, FILTER_VALIDATE_URL) ? "<img src='{$t1_flag_raw}' alt='logo'>" : $t1_flag_raw;
                        $t2_flag = filter_var($t2_flag_raw, FILTER_VALIDATE_URL) ? "<img src='{$t2_flag_raw}' alt='logo'>" : $t2_flag_raw;

                        $t1_odds = isset($match['team1_odds']) ? number_format($match['team1_odds'], 2) : '2.00';
                        $draw_odds = isset($match['draw_odds']) ? number_format($match['draw_odds'], 2) : '3.00';
                        $t2_odds = isset($match['team2_odds']) ? number_format($match['team2_odds'], 2) : '2.00';
                    ?>
                        <a href="javascript:void(0)" class="match-card finished">
                            <div class="card-header">
                                <div class="status-badge status-finished"><i class="fa-solid fa-flag-checkered"></i> সমাপ্ত</div>
                                <div class="match-time">
                                    <i class="fa-regular fa-clock" style="margin-right:4px;"></i>
                                    <?php echo date("d M, h:i A", strtotime($match['match_time'])); ?>
                                </div>
                            </div>
                            
                            <div class="teams-layout">
                                <div class="team-side left">
                                    <span class="team-flag"><?php echo $t1_flag; ?></span>
                                    <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                </div>
                                <div class="vs-text">FT</div>
                                <div class="team-side right">
                                    <span class="team-flag"><?php echo $t2_flag; ?></span>
                                    <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                </div>
                            </div>

                            <div class="odds-container" style="opacity: 0.6;">
                                <div class="odd-box">
                                    <span class="odd-label">1 (Home)</span>
                                    <span class="odd-value"><?php echo $t1_odds; ?></span>
                                </div>
                                <div class="odd-box">
                                    <span class="odd-label">X (Draw)</span>
                                    <span class="odd-value"><?php echo $draw_odds; ?></span>
                                </div>
                                <div class="odd-box">
                                    <span class="odd-label">2 (Away)</span>
                                    <span class="odd-value"><?php echo $t2_odds; ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 60px 20px; background:var(--bg-card); border-radius:16px; border:1px dashed var(--border-color); color:var(--text-muted);">
                        <i class="fa-solid fa-history" style="font-size:48px; margin-bottom:15px; opacity: 0.5;"></i>
                        <p style="font-size: 16px; font-weight: 600;">কোনো সমাপ্ত ম্যাচের রেকর্ড নেই।</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(button) {
                button.classList.remove('active');
            });
            
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>

</body>
</html>