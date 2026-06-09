<?php
require_once 'config.php';
require_once 'functions.php';

// ইউজার লগইন না থাকলে লগইন পেজে পাঠিয়ে দেবে
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_balance = 0.00;

// ইউজারের বর্তমান ব্যালেন্স আনা
$bal_sql = "SELECT balance FROM users WHERE id = ?";
$bal_stmt = $conn->prepare($bal_sql);
$bal_stmt->bind_param("i", $user_id);
$bal_stmt->execute();
$current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];

// ডাটাবেস থেকে এই ইউজারের সব প্রেডিকশন হিস্ট্রি নিয়ে আসা
$history_sql = "
    SELECT p.*, m.match_time, m.team1_score, m.team2_score, m.status as match_status,
           t1.name as team1_name, t2.name as team2_name
    FROM predictions p
    JOIN matches m ON p.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bet History - Prediction Web</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .navbar { background-color: #1e293b; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .navbar a { color: #3b82f6; text-decoration: none; font-weight: bold; }
        .balance-badge { background-color: #334155; padding: 8px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; color: #fbbf24; border: 1px solid #475569; }
        
        .history-container { max-width: 1000px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }
        .history-container h2 { color: #cbd5e1; margin-top: 0; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        /* Table Styles */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td { padding: 15px; text-align: center; border-bottom: 1px solid #334155; }
        th { background-color: #0f172a; color: #94a3b8; text-transform: uppercase; font-size: 13px; font-weight: bold; letter-spacing: 1px; }
        td { font-size: 14px; vertical-align: middle; }
        tr:hover { background-color: #334155; transition: 0.2s; }
        
        .match-name { font-weight: bold; color: #e2e8f0; }
        .match-date { display: block; font-size: 12px; color: #64748b; margin-top: 4px; }
        
        .score-box { background: #0f172a; padding: 5px 10px; border-radius: 6px; border: 1px solid #475569; font-weight: bold; font-family: monospace; font-size: 15px; }
        .stake-amount { color: #fbbf24; font-weight: bold; }
        
        /* Status Badges */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid #f59e0b; }
        .badge-won { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid #22c55e; }
        .badge-lost { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }
        
        .points-earned { font-weight: bold; font-size: 15px; }
        .text-green { color: #22c55e; }
        .text-red { color: #ef4444; }
        .text-gray { color: #94a3b8; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">← Back to Feed</a>
        <div class="balance-badge">🪙 <span><?php echo number_format($current_balance, 2); ?></span> Coins</div>
    </div>

    <div class="history-container">
        <h2>📜 My Prediction History</h2>

        <div class="table-responsive">
            <?php if($history_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: left;">Match Details</th>
                            <th>My Prediction</th>
                            <th>Actual Score</th>
                            <th>Stake</th>
                            <th>Status</th>
                            <th>Return</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align: left;">
                                    <span class="match-name"><?php echo htmlspecialchars($row['team1_name']) . " VS " . htmlspecialchars($row['team2_name']); ?></span>
                                    <span class="match-date">📅 <?php echo date("d M Y, h:i A", strtotime($row['match_time'])); ?></span>
                                </td>
                                
                                <td>
                                    <span class="score-box"><?php echo $row['predicted_score1'] . " - " . $row['predicted_score2']; ?></span>
                                </td>
                                
                                <td>
                                    <?php if($row['match_status'] == 'finished'): ?>
                                        <span class="score-box"><?php echo $row['team1_score'] . " - " . $row['team2_score']; ?></span>
                                    <?php else: ?>
                                        <span style="color:#64748b; font-size:12px;">Waiting...</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="stake-amount"><?php echo number_format($row['stake_amount'], 2); ?></td>
                                
                                <td>
                                    <?php if($row['status'] == 'won'): ?>
                                        <span class="badge badge-won">Won</span>
                                    <?php elseif($row['status'] == 'lost'): ?>
                                        <span class="badge badge-lost">Lost</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="points-earned">
                                    <?php if($row['status'] == 'won'): ?>
                                        <span class="text-green">+<?php echo number_format($row['points_earned'], 2); ?></span>
                                    <?php elseif($row['status'] == 'lost'): ?>
                                        <span class="text-red">-<?php echo number_format($row['stake_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray">0.00</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; color: #64748b; padding: 40px;">
                    You haven't placed any predictions yet. Go to the feed and make your first bet! 🎯
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>