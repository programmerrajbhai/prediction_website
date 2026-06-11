<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক: শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:#FF3C3C; text-align:center; font-family:sans-serif;'>Access Denied!</h2>");
}

$msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_result'])) {
    $match_id = intval($_POST['match_id']);
    $actual_score1 = intval($_POST['team1_score']);
    $actual_score2 = intval($_POST['team2_score']);

    // আসল ম্যাচের উইনার বের করা (team1, team2, নাকি draw)
    $actual_winner_str = 'draw';
    if ($actual_score1 > $actual_score2) $actual_winner_str = 'team1';
    elseif ($actual_score1 < $actual_score2) $actual_winner_str = 'team2';

    $conn->begin_transaction();

    try {
        // ১. ম্যাচ ডাটাবেস থেকে ডাইনামিক অডস (Odds) নিয়ে আসা
        $odds_sql = "SELECT team1_odds, draw_odds, team2_odds FROM matches WHERE id = ?";
        $stmt_odds = $conn->prepare($odds_sql);
        $stmt_odds->bind_param("i", $match_id);
        $stmt_odds->execute();
        $odds_result = $stmt_odds->get_result();
        
        if($odds_result->num_rows == 0) throw new Exception("ম্যাচটি খুঁজে পাওয়া যায়নি!");
        $odds_data = $odds_result->fetch_assoc();
        
        $t1_odds = $odds_data['team1_odds'];
        $draw_odds = $odds_data['draw_odds'];
        $t2_odds = $odds_data['team2_odds'];

        // ২. ম্যাচ স্ট্যাটাস এবং স্কোর আপডেট করা
        $update_match = "UPDATE matches SET status = 'finished', team1_score = ?, team2_score = ? WHERE id = ?";
        $stmt_match = $conn->prepare($update_match);
        $stmt_match->bind_param("iii", $actual_score1, $actual_score2, $match_id);
        $stmt_match->execute();

        // ৩. এই ম্যাচের সব Pending প্রেডিকশনগুলো নিয়ে আসা
        $pred_sql = "SELECT * FROM predictions WHERE match_id = ? AND status = 'pending'";
        $stmt_preds = $conn->prepare($pred_sql);
        $stmt_preds->bind_param("i", $match_id);
        $stmt_preds->execute();
        $predictions = $stmt_preds->get_result();

        while ($bet = $predictions->fetch_assoc()) {
            $bet_type = $bet['bet_type'];
            $selection = $bet['bet_selection'];
            $stake = $bet['stake_amount'];
            
            $winnings = 0;
            $bet_status = 'lost';

            // লজিক চেক (Multi-Market with Dynamic Odds)
            if ($bet_type === 'exact_score') {
                $p1 = $bet['predicted_score1'];
                $p2 = $bet['predicted_score2'];
                if ($p1 == $actual_score1 && $p2 == $actual_score2) {
                    $winnings = $stake * 5.0; // Exact Score এর জন্য 5x ফিক্সড রাখা হলো
                    $bet_status = 'won';
                }
            } elseif ($bet_type === 'match_winner') {
                if ($selection === $actual_winner_str) {
                    // ডাইনামিক অডস অনুযায়ী উইনিং ক্যালকুলেশন
                    if ($selection === 'team1') $winnings = $stake * $t1_odds;
                    elseif ($selection === 'draw') $winnings = $stake * $draw_odds;
                    elseif ($selection === 'team2') $winnings = $stake * $t2_odds;
                    
                    $bet_status = 'won';
                }
            }

            // ৪. প্রেডিকশন টেবিল আপডেট করা
            $upd_pred = "UPDATE predictions SET status = ?, points_earned = ? WHERE id = ?";
            $stmt_upd_pred = $conn->prepare($upd_pred);
            $stmt_upd_pred->bind_param("sdi", $bet_status, $winnings, $bet['id']);
            $stmt_upd_pred->execute();

            // ৫. যদি জেতে, ইউজারের ব্যালেন্স আপডেট করা
            if ($bet_status === 'won') {
                $upd_user = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $stmt_user = $conn->prepare($upd_user);
                $stmt_user->bind_param("di", $winnings, $bet['user_id']);
                $stmt_user->execute();
            }
        }

        $conn->commit();
        $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> রেজাল্ট পাবলিশ হয়েছে এবং বিজয়ীদের ব্যালেন্স ডিস্ট্রিবিউট করা হয়েছে!</div>";

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='error-msg'><i class='fa-solid fa-triangle-exclamation'></i> এরর: " . $e->getMessage() . "</div>";
    }
}

// Active Matches (ফ্ল্যাগ সহ)
$matches_sql = "SELECT m.id, t1.name as team1, t1.flag as t1_flag, t2.name as team2, t2.flag as t2_flag, m.match_time 
                FROM matches m 
                JOIN teams t1 ON m.team1_id = t1.id 
                JOIN teams t2 ON m.team2_id = t2.id 
                WHERE m.status != 'finished' AND m.status != 'canceled'
                ORDER BY m.match_time ASC";
$active_matches = $conn->query($matches_sql);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>রেজাল্ট আপডেট - Admin PredX</title>
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

        /* --- Sidebar & Topbar --- */
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

        /* --- Page Content --- */
        .dashboard-container { padding: 30px; display: flex; justify-content: center; }
        .form-card { background: var(--bg-card); padding: 35px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); width: 100%; max-width: 550px; position: relative; overflow: hidden; }
        .form-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--accent-admin), #FF8A8A); }
        
        .header-box { text-align: center; margin-bottom: 25px; }
        .header-box i { font-size: 40px; color: var(--accent-admin); margin-bottom: 10px; }
        .header-box h2 { margin: 0 0 5px 0; font-size: 24px; font-weight: 800; }
        .header-box p { color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 600; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 13px; }
        .input-group select, .input-group input { width: 100%; padding: 14px; border: 1px solid var(--border-color); border-radius: 8px; background: #0B0E14; color: var(--text-main); outline: none; font-family: 'Inter', 'Noto Sans Bengali', sans-serif; font-size: 15px; transition: 0.3s; box-sizing: border-box; }
        .input-group select:focus, .input-group input:focus { border-color: var(--accent-admin); box-shadow: 0 0 10px rgba(255, 60, 60, 0.1); }
        
        .score-inputs { display: flex; gap: 15px; align-items: center; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px; }
        .team-score { flex: 1; text-align: center; }
        .team-score label { margin-bottom: 10px; color: var(--text-main); font-size: 14px;}
        .team-score input { text-align: center; font-size: 24px; font-weight: 800; color: var(--accent-admin); }
        .vs-text { font-size: 18px; color: var(--text-muted); font-weight: 800; margin-top: 15px; }
        
        .btn { width: 100%; padding: 15px; background: linear-gradient(90deg, var(--accent-admin), var(--accent-admin-hover)); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 800; text-transform: uppercase; transition: 0.3s; box-shadow: 0 4px 15px rgba(255, 60, 60, 0.2); display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 60, 60, 0.4); }
        
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; border: 1px solid rgba(0, 231, 1, 0.2); font-weight: 600; }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; border: 1px solid rgba(255, 60, 60, 0.2); font-weight: 600; }

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
            <li class="nav-item"><a href="../index.php" class="nav-link" target="_blank"><i class="fa-solid fa-globe"></i> মেইন ওয়েবসাইট</a></li>
            
            <div class="nav-title">ম্যানেজমেন্ট</div>
            <li class="nav-item"><a href="manage_matches.php" class="nav-link"><i class="fa-solid fa-earth-americas"></i> ম্যাচ ও দেশ ম্যানেজ</a></li>
            <li class="nav-item"><a href="update_result.php" class="nav-link active"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a></li>
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
            <div class="form-card">
                <div class="header-box">
                    <i class="fa-solid fa-gavel"></i>
                    <h2>ম্যাচ ফাইনাল করুন</h2>
                    <p>আসল স্কোর সাবমিট করুন, বিজয়ীদের একাউন্টে কয়েন যোগ হবে</p>
                </div>
                
                <?php echo $msg; ?>

                <form id="resultForm" action="" method="POST">
                    <div class="input-group">
                        <label>অ্যাক্টিভ ম্যাচ সিলেক্ট করুন</label>
                        <select name="match_id" required>
                            <option value="">-- ম্যাচ নির্বাচন করুন --</option>
                            <?php 
                            if($active_matches->num_rows > 0) {
                                while($row = $active_matches->fetch_assoc()) {
                                    echo "<option value='".$row['id']."'>".$row['t1_flag']." ".$row['team1']." VS ".$row['t2_flag']." ".$row['team2']." (".date("d M", strtotime($row['match_time'])).")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="score-inputs">
                        <div class="team-score input-group">
                            <label>টিম ১ গোল</label>
                            <input type="number" name="team1_score" min="0" required placeholder="0">
                        </div>
                        <div class="vs-text">VS</div>
                        <div class="team-score input-group">
                            <label>টিম ২ গোল</label>
                            <input type="number" name="team2_score" min="0" required placeholder="0">
                        </div>
                    </div>

                    <input type="hidden" name="update_result" value="1">
                    
                    <button type="button" class="btn" onclick="confirmSubmit()">রেজাল্ট পাবলিশ করুন <i class="fa-solid fa-bolt"></i></button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('mobileOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }
        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // SweetAlert2 Confirmation before Submit
        function confirmSubmit() {
            const form = document.getElementById('resultForm');
            // Check if form is valid
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'আপনি কি নিশ্চিত?',
                text: "এই রেজাল্টের ওপর ভিত্তি করে ইউজারদের ব্যালেন্স যোগ/বিয়োগ হবে। এটি আর পরিবর্তন করা যাবে না!",
                icon: 'warning',
                showCancelButton: true,
                background: '#151A22',
                color: '#fff',
                confirmButtonColor: '#FF3C3C',
                cancelButtonColor: '#334155',
                confirmButtonText: 'হ্যাঁ, পাবলিশ করুন!',
                cancelButtonText: 'বাতিল'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>