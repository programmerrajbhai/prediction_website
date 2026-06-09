<?php
require_once 'config.php';
require_once 'functions.php';

// ইউজার লগইন না থাকলে লগইন পেজে পাঠিয়ে দেবে
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;
$msg = '';

// ম্যাচ ডাটাবেসে আছে কি না এবং Upcoming কি না তা চেক করা
$match_sql = "SELECT m.*, t1.name as team1_name, t2.name as team2_name 
              FROM matches m 
              JOIN teams t1 ON m.team1_id = t1.id 
              JOIN teams t2 ON m.team2_id = t2.id 
              WHERE m.id = ? AND m.status = 'upcoming'";
$stmt = $conn->prepare($match_sql);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match_result = $stmt->get_result();

if ($match_result->num_rows == 0) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Invalid Match or Match has already started!</h2>");
}

$match = $match_result->fetch_assoc();

// ফর্ম সাবমিট হলে বেট প্লেস করার লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_bet'])) {
    $score1 = intval($_POST['score1']);
    $score2 = intval($_POST['score2']);
    $stake_amount = floatval($_POST['stake_amount']);

    if ($stake_amount <= 0) {
        $msg = "<div class='error'>Stake amount must be greater than 0!</div>";
    } else {
        // ডাটাবেস ট্রানজেকশন শুরু (যাতে ব্যালেন্স ডিডাক্ট এবং বেট প্লেস একসাথে হয়)
        $conn->begin_transaction();

        try {
            // ১. ইউজারের ব্যালেন্স চেক করা (FOR UPDATE দিয়ে Row Lock করা হলো যেন রেস কন্ডিশন না হয়)
            $bal_sql = "SELECT balance FROM users WHERE id = ? FOR UPDATE";
            $bal_stmt = $conn->prepare($bal_sql);
            $bal_stmt->bind_param("i", $user_id);
            $bal_stmt->execute();
            $user_row = $bal_stmt->get_result()->fetch_assoc();

            if ($user_row['balance'] < $stake_amount) {
                throw new Exception("Insufficient Balance! You only have " . $user_row['balance'] . " Coins.");
            }

            // ২. ব্যালেন্স কাটা
            $new_balance = $user_row['balance'] - $stake_amount;
            $update_bal = "UPDATE users SET balance = ? WHERE id = ?";
            $upd_stmt = $conn->prepare($update_bal);
            $upd_stmt->bind_param("di", $new_balance, $user_id);
            $upd_stmt->execute();

            // ৩. প্রেডিকশন টেবিলে ডেটা ইনসার্ট
            $insert_bet = "INSERT INTO predictions (user_id, match_id, predicted_score1, predicted_score2, stake_amount) 
                           VALUES (?, ?, ?, ?, ?)";
            $ins_stmt = $conn->prepare($insert_bet);
            $ins_stmt->bind_param("iiiid", $user_id, $match_id, $score1, $score2, $stake_amount);
            $ins_stmt->execute();

            // ৪. সব ঠিক থাকলে Commit করে ডেটাবেসে পার্মানেন্ট সেভ করা
            $conn->commit();
            $msg = "<div class='success'>Bet placed successfully! Best of luck. 🚀</div>";
            
        } catch (Exception $e) {
            // কোনো এরর হলে Rollback করে আগের অবস্থায় ফিরিয়ে আনা
            $conn->rollback();
            $msg = "<div class='error'>" . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Bet - <?php echo $match['team1_name'] . " vs " . $match['team2_name']; ?></title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .navbar { background-color: #1e293b; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .navbar a { color: #3b82f6; text-decoration: none; font-weight: bold; }
        
        .bet-container { max-width: 500px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); border: 1px solid #334155; }
        .match-header { text-align: center; margin-bottom: 20px; border-bottom: 1px solid #334155; padding-bottom: 15px; }
        .match-header h3 { margin: 0; color: #cbd5e1; }
        .match-time { font-size: 13px; color: #94a3b8; margin-top: 5px; }
        
        .score-inputs { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .team-input { display: flex; flex-direction: column; align-items: center; width: 40%; }
        .team-input label { font-weight: bold; margin-bottom: 10px; text-align: center; }
        .team-input input { width: 80px; height: 80px; text-align: center; font-size: 32px; font-weight: bold; background: #0f172a; border: 2px solid #3b82f6; color: white; border-radius: 12px; outline: none; }
        .team-input input:focus { border-color: #22c55e; }
        
        .vs { font-size: 24px; font-weight: bold; color: #64748b; }
        
        .stake-section { margin-bottom: 20px; }
        .stake-section label { display: block; margin-bottom: 8px; color: #94a3b8; font-weight: bold; }
        .stake-input-wrapper { position: relative; }
        .stake-input-wrapper span { position: absolute; left: 15px; top: 12px; color: #fbbf24; font-weight: bold; }
        .stake-section input { width: 100%; padding: 12px 12px 12px 40px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; font-size: 16px; box-sizing: border-box; outline: none; }
        
        .btn { width: 100%; padding: 15px; background: #22c55e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: bold; transition: 0.3s; text-transform: uppercase; }
        .btn:hover { background: #16a34a; }
        
        .error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #ef4444; }
        .success { background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #22c55e; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">← Back to Feed</a>
        <span style="color: #fbbf24; font-weight:bold;">🪙 Wallet</span>
    </div>

    <div class="bet-container">
        <div class="match-header">
            <h3>Predict The Score</h3>
            <div class="match-time">🕒 Starts at: <?php echo date("d M, h:i A", strtotime($match['match_time'])); ?></div>
        </div>

        <?php echo $msg; ?>

        <form action="" method="POST">
            <div class="score-inputs">
                <div class="team-input">
                    <label><?php echo htmlspecialchars($match['team1_name']); ?></label>
                    <input type="number" name="score1" min="0" required placeholder="0">
                </div>
                
                <div class="vs">VS</div>
                
                <div class="team-input">
                    <label><?php echo htmlspecialchars($match['team2_name']); ?></label>
                    <input type="number" name="score2" min="0" required placeholder="0">
                </div>
            </div>

            <div class="stake-section">
                <label>Bet Amount (Coins)</label>
                <div class="stake-input-wrapper">
                    <span>🪙</span>
                    <input type="number" name="stake_amount" step="0.01" min="1" required placeholder="Enter coin amount...">
                </div>
            </div>

            <button type="submit" name="place_bet" class="btn">Confirm Prediction</button>
        </form>
    </div>

</body>
</html>