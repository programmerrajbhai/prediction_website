<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:red; text-align:center;'>Access Denied!</h2>");
}

$msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_result'])) {
    $match_id = intval($_POST['match_id']);
    $actual_score1 = intval($_POST['team1_score']);
    $actual_score2 = intval($_POST['team2_score']);

    // আসল ম্যাচের উইনার বের করা (1 = Team 1, 2 = Team 2, 0 = Draw)
    $actual_winner = ($actual_score1 > $actual_score2) ? 1 : (($actual_score1 < $actual_score2) ? 2 : 0);

    // ডাটাবেস ট্রানজেকশন শুরু
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
            $pred_score1 = $bet['predicted_score1'];
            $pred_score2 = $bet['predicted_score2'];
            $stake = $bet['stake_amount'];
            
            // ইউজারের প্রেডিক্ট করা উইনার
            $pred_winner = ($pred_score1 > $pred_score2) ? 1 : (($pred_score1 < $pred_score2) ? 2 : 0);
            
            $winnings = 0;
            $bet_status = 'lost';

            // লজিক চেক
            if ($pred_score1 == $actual_score1 && $pred_score2 == $actual_score2) {
                // Exact Score Match: 5x Reward
                $winnings = $stake * 5;
                $bet_status = 'won';
            } elseif ($pred_winner == $actual_winner) {
                // Match Winner Correct: 2x Reward
                $winnings = $stake * 2;
                $bet_status = 'won';
            }

            // ৩. প্রেডিকশন টেবিল আপডেট করা
            $upd_pred = "UPDATE predictions SET status = ?, points_earned = ? WHERE id = ?";
            $stmt_upd_pred = $conn->prepare($upd_pred);
            $stmt_upd_pred->bind_param("sdi", $bet_status, $winnings, $bet['id']);
            $stmt_upd_pred->execute();

            // ৪. যদি জেতে, ইউজারের ব্যালেন্স আপডেট করা
            if ($bet_status == 'won') {
                $upd_user = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $stmt_user = $conn->prepare($upd_user);
                $stmt_user->bind_param("di", $winnings, $bet['user_id']);
                $stmt_user->execute();
            }
        }

        // সব কাজ সফল হলে Commit করা
        $conn->commit();
        $msg = "<div class='success'>Result Updated & Bets Settled Successfully! 🚀</div>";

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='error'>Error: " . $e->getMessage() . "</div>";
    }
}

// যে ম্যাচগুলো এখনো finished হয়নি, সেগুলোর লিস্ট আনা
$matches_sql = "
    SELECT m.id, t1.name as team1, t2.name as team2, m.match_time 
    FROM matches m 
    JOIN teams t1 ON m.team1_id = t1.id 
    JOIN teams t2 ON m.team2_id = t2.id 
    WHERE m.status != 'finished'
    ORDER BY m.match_time ASC
";
$active_matches = $conn->query($matches_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Result - Admin</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .admin-container { max-width: 600px; margin: 40px auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }
        h2 { text-align: center; color: #3b82f6; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #94a3b8; font-weight: bold; }
        .input-group select, .input-group input { width: 100%; padding: 12px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; outline: none; box-sizing: border-box; font-size: 15px; }
        .input-group select:focus, .input-group input:focus { border-color: #3b82f6; }
        
        .score-inputs { display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .score-inputs .team-score { flex: 1; }
        
        .btn { width: 100%; padding: 15px; background: #22c55e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; margin-top: 10px; }
        .btn:hover { background: #16a34a; }
        
        .success { background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #22c55e; }
        .error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #ef4444; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3b82f6; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="admin-container">
        <a href="../index.php" class="back-link">← Back to Homepage</a>
        <h2>🎯 Update Match Result</h2>
        <p style="text-align: center; color: #64748b; font-size: 14px; margin-bottom: 20px;">Submitting the score will automatically settle all pending bets.</p>
        
        <?php echo $msg; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Select Match</label>
                <select name="match_id" required>
                    <option value="">-- Choose a match --</option>
                    <?php 
                    if($active_matches->num_rows > 0) {
                        while($row = $active_matches->fetch_assoc()) {
                            $match_name = $row['team1'] . " VS " . $row['team2'] . " (" . date("d M", strtotime($row['match_time'])) . ")";
                            echo "<option value='".$row['id']."'>".$match_name."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="score-inputs">
                <div class="team-score">
                    <label>Team 1 Score</label>
                    <input type="number" name="team1_score" min="0" required placeholder="0">
                </div>
                <div style="font-size: 20px; color: #64748b; font-weight: bold; margin-top: 25px;">-</div>
                <div class="team-score">
                    <label>Team 2 Score</label>
                    <input type="number" name="team2_score" min="0" required placeholder="0">
                </div>
            </div>

            <button type="submit" name="update_result" class="btn" onclick="return confirm('Are you sure? This action will distribute coins and cannot be undone!');">Submit Result & Settle Bets</button>
        </form>
    </div>

</body>
</html>