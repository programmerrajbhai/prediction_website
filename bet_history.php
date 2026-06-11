<?php
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get User Balance
$bal_sql = "SELECT balance FROM users WHERE id = ?";
$bal_stmt = $conn->prepare($bal_sql);
$bal_stmt->bind_param("i", $user_id);
$bal_stmt->execute();
$current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];

// Get Prediction History
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
    <title>My Predictions - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); --accent-primary: #00E701; --accent-blue: #007BFF; --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; padding-bottom: 50px; background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%); min-height: 100vh; }
        
        /* Navbar */
        .navbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; }
        .navbar .back-btn { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 16px; transition: 0.3s; }
        .navbar .back-btn:hover { color: var(--text-main); }
        .balance-badge { background: rgba(0, 231, 1, 0.1); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 600; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); }

        .history-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
        .section-header h2 { margin: 0; font-size: 24px; font-weight: 800; color: var(--text-main); }
        .section-header i { color: var(--accent-blue); font-size: 24px; }
        
        /* Glassmorphism Table */
        .table-responsive { overflow-x: auto; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 18px 20px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        td { font-size: 14px; font-weight: 600; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        tr:last-child td { border-bottom: none; }
        
        .match-info .teams { display: block; font-size: 15px; color: var(--text-main); font-weight: 800; }
        .match-info .date { display: block; font-size: 12px; color: var(--text-muted); margin-top: 5px; }
        
        .bet-market { font-size: 11px; text-transform: uppercase; background: rgba(0, 123, 255, 0.1); color: var(--accent-blue); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(0, 123, 255, 0.2); margin-bottom: 6px; display: inline-block; }
        .bet-selection { display: block; font-size: 15px; color: var(--text-main); }

        .score-badge { background: #0B0E14; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); font-family: monospace; font-size: 15px; color: var(--text-main); }
        .stake-amt { color: #F59E0B; font-weight: 800; }
        
        /* Status Badges */
        .status { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-block; }
        .s-pending { background: rgba(245, 158, 11, 0.1); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); }
        .s-won { background: rgba(0, 231, 1, 0.1); color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); }
        .s-lost { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.3); }
        
        .return-amt { font-size: 16px; font-weight: 800; }
        .r-green { color: var(--accent-primary); }
        .r-red { color: #FF3C3C; }
        .r-gray { color: var(--text-muted); }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Home</a>
        <div class="balance-badge"><i class="fa-solid fa-coins"></i> <?php echo number_format($current_balance, 2); ?></div>
    </nav>

    <div class="history-container">
        <div class="section-header">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <h2>My Predictions</h2>
        </div>

        <div class="table-responsive">
            <?php if($history_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Match</th>
                            <th>Market & Selection</th>
                            <th>Actual Result</th>
                            <th>Stake</th>
                            <th>Status</th>
                            <th>Return</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td class="match-info">
                                    <span class="teams"><?php echo htmlspecialchars($row['team1_name']) . " vs " . htmlspecialchars($row['team2_name']); ?></span>
                                    <span class="date"><i class="fa-regular fa-clock"></i> <?php echo date("d M Y, H:i", strtotime($row['match_time'])); ?></span>
                                </td>
                                
                                <td>
                                    <?php if($row['bet_type'] == 'exact_score'): ?>
                                        <span class="bet-market">Exact Score</span>
                                        <span class="bet-selection"><?php echo htmlspecialchars($row['bet_selection']); ?></span>
                                    <?php else: ?>
                                        <span class="bet-market">Match Winner</span>
                                        <span class="bet-selection">
                                            <?php 
                                            if($row['bet_selection'] == 'team1') echo htmlspecialchars($row['team1_name']);
                                            elseif($row['bet_selection'] == 'team2') echo htmlspecialchars($row['team2_name']);
                                            else echo "Draw";
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if($row['match_status'] == 'finished'): ?>
                                        <span class="score-badge"><?php echo $row['team1_score'] . " - " . $row['team2_score']; ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:12px;">Pending...</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="stake-amt"><?php echo number_format($row['stake_amount'], 2); ?></td>
                                
                                <td>
                                    <?php if($row['status'] == 'won'): ?>
                                        <span class="status s-won">Won</span>
                                    <?php elseif($row['status'] == 'lost'): ?>
                                        <span class="status s-lost">Lost</span>
                                    <?php else: ?>
                                        <span class="status s-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="return-amt">
                                    <?php if($row['status'] == 'won'): ?>
                                        <span class="r-green">+<?php echo number_format($row['points_earned'], 2); ?></span>
                                    <?php elseif($row['status'] == 'lost'): ?>
                                        <span class="r-red">-<?php echo number_format($row['stake_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="r-gray">0.00</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-muted); padding: 50px;">
                    <i class="fa-solid fa-ghost" style="font-size: 40px; margin-bottom: 15px; color: var(--border-color);"></i>
                    <p>No predictions placed yet. Get in the game!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>