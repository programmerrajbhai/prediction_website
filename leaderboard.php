<?php
require_once 'config.php';
require_once 'functions.php';

$is_logged_in = isset($_SESSION['user_id']);
$current_balance = 0.00;

// লগইন করা থাকলে ইউজারের নিজের ব্যালেন্স দেখানোর জন্য
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $bal_sql = "SELECT balance FROM users WHERE id = ?";
    $bal_stmt = $conn->prepare($bal_sql);
    $bal_stmt->bind_param("i", $user_id);
    $bal_stmt->execute();
    $current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];
}

// ডাটাবেস থেকে টপ ইউজারদের ডেটা আনা (অ্যাডমিন বাদে)
$leaderboard_sql = "
    SELECT username, balance 
    FROM users 
    WHERE role = 'user' AND balance > 0
    ORDER BY balance DESC 
    LIMIT 50
";
$top_users = $conn->query($leaderboard_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Prediction Web</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .navbar { background-color: #1e293b; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .navbar a { color: #3b82f6; text-decoration: none; font-weight: bold; }
        .balance-badge { background-color: #334155; padding: 8px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; color: #fbbf24; border: 1px solid #475569; }
        
        .leaderboard-container { max-width: 700px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }
        .leaderboard-container h2 { text-align: center; color: #fbbf24; margin-top: 0; margin-bottom: 30px; display: flex; justify-content: center; align-items: center; gap: 10px; font-size: 28px; }
        
        /* List Styles */
        .rank-list { list-style: none; padding: 0; margin: 0; }
        .rank-item { display: flex; justify-content: space-between; align-items: center; background: #0f172a; padding: 15px 20px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #334155; transition: 0.3s; }
        .rank-item:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        
        .rank-left { display: flex; align-items: center; gap: 15px; }
        .rank-number { font-size: 18px; font-weight: bold; width: 30px; text-align: center; color: #64748b; }
        .username { font-size: 16px; font-weight: bold; color: #e2e8f0; }
        
        .rank-coins { font-weight: bold; color: #fbbf24; font-size: 16px; display: flex; align-items: center; gap: 5px; }
        
        /* Top 3 Styles */
        .top-1 { background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(251, 191, 36, 0.05)); border-color: #fbbf24; }
        .top-1 .rank-number { font-size: 24px; }
        .top-1 .username { color: #fbbf24; font-size: 18px; }
        
        .top-2 { background: linear-gradient(135deg, rgba(148, 163, 184, 0.15), rgba(148, 163, 184, 0.05)); border-color: #94a3b8; }
        .top-2 .rank-number { font-size: 22px; }
        
        .top-3 { background: linear-gradient(135deg, rgba(180, 83, 9, 0.15), rgba(180, 83, 9, 0.05)); border-color: #b45309; }
        .top-3 .rank-number { font-size: 20px; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">← Back to Feed</a>
        <?php if($is_logged_in): ?>
            <div class="balance-badge">🪙 <span><?php echo number_format($current_balance, 2); ?></span> Coins</div>
        <?php else: ?>
            <a href="login.php" style="color: #22c55e;">Login</a>
        <?php endif; ?>
    </div>

    <div class="leaderboard-container">
        <h2>🏆 Top Predictors</h2>

        <ul class="rank-list">
            <?php if($top_users->num_rows > 0): ?>
                <?php 
                $rank = 1;
                while($user = $top_users->fetch_assoc()): 
                    // টপ ৩ জনের জন্য আলাদা ক্লাস ও ইমোজি সেট করা
                    $li_class = '';
                    $medal = '';
                    if ($rank == 1) { $li_class = 'top-1'; $medal = '🥇'; }
                    elseif ($rank == 2) { $li_class = 'top-2'; $medal = '🥈'; }
                    elseif ($rank == 3) { $li_class = 'top-3'; $medal = '🥉'; }
                    else { $medal = $rank; }
                ?>
                    <li class="rank-item <?php echo $li_class; ?>">
                        <div class="rank-left">
                            <span class="rank-number"><?php echo $medal; ?></span>
                            <span class="username">@<?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="rank-coins">
                            <?php echo number_format($user['balance'], 2); ?> 🪙
                        </div>
                    </li>
                <?php 
                $rank++;
                endwhile; 
                ?>
            <?php else: ?>
                <div style="text-align: center; color: #64748b; padding: 20px;">
                    Leaderboard is empty. Start predicting to get on top!
                </div>
            <?php endif; ?>
        </ul>
    </div>

</body>
</html>