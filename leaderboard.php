<?php
require_once 'config.php';
require_once 'functions.php';

$is_logged_in = isset($_SESSION['user_id']);
$current_balance = 0.00;
$real_users = [];

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $bal_sql = "SELECT balance FROM users WHERE id = ?";
    $bal_stmt = $conn->prepare($bal_sql);
    $bal_stmt->bind_param("i", $user_id);
    $bal_stmt->execute();
    $current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];
}

// 1. Database theke ashol (Real) Top users ana
$leaderboard_sql = "SELECT username, balance FROM users WHERE role = 'user' AND balance > 0";
$top_users = $conn->query($leaderboard_sql);
if ($top_users->num_rows > 0) {
    while($row = $top_users->fetch_assoc()) {
        $real_users[] = ['username' => $row['username'], 'balance' => floatval($row['balance'])];
    }
}

// 2. High-End Fake User Generator (100+ Names)
$fake_users = [];

// Name parts for realistic combinations
$prefixes = ['king', 'pro', 'boss', 'super', 'mega', 'bd', 'mr', 'tiger', 'hero', 'alpha', 'itz', 'gamer', 'ninja', 'devil', 'shadow', 'legend', 'toxic', 'vip', 'dark', 'royal', 'dr', 'yt', 'op', 'sk', 'badboy'];
$names = ['sakib', 'rakib', 'hasan', 'rana', 'tareq', 'shuvo', 'mehedi', 'arman', 'opu', 'siam', 'nahid', 'rabbi', 'hridoy', 'akash', 'sagor', 'joy', 'nirob', 'fahim', 'tushar', 'emon', 'riad', 'tamim', 'mushfiq', 'miraj', 'liton', 'taskin', 'rubel', 'soumya', 'mustafiz', 'mahmud', 'arif', 'ashik', 'babu', 'bappy', 'chayan', 'dipu', 'faruk', 'galib', 'habib', 'imran', 'jamal', 'kamrul', 'limon', 'munna', 'nayem', 'osman', 'pavel', 'qader', 'rasel', 'sabbir', 'tonmoy', 'utsab', 'zahid'];
$suffixes = ['99', '123', '007', '75', '777', '11', '01', 'x', 'boss', 'yt', 'ff', 'gaming', 'bd', '10', '00', '69', '360', 'official', 'khan', 'chowdhury'];

// 1-ghontar fixed random seed toiri kora
$current_time = time();
$current_hour = floor($current_time / 3600);
mt_srand($current_hour); 

// 130 jon fake user toiri kora (jate Top 100 e always competitive lage)
for ($i = 0; $i < 130; $i++) {
    $pattern = mt_rand(1, 4);
    $fake_name = '';
    
    // Naming patterns to look exactly like real gamers/bettors
    if ($pattern == 1) {
        $fake_name = $names[array_rand($names)] . mt_rand(10, 9999);
    } elseif ($pattern == 2) {
        $fake_name = $prefixes[array_rand($prefixes)] . '_' . $names[array_rand($names)];
    } elseif ($pattern == 3) {
        $fake_name = $names[array_rand($names)] . '_' . $suffixes[array_rand($suffixes)];
    } else {
        $fake_name = $prefixes[array_rand($prefixes)] . $names[array_rand($names)] . $suffixes[array_rand($suffixes)];
    }
    
    // Random balance logic: Kichu user er onek coin thakbe (50k-90k), beshirbhag er normal thakbe (5k-40k)
    if ($i < 5) {
        $fake_balance = mt_rand(60000, 95000); // Top players
    } elseif ($i < 20) {
        $fake_balance = mt_rand(30000, 59000); // High players
    } else {
        $fake_balance = mt_rand(2000, 29000); // Normal players
    }
    
    $fake_users[] = [
        'username' => strtolower($fake_name), // Sob nam lowercase e jate real mone hoy
        'balance' => floatval($fake_balance)
    ];
}

mt_srand(); // Seed reset

// 3. Ashol o Fake user eksathe merge kora
$all_users = array_merge($real_users, $fake_users);

// Balance onujayi Descending order e sort kora
usort($all_users, function($a, $b) {
    return $b['balance'] <=> $a['balance'];
});

// Top 100 jon ke select kora (User 100+ chyeche tai)
$users_array = array_slice($all_users, 0, 100);

// Timer calculation
$next_hour = ceil($current_time / 3600) * 3600;
$time_left = $next_hour - $current_time; 
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লিডারবোর্ড - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-primary: #00E701; --accent-gold: #F59E0B; --accent-silver: #94A3B8; --accent-bronze: #D97706;
            --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38;
        }

        body { 
            background-color: var(--bg-main); color: var(--text-main); font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding-bottom: 50px; background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%); min-height: 100vh; overflow-x: hidden;
        }

        /* Navbar */
        .navbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; }
        .navbar .back-btn { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 15px; transition: 0.3s; }
        .navbar .back-btn:hover { color: var(--text-main); }
        .balance-badge { background: rgba(0, 231, 1, 0.1); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 800; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); font-family: 'Inter', sans-serif;}

        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }

        /* Tournament Header */
        .tournament-header { text-align: center; margin-bottom: 40px; animation: fadeIn 0.8s ease; }
        .tournament-header h2 { margin: 0 0 10px 0; font-size: 28px; font-weight: 800; color: var(--accent-gold); text-shadow: 0 0 15px rgba(245, 158, 11, 0.3); }
        .tournament-header p { color: var(--text-muted); margin: 0 0 20px 0; font-size: 14px; }
        
        .timer-box { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); display: inline-flex; align-items: center; gap: 15px; padding: 10px 25px; border-radius: 30px; }
        .timer-box i { color: var(--accent-gold); font-size: 20px; animation: spin 4s linear infinite; }
        .time-text { font-family: 'Inter', monospace; font-size: 20px; font-weight: 800; color: var(--accent-gold); letter-spacing: 2px; }

        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Podium (Top 3) */
        .podium-container { display: flex; justify-content: center; align-items: flex-end; gap: 15px; margin-bottom: 50px; height: 250px; margin-top: 30px;}
        .podium-item { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px 16px 8px 8px; width: 140px; display: flex; flex-direction: column; justify-content: flex-start; align-items: center; padding: 20px 10px; position: relative; transition: 0.3s; }
        .podium-item:hover { transform: translateY(-10px); }
        
        .podium-avatar { width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: 800; background: var(--bg-main); border: 3px solid; margin-bottom: 15px; position: absolute; top: -30px; box-shadow: 0 5px 15px rgba(0,0,0,0.5);}
        .podium-name { margin-top: 25px; font-weight: 800; font-size: 14px; color: var(--text-main); text-align: center; word-break: break-all; font-family: 'Inter', sans-serif;}
        .podium-score { color: var(--accent-primary); font-weight: 800; font-size: 14px; margin-top: 5px; font-family: 'Inter', sans-serif;}
        .podium-prize { font-size: 12px; font-weight: 800; margin-top: 15px; padding: 4px 8px; border-radius: 4px; }

        .rank-1 { height: 200px; background: linear-gradient(180deg, rgba(245,158,11,0.15) 0%, var(--bg-card) 100%); border-color: rgba(245,158,11,0.5); box-shadow: 0 -10px 30px rgba(245, 158, 11, 0.2); z-index: 3; width: 160px;}
        .rank-1 .podium-avatar { border-color: var(--accent-gold); color: var(--accent-gold); width: 70px; height: 70px; top: -35px;}
        .rank-1 .podium-prize { background: rgba(245,158,11,0.2); color: var(--accent-gold); }

        .rank-2 { height: 160px; background: linear-gradient(180deg, rgba(148,163,184,0.15) 0%, var(--bg-card) 100%); border-color: rgba(148,163,184,0.5); z-index: 2; }
        .rank-2 .podium-avatar { border-color: var(--accent-silver); color: var(--accent-silver); }
        .rank-2 .podium-prize { background: rgba(148,163,184,0.2); color: var(--accent-silver); }

        .rank-3 { height: 130px; background: linear-gradient(180deg, rgba(217,119,6,0.15) 0%, var(--bg-card) 100%); border-color: rgba(217,119,6,0.5); z-index: 1; }
        .rank-3 .podium-avatar { border-color: var(--accent-bronze); color: var(--accent-bronze); }
        .rank-3 .podium-prize { background: rgba(217,119,6,0.2); color: var(--accent-bronze); }

        /* List (Rank 4-100) */
        .rank-list { display: flex; flex-direction: column; gap: 12px; }
        .rank-row { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; animation: fadeIn 0.5s ease forwards; opacity: 0;}
        .rank-row:hover { border-color: var(--accent-primary); transform: translateX(5px); background: rgba(255,255,255,0.02);}
        
        .row-left { display: flex; align-items: center; gap: 15px; }
        .row-num { width: 35px; font-weight: 800; color: var(--text-muted); font-size: 16px; font-family: 'Inter', sans-serif;}
        .row-name { font-weight: 800; font-size: 15px; color: var(--text-main); font-family: 'Inter', sans-serif;}
        
        .row-right { display: flex; align-items: center; gap: 20px; }
        .row-score { color: var(--accent-primary); font-weight: 800; font-family: 'Inter', sans-serif;}
        
        .row-prize { font-size: 12px; font-weight: 800; padding: 4px 8px; border-radius: 6px; width: 65px; text-align: center;}
        .prize-tier-1 { background: rgba(0, 123, 255, 0.1); color: #007BFF; border: 1px solid rgba(0, 123, 255, 0.2); }
        .prize-tier-2 { background: rgba(168, 85, 247, 0.1); color: #A855F7; border: 1px solid rgba(168, 85, 247, 0.2); }
        .prize-tier-3 { background: rgba(244, 63, 94, 0.1); color: #F43F5E; border: 1px solid rgba(244, 63, 94, 0.2); }

        /* Mobile Adjustments */
        @media (max-width: 600px) {
            .podium-container { gap: 8px; height: 200px;}
            .podium-item { width: 100px; padding: 15px 5px;}
            .rank-1 { width: 120px; height: 160px; }
            .rank-2 { height: 130px; }
            .rank-3 { height: 110px; }
            .podium-avatar { width: 45px; height: 45px; font-size: 18px; top: -22px;}
            .rank-1 .podium-avatar { width: 55px; height: 55px; top: -27px;}
            .podium-name { font-size: 12px; margin-top: 15px;}
            .row-prize { display: none; }
            .row-right { gap: 10px; }
        }
    </style>
</head>

<?php include 'bottom_nav.php'; ?>
<body>

    <nav class="navbar">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> হোমপেজ</a>
        <?php if($is_logged_in): ?>
            <div class="balance-badge"><i class="fa-solid fa-coins" style="color: #F59E0B;"></i> <?php echo number_format($current_balance, 2); ?></div>
        <?php else: ?>
            <a href="login.php" class="back-btn" style="color: var(--accent-primary);">লগইন করুন</a>
        <?php endif; ?>
    </nav>

    <div class="container">
        
        <div class="tournament-header">
            <h2>🏆 লাইভ টুর্নামেন্ট লিডারবোর্ড</h2>
            <p>প্রতি ১ ঘণ্টা পর পর লিডারবোর্ড আপডেট হয় এবং র‍্যাংক অনুযায়ী রিওয়ার্ড দেওয়া হয়! (Top 100)</p>
            <div class="timer-box">
                <i class="fa-solid fa-hourglass-half"></i>
                <div class="time-text" id="countdown">00:00:00</div>
            </div>
        </div>

        <?php if(count($users_array) > 0): ?>
            
            <div class="podium-container">
                <?php if(isset($users_array[1])): ?>
                <div class="podium-item rank-2">
                    <div class="podium-avatar">2</div>
                    <div class="podium-name">@<?php echo htmlspecialchars($users_array[1]['username']); ?></div>
                    <div class="podium-score"><?php echo number_format($users_array[1]['balance'], 0); ?> 🪙</div>
                    <div class="podium-prize">🎁 ২৫০০ কয়েন</div>
                </div>
                <?php endif; ?>

                <?php if(isset($users_array[0])): ?>
                <div class="podium-item rank-1">
                    <div class="podium-avatar"><i class="fa-solid fa-crown"></i></div>
                    <div class="podium-name">@<?php echo htmlspecialchars($users_array[0]['username']); ?></div>
                    <div class="podium-score"><?php echo number_format($users_array[0]['balance'], 0); ?> 🪙</div>
                    <div class="podium-prize">👑 ৫০০০ কয়েন</div>
                </div>
                <?php endif; ?>

                <?php if(isset($users_array[2])): ?>
                <div class="podium-item rank-3">
                    <div class="podium-avatar">3</div>
                    <div class="podium-name">@<?php echo htmlspecialchars($users_array[2]['username']); ?></div>
                    <div class="podium-score"><?php echo number_format($users_array[2]['balance'], 0); ?> 🪙</div>
                    <div class="podium-prize">🎁 ১০০০ কয়েন</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="rank-list">
                <?php 
                for ($i = 3; $i < count($users_array); $i++): 
                    $rank = $i + 1;
                    
                    // Dynamic Prizes up to 100
                    if ($rank <= 10) {
                        $prize = '৫০০ কয়েন';
                        $prize_class = 'prize-tier-1';
                    } elseif ($rank <= 50) {
                        $prize = '২০০ কয়েন';
                        $prize_class = 'prize-tier-2';
                    } else {
                        $prize = '৫০ কয়েন';
                        $prize_class = 'prize-tier-3';
                    }
                    
                    // Delay limit kore deya hoyeche jate 100 ta load hote beshi somoy na lage
                    $delay = min(($i * 0.05), 1.5); 
                ?>
                <div class="rank-row" style="animation-delay: <?php echo $delay; ?>s;">
                    <div class="row-left">
                        <div class="row-num">#<?php echo $rank; ?></div>
                        <div class="row-name">@<?php echo htmlspecialchars($users_array[$i]['username']); ?></div>
                    </div>
                    <div class="row-right">
                        <div class="row-prize <?php echo $prize_class; ?>">🎁 <?php echo $prize; ?></div>
                        <div class="row-score"><?php echo number_format($users_array[$i]['balance'], 0); ?> 🪙</div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

        <?php endif; ?>

    </div>

    <script>
        let timeLeft = <?php echo $time_left; ?>;

        function updateTimer() {
            if (timeLeft <= 0) {
                window.location.reload();
                return;
            }

            let h = Math.floor(timeLeft / 3600);
            let m = Math.floor((timeLeft % 3600) / 60);
            let s = timeLeft % 60;

            let formattedTime = 
                (h < 10 ? "0" + h : h) + ":" + 
                (m < 10 ? "0" + m : m) + ":" + 
                (s < 10 ? "0" + s : s);

            document.getElementById("countdown").innerText = formattedTime;
            timeLeft--;
        }

        setInterval(updateTimer, 1000);
        updateTimer();
    </script>

</body>
</html>