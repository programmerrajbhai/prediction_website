<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied!</h2>");
}

$msg = '';

// ==========================================
// AUTO SCHEMA UPDATER 
// ==========================================
// ১. Matches টেবিলের অডস কলাম
$check_odds = $conn->query("SHOW COLUMNS FROM matches LIKE 'team1_odds'");
if($check_odds->num_rows == 0) {
    $conn->query("ALTER TABLE matches ADD COLUMN team1_odds DECIMAL(5,2) DEFAULT 2.00 AFTER match_time");
    $conn->query("ALTER TABLE matches ADD COLUMN draw_odds DECIMAL(5,2) DEFAULT 3.00 AFTER team1_odds");
    $conn->query("ALTER TABLE matches ADD COLUMN team2_odds DECIMAL(5,2) DEFAULT 2.00 AFTER draw_odds");
}

// ২. Teams টেবিলের ফ্ল্যাগ কলাম
$check_flag = $conn->query("SHOW COLUMNS FROM teams LIKE 'flag'");
if($check_flag->num_rows == 0) {
    $conn->query("ALTER TABLE teams ADD COLUMN flag VARCHAR(10) DEFAULT '🏳️' AFTER name");
}

// ==========================================
// TEAM MANAGEMENT LOGIC (নতুন দেশ/টিম যুক্ত)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_team'])) {
    $team_name = sanitizeInput($_POST['team_name'], $conn);
    $team_flag = sanitizeInput($_POST['team_flag'], $conn); // e.g. 🇧🇩

    $sql = "INSERT INTO teams (name, flag) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $team_name, $team_flag);
    if ($stmt->execute()) {
        $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> নতুন টিম/দেশ সফলভাবে যুক্ত হয়েছে!</div>";
    } else {
        $msg = "<div class='error-msg'>সিস্টেম এরর! টিম যুক্ত করা যায়নি।</div>";
    }
}

// Delete Team
if (isset($_GET['delete_team_id'])) {
    $del_id = intval($_GET['delete_team_id']);
    try {
        $conn->query("DELETE FROM teams WHERE id = $del_id");
        $msg = "<div class='success-msg'><i class='fa-solid fa-trash'></i> টিমটি মুছে ফেলা হয়েছে!</div>";
    } catch (Exception $e) {
        $msg = "<div class='error-msg'><i class='fa-solid fa-triangle-exclamation'></i> এই টিমটি ডিলিট সম্ভব নয় কারণ তারা কোনো ম্যাচে যুক্ত আছে!</div>";
    }
}

// ==========================================
// MATCH MANAGEMENT LOGIC
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_match'])) {
    $team1_id = intval($_POST['team1']);
    $team2_id = intval($_POST['team2']);
    $match_time = $_POST['match_time'];
    $team1_odds = floatval($_POST['team1_odds']);
    $draw_odds = floatval($_POST['draw_odds']);
    $team2_odds = floatval($_POST['team2_odds']);

    if ($team1_id == $team2_id) {
        $msg = "<div class='error-msg'>একই টিম নিজেদের সাথে খেলতে পারে না!</div>";
    } else {
        $sql = "INSERT INTO matches (team1_id, team2_id, match_time, team1_odds, draw_odds, team2_odds) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisddd", $team1_id, $team2_id, $match_time, $team1_odds, $draw_odds, $team2_odds);
        if ($stmt->execute()) $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> ম্যাচ সফলভাবে যুক্ত করা হয়েছে!</div>";
        else $msg = "<div class='error-msg'>সিস্টেম এরর!</div>";
    }
}

// Update Match
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_match'])) {
    $match_id = intval($_POST['match_id']);
    $team1_id = intval($_POST['team1']);
    $team2_id = intval($_POST['team2']);
    $match_time = $_POST['match_time'];
    $team1_odds = floatval($_POST['team1_odds']);
    $draw_odds = floatval($_POST['draw_odds']);
    $team2_odds = floatval($_POST['team2_odds']);
    $status = $_POST['status'];

    if ($team1_id == $team2_id) {
        $msg = "<div class='error-msg'>একই টিম নিজেদের সাথে খেলতে পারে না!</div>";
    } else {
        $sql = "UPDATE matches SET team1_id=?, team2_id=?, match_time=?, team1_odds=?, draw_odds=?, team2_odds=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisdddsi", $team1_id, $team2_id, $match_time, $team1_odds, $draw_odds, $team2_odds, $status, $match_id);
        if ($stmt->execute()) $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> ম্যাচ সফলভাবে আপডেট করা হয়েছে!</div>";
        else $msg = "<div class='error-msg'>সিস্টেম এরর!</div>";
    }
}

// Delete Match
if (isset($_GET['delete_match_id'])) {
    $del_id = intval($_GET['delete_match_id']);
    try {
        $conn->query("DELETE FROM matches WHERE id = $del_id");
        $msg = "<div class='success-msg'><i class='fa-solid fa-trash'></i> ম্যাচটি মুছে ফেলা হয়েছে!</div>";
    } catch (Exception $e) {
        $msg = "<div class='error-msg'><i class='fa-solid fa-triangle-exclamation'></i> ম্যাচটি ডিলিট সম্ভব নয় কারণ ইউজাররা বেট করেছে! স্ট্যাটাস Canceled করুন।</div>";
    }
}

// ==========================================
// FETCH DATA
// ==========================================
$teams = $conn->query("SELECT * FROM teams ORDER BY name ASC");

$matches_query = "SELECT m.*, t1.name as t1_name, t1.flag as t1_flag, t2.name as t2_name, t2.flag as t2_flag 
                  FROM matches m 
                  JOIN teams t1 ON m.team1_id = t1.id 
                  JOIN teams t2 ON m.team2_id = t2.id 
                  ORDER BY m.match_time DESC";
$all_matches = $conn->query($matches_query);

$edit_match = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_match = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ম্যাচ ও দেশ ম্যানেজমেন্ট - Admin PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-admin: #FF3C3C; --accent-admin-hover: #D32F2F; --text-main: #FFFFFF;
            --text-muted: #8B94A3; --border-color: #242B38; --sidebar-width: 280px;
        }

        body { 
            background-color: var(--bg-main); color: var(--text-main); font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding: 0; display: flex; min-height: 100vh; overflow-x: hidden;
            background-image: radial-gradient(circle at 50% -20%, #2a1111, var(--bg-main) 60%);
        }

        /* --- Sidebar & Topbar (Standard Admin Theme) --- */
        .sidebar { width: var(--sidebar-width); background: var(--bg-card); border-right: 1px solid var(--border-color); height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000; display: flex; flex-direction: column; transition: 0.3s ease; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .sidebar-header i { font-size: 28px; color: var(--accent-admin); }
        .sidebar-header h2 { margin: 0; font-size: 24px; font-weight: 800; font-family: 'Inter', sans-serif; letter-spacing: -1px; }
        .nav-menu { list-style: none; padding: 20px 15px; margin: 0; flex-grow: 1; overflow-y: auto; }
        .nav-title { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin: 15px 0 10px 10px; font-family: 'Inter', sans-serif; }
        .nav-item { margin-bottom: 5px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.3s; }
        .nav-link i { width: 20px; text-align: center; font-size: 16px; }
        .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
        .nav-link.active { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-left: 3px solid var(--accent-admin); }
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - var(--sidebar-width)); }
        .topbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; }
        .mobile-toggle { display: none; background: none; border: none; color: var(--text-main); font-size: 24px; cursor: pointer; }
        .logout-btn { padding: 8px 15px; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .logout-btn:hover { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-color: var(--accent-admin); }

        /* --- Dashboard Layout --- */
        .dashboard-container { padding: 30px; }
        .page-title { font-size: 24px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--accent-admin); }

        /* Tabs System */
        .admin-tabs { display: flex; gap: 15px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; overflow-x: auto; }
        .admin-tab { padding: 10px 20px; font-size: 15px; font-weight: 800; color: var(--text-muted); cursor: pointer; border-radius: 8px; transition: 0.3s; white-space: nowrap; }
        .admin-tab.active { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); box-shadow: inset 0 0 10px rgba(255, 60, 60, 0.2); }
        .tab-content { display: none; animation: fadeIn 0.4s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from{opacity:0; transform:translateY(5px);} to{opacity:1; transform:translateY(0);} }

        /* Cards & Forms */
        .content-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;}
        .card { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .card-header { font-size: 18px; font-weight: 800; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: var(--text-main); }
        
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 13px; font-weight: 600; }
        .input-group select, .input-group input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: #0B0E14; color: var(--text-main); font-family: 'Inter', 'Noto Sans Bengali', sans-serif; font-size: 14px; outline: none; transition: 0.3s; box-sizing: border-box; }
        .input-group select:focus, .input-group input:focus { border-color: var(--accent-admin); box-shadow: 0 0 10px rgba(255, 60, 60, 0.1); }
        .odds-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }

        .btn { width: 100%; padding: 14px; background: linear-gradient(90deg, var(--accent-admin), var(--accent-admin-hover)); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 800; text-transform: uppercase; transition: 0.3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(255, 60, 60, 0.2); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 60, 60, 0.4); }
        .btn-blue { background: linear-gradient(90deg, #007BFF, #0056b3); box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2); }
        .btn-blue:hover { box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4); }

        /* Tables */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .bg-upcoming { background: rgba(139, 148, 163, 0.1); color: var(--text-muted); border: 1px solid rgba(139, 148, 163, 0.3); }
        .bg-live { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.3); }
        .bg-finished { background: rgba(0, 231, 1, 0.1); color: #00E701; border: 1px solid rgba(0, 231, 1, 0.3); }

        .action-btns { display: flex; gap: 8px; }
        .a-btn { padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.3s; cursor: pointer; border: none; }
        .a-edit { background: rgba(0, 123, 255, 0.1); color: #007BFF; border: 1px solid rgba(0, 123, 255, 0.3); }
        .a-delete { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.3); }
        
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(0, 231, 1, 0.2); }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(255, 60, 60, 0.2); }

        @media (max-width: 1024px) { .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 992px) {
            .sidebar { left: -300px; } .sidebar.active { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.8); }
            .main-content { margin-left: 0; width: 100%; } .mobile-toggle { display: block; }
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
            <li class="nav-item"><a href="index.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> ড্যাশবোর্ড</a></li>
            <li class="nav-item"><a href="../index.php" class="nav-link" target="_blank"><i class="fa-solid fa-globe"></i> মেইন ওয়েবসাইট</a></li>
            
            <div class="nav-title">ম্যানেজমেন্ট</div>
            <li class="nav-item"><a href="manage_matches.php" class="nav-link active"><i class="fa-solid fa-earth-americas"></i> ম্যাচ ও দেশ ম্যানেজ</a></li>
            <li class="nav-item"><a href="update_result.php" class="nav-link"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a></li>
            <li class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> ইউজার ম্যানেজমেন্ট</a></li>
        </ul>
    </aside>

    <div style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; display:none;" id="mobileOverlay"></div>

    <div class="main-content">
        <header class="topbar">
            <button class="mobile-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <div style="flex-grow: 1;"></div>
            <a href="../logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> লগআউট</a>
        </header>

        <div class="dashboard-container">
            <div class="page-title"><i class="fa-solid fa-earth-americas"></i> সিস্টেম ডেটা ম্যানেজমেন্ট</div>
            <?php echo $msg; ?>

            <div class="admin-tabs">
                <div class="admin-tab <?php echo !$edit_match ? 'active' : ''; ?>" onclick="switchTab('matches', this)"><i class="fa-solid fa-calendar-plus"></i> ম্যাচ ম্যানেজমেন্ট</div>
                <div class="admin-tab" onclick="switchTab('teams', this)"><i class="fa-solid fa-flag"></i> টিম / দেশ যুক্ত করুন</div>
            </div>

            <div id="tab_matches" class="tab-content <?php echo !$edit_match ? 'active' : ''; ?>">
                <div class="content-grid">
                    <div class="card">
                        <div class="card-header"><?php echo $edit_match ? '✏️ ম্যাচ এডিট' : '➕ নতুন ম্যাচ শিডিউল'; ?></div>
                        <form action="manage_matches.php<?php echo $edit_match ? '?edit_id='.$edit_id : ''; ?>" method="POST">
                            <?php if($edit_match): ?><input type="hidden" name="match_id" value="<?php echo $edit_match['id']; ?>"><?php endif; ?>

                            <div class="input-group">
                                <label>টিম ১ (Home Team)</label>
                                <select name="team1" required>
                                    <option value="">দেশ/টিম সিলেক্ট করুন</option>
                                    <?php 
                                    $teams->data_seek(0);
                                    while($row = $teams->fetch_assoc()) {
                                        $selected = ($edit_match && $edit_match['team1_id'] == $row['id']) ? 'selected' : '';
                                        echo "<option value='".$row['id']."' $selected>".$row['flag']." ".$row['name']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="input-group">
                                <label>টিম ২ (Away Team)</label>
                                <select name="team2" required>
                                    <option value="">দেশ/টিম সিলেক্ট করুন</option>
                                    <?php 
                                    $teams->data_seek(0);
                                    while($row = $teams->fetch_assoc()) {
                                        $selected = ($edit_match && $edit_match['team2_id'] == $row['id']) ? 'selected' : '';
                                        echo "<option value='".$row['id']."' $selected>".$row['flag']." ".$row['name']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="input-group">
                                <label>তারিখ ও সময়</label>
                                <input type="datetime-local" name="match_time" value="<?php echo $edit_match ? date('Y-m-d\TH:i', strtotime($edit_match['match_time'])) : ''; ?>" required>
                            </div>

                            <div class="odds-grid">
                                <div class="input-group">
                                    <label style="color:#00E701;">Team 1 Odds</label>
                                    <input type="number" name="team1_odds" step="0.01" value="<?php echo $edit_match ? $edit_match['team1_odds'] : '2.00'; ?>" required>
                                </div>
                                <div class="input-group">
                                    <label style="color:#F59E0B;">Draw Odds</label>
                                    <input type="number" name="draw_odds" step="0.01" value="<?php echo $edit_match ? $edit_match['draw_odds'] : '3.00'; ?>" required>
                                </div>
                                <div class="input-group">
                                    <label style="color:#007BFF;">Team 2 Odds</label>
                                    <input type="number" name="team2_odds" step="0.01" value="<?php echo $edit_match ? $edit_match['team2_odds'] : '2.00'; ?>" required>
                                </div>
                            </div>

                            <?php if($edit_match): ?>
                                <div class="input-group">
                                    <label>স্ট্যাটাস</label>
                                    <select name="status" required>
                                        <option value="upcoming" <?php if($edit_match['status']=='upcoming') echo 'selected'; ?>>Upcoming</option>
                                        <option value="live" <?php if($edit_match['status']=='live') echo 'selected'; ?>>Live</option>
                                        <option value="finished" <?php if($edit_match['status']=='finished') echo 'selected'; ?>>Finished</option>
                                        <option value="canceled" <?php if($edit_match['status']=='canceled') echo 'selected'; ?>>Canceled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_match" class="btn">আপডেট করুন</button>
                                <a href="manage_matches.php" class="btn" style="background:#334155; text-align:center; text-decoration:none; display:block; box-shadow:none;">বাতিল</a>
                            <?php else: ?>
                                <button type="submit" name="add_match" class="btn">ম্যাচ যুক্ত করুন</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">📋 সকল ম্যাচ</div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ম্যাচ</th>
                                        <th>অডস</th>
                                        <th>স্ট্যাটাস</th>
                                        <th>অ্যাকশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($all_matches->num_rows > 0): while($m = $all_matches->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: bold;">
                                                <?php echo $m['t1_flag']." ".$m['t1_name']; ?> <span style='color:var(--text-muted);font-size:10px;'>VS</span> <?php echo $m['t2_flag']." ".$m['t2_name']; ?><br>
                                                <span style="font-size:11px; color:var(--text-muted); font-weight:normal;"><?php echo date("d M Y, h:i A", strtotime($m['match_time'])); ?></span>
                                            </td>
                                            <td style="font-family: monospace; color:var(--accent-admin); font-size:12px;"><?php echo $m['team1_odds']." - ".$m['draw_odds']." - ".$m['team2_odds']; ?></td>
                                            <td><span class="status-badge bg-<?php echo $m['status']; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="manage_matches.php?edit_id=<?php echo $m['id']; ?>" class="a-btn a-edit"><i class="fa-solid fa-pen"></i></a>
                                                    <button onclick="confirmDel('match', <?php echo $m['id']; ?>)" class="a-btn a-delete"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="4" style="text-align:center;">কোনো ম্যাচ নেই</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab_teams" class="tab-content">
                <div class="content-grid">
                    <div class="card">
                        <div class="card-header">🌍 নতুন দেশ/টিম যুক্ত করুন</div>
                        <form action="manage_matches.php" method="POST">
                            <div class="input-group">
                                <label>দেশ বা টিমের নাম</label>
                                <input type="text" name="team_name" required placeholder="যেমন: Bangladesh বা Real Madrid">
                            </div>
                            <div class="input-group">
                                <label>ফ্ল্যাগ (ইমোজি)</label>
                                <input type="text" name="team_flag" required placeholder="যেমন: 🇧🇩 বা ⚽">
                                <span style="font-size: 11px; color: var(--text-muted); margin-top:5px; display:block;">মোবাইল বা পিসি থেকে দেশের ইমোজি কপি করে পেস্ট করুন।</span>
                            </div>
                            <button type="submit" name="add_team" class="btn btn-blue">টিম সেভ করুন</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">📋 উপলব্ধ দেশসমূহ</div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ফ্ল্যাগ</th>
                                        <th>নাম</th>
                                        <th>অ্যাকশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($teams->num_rows > 0): $teams->data_seek(0); while($t = $teams->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-size: 24px;"><?php echo $t['flag']; ?></td>
                                            <td style="font-weight: 800;"><?php echo htmlspecialchars($t['name']); ?></td>
                                            <td>
                                                <button onclick="confirmDel('team', <?php echo $t['id']; ?>)" class="a-btn a-delete"><i class="fa-solid fa-trash"></i> ডিলিট</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="3" style="text-align:center;">কোনো টিম নেই</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Sidebar logic
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('mobileOverlay');
        function toggleSidebar() { sidebar.classList.toggle('active'); overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none'; }
        toggleBtn.addEventListener('click', toggleSidebar); overlay.addEventListener('click', toggleSidebar);

        // Tab Switch Logic
        function switchTab(tabId, el) {
            document.querySelectorAll('.admin-tab').forEach(tab => tab.classList.remove('active'));
            el.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab_' + tabId).classList.add('active');
        }

        // Delete Confirm Logic
        function confirmDel(type, id) {
            let msg = type === 'match' 
                ? "এই ম্যাচটি মুছে ফেলা হবে। ইউজাররা বেট করে থাকলে ডিলিট হবে না!" 
                : "এই দেশ/টিমটি মুছে ফেলা হবে। তারা কোনো ম্যাচে যুক্ত থাকলে ডিলিট হবে না!";
            let url = type === 'match' ? "?delete_match_id=" : "?delete_team_id=";

            Swal.fire({
                title: 'আপনি কি নিশ্চিত?', text: msg, icon: 'warning',
                showCancelButton: true, background: '#151A22', color: '#fff',
                confirmButtonColor: '#FF3C3C', cancelButtonColor: '#334155', confirmButtonText: 'হ্যাঁ, ডিলিট করুন!'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = "manage_matches.php" + url + id;
            })
        }
    </script>
</body>
</html>