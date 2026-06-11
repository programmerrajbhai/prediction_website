<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// শুধুমাত্র অ্যাডমিন এক্সেস পাবে
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
        // ১. ম্যাচ স্ট্যাটাস আপডেট করা
        $update_match = "UPDATE matches SET status = 'finished', team1_score = ?, team2_score = ? WHERE id = ?";
        $stmt_match = $conn->prepare($update_match);
        $stmt_match->bind_param("iii", $actual_score1, $actual_score2, $match_id);
        $stmt_match->execute();

        // ২. এই ম্যাচের সব Pending প্রেডিকশনগুলো নিয়ে আসা
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

            // লজিক চেক (Multi-Market)
            if ($bet_type === 'exact_score') {
                $p1 = $bet['predicted_score1'];
                $p2 = $bet['predicted_score2'];
                if ($p1 == $actual_score1 && $p2 == $actual_score2) {
                    $winnings = $stake * 5.0; // 5x Reward for Exact Score
                    $bet_status = 'won';
                }
            } elseif ($bet_type === 'match_winner') {
                if ($selection === $actual_winner_str) {
                    // ডেমো অডস অনুযায়ী উইনিং ক্যালকুলেশন (পরবর্তীতে এটা ডাটাবেস থেকে আসবে)
                    if ($selection === 'team1') $winnings = $stake * 2.0;
                    elseif ($selection === 'draw') $winnings = $stake * 3.5;
                    elseif ($selection === 'team2') $winnings = $stake * 1.8;
                    
                    $bet_status = 'won';
                }
            }

            // ৩. প্রেডিকশন টেবিল আপডেট করা
            $upd_pred = "UPDATE predictions SET status = ?, points_earned = ? WHERE id = ?";
            $stmt_upd_pred = $conn->prepare($upd_pred);
            $stmt_upd_pred->bind_param("sdi", $bet_status, $winnings, $bet['id']);
            $stmt_upd_pred->execute();

            // ৪. যদি জেতে, ইউজারের ব্যালেন্স আপডেট করা
            if ($bet_status === 'won') {
                $upd_user = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $stmt_user = $conn->prepare($upd_user);
                $stmt_user->bind_param("di", $winnings, $bet['user_id']);
                $stmt_user->execute();
            }
        }

        $conn->commit();
        $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> Result Updated & Bets Settled Successfully!</div>";

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='error-msg'><i class='fa-solid fa-triangle-exclamation'></i> Error: " . $e->getMessage() . "</div>";
    }
}

// Active Matches
$matches_sql = "SELECT m.id, t1.name as team1, t2.name as team2, m.match_time FROM matches m JOIN teams t1 ON m.team1_id = t1.id JOIN teams t2 ON m.team2_id = t2.id WHERE m.status != 'finished' ORDER BY m.match_time ASC";
$active_matches = $conn->query($matches_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Result - Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-main: #0B0E14; --bg-card: #151A22; --accent-admin: #FF3C3C; --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; background-image: radial-gradient(circle at 50% -20%, #2a1111, var(--bg-main) 60%); min-height: 100vh; }
        .admin-container { max-width: 600px; margin: 40px auto; background: rgba(21, 26, 34, 0.8); backdrop-filter: blur(15px); padding: 35px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); }
        .admin-container::before { content: ''; display: block; width: 100%; height: 4px; background: linear-gradient(90deg, var(--accent-admin), #FF8A8A); border-radius: 4px 4px 0 0; margin: -35px -35px 35px -35px; width: calc(100% + 70px); }
        h2 { text-align: center; color: var(--text-main); font-weight: 800; font-size: 24px; margin-bottom: 5px; }
        .subtitle { text-align: center; color: var(--text-muted); font-size: 13px; margin-bottom: 25px; text-transform: uppercase; letter-spacing: 1px;}
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 13px; }
        .input-group select, .input-group input { width: 100%; padding: 14px; border: 1px solid var(--border-color); border-radius: 8px; background: #0B0E14; color: var(--text-main); outline: none; font-family: 'Inter', sans-serif; font-size: 15px; transition: 0.3s; }
        .input-group select:focus, .input-group input:focus { border-color: var(--accent-admin); box-shadow: 0 0 10px rgba(255, 60, 60, 0.1); }
        .score-inputs { display: flex; gap: 15px; align-items: center; }
        .team-score { flex: 1; }
        .vs-text { font-size: 20px; color: var(--text-muted); font-weight: 800; margin-top: 25px; }
        .btn { width: 100%; padding: 15px; background: linear-gradient(90deg, var(--accent-admin), #D32F2F); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 800; text-transform: uppercase; margin-top: 15px; transition: 0.3s; box-shadow: 0 4px 15px rgba(255, 60, 60, 0.2); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 60, 60, 0.4); }
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; border: 1px solid rgba(0, 231, 1, 0.2); font-weight: 600; }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; border: 1px solid rgba(255, 60, 60, 0.2); font-weight: 600; }
        .back-link { display: inline-block; margin-bottom: 25px; color: var(--text-muted); text-decoration: none; font-weight: 600; transition: 0.3s; }
        .back-link:hover { color: var(--text-main); }
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <h2><i class="fa-solid fa-gavel" style="color: var(--accent-admin);"></i> Finalize Match</h2>
        <p class="subtitle">Submit actual scores to settle user bets</p>
        
        <?php echo $msg; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Select Active Match</label>
                <select name="match_id" required>
                    <option value="">-- Choose a match --</option>
                    <?php 
                    if($active_matches->num_rows > 0) {
                        while($row = $active_matches->fetch_assoc()) {
                            echo "<option value='".$row['id']."'>".$row['team1']." VS ".$row['team2']." (".date("d M", strtotime($row['match_time'])).")</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="score-inputs">
                <div class="team-score input-group">
                    <label>Team 1 Score</label>
                    <input type="number" name="team1_score" min="0" required placeholder="0">
                </div>
                <div class="vs-text">-</div>
                <div class="team-score input-group">
                    <label>Team 2 Score</label>
                    <input type="number" name="team2_score" min="0" required placeholder="0">
                </div>
            </div>

            <button type="submit" name="update_result" class="btn" onclick="return confirm('Are you sure? This action will distribute coins automatically!');">Settle Bets <i class="fa-solid fa-bolt" style="margin-left: 5px;"></i></button>
        </form>
    </div>
</body>
</html>