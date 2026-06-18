<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// লগইন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$balance = 0.00;
$msg = '';

// ইউজারের ব্যালেন্স আনা
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['balance'];

// ম্যাচ আইডি ভ্যালিডেশন
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

if ($match_id <= 0) {
    die("<div style='text-align:center; padding:50px; color:#fff; font-family:sans-serif;'>Invalid Match ID! <a href='index.php' style='color:#00E701;'>Go Back</a></div>");
}

// ডাটাবেস থেকে স্পেসিফিক ম্যাচ এবং তার ডাইনামিক অডস (Odds) নিয়ে আসা
$sql = "SELECT m.*, 
               t1.name as team1_name, t1.flag as team1_flag, 
               t2.name as team2_name, t2.flag as team2_flag
        FROM matches m
        JOIN teams t1 ON m.team1_id = t1.id
        JOIN teams t2 ON m.team2_id = t2.id
        WHERE m.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();

if (!$match || $match['status'] == 'finished') {
    die("<div style='text-align:center; padding:50px; color:#fff; font-family:sans-serif;'>Match is unavailable or already finished! <a href='index.php' style='color:#00E701;'>Go Back</a></div>");
}

// ডাইনামিক অডস ভ্যারিয়েবলে রাখা
$t1_odds = isset($match['team1_odds']) ? number_format($match['team1_odds'], 2) : '2.00';
$draw_odds = isset($match['draw_odds']) ? number_format($match['draw_odds'], 2) : '3.00';
$t2_odds = isset($match['team2_odds']) ? number_format($match['team2_odds'], 2) : '2.00';

// বেট প্লেস করার লজিক
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_bet'])) {
    $bet_amount = floatval($_POST['bet_amount']);
    $prediction = $_POST['prediction_choice']; // team1, draw, team2

    if ($bet_amount <= 0) {
        $msg = "<div class='error-msg'><i class='fa-solid fa-circle-exclamation'></i> সঠিক এমাউন্ট দিন!</div>";
    } elseif ($bet_amount > $balance) {
        $msg = "<div class='error-msg'><i class='fa-solid fa-wallet'></i> আপনার ব্যালেন্স অপর্যাপ্ত!</div>";
    } elseif (empty($prediction)) {
        $msg = "<div class='error-msg'>দয়া করে একটি অপশন সিলেক্ট করুন!</div>";
    } else {
        // ডাইনামিক অডস অনুযায়ী রিটার্ন ক্যালকুলেট করা
        $selected_odds = 0;
        if ($prediction == 'team1') $selected_odds = $t1_odds;
        elseif ($prediction == 'draw') $selected_odds = $draw_odds;
        elseif ($prediction == 'team2') $selected_odds = $t2_odds;
        
        $possible_return = $bet_amount * $selected_odds;

        // ইউজারের ব্যালেন্স কাটা
        $new_balance = $balance - $bet_amount;
        $conn->query("UPDATE users SET balance = $new_balance WHERE id = $user_id");

        // ডাটাবেসের predictions টেবিলে সেভ করা
        // নোট: আপনার ডাটাবেসে যদি prediction_type কলাম না থাকে, এটি অটোমেটিক তৈরি করে নেবে।
        $check_col = $conn->query("SHOW COLUMNS FROM predictions LIKE 'prediction_type'");
        if($check_col->num_rows == 0) {
            $conn->query("ALTER TABLE predictions ADD COLUMN prediction_type VARCHAR(20) DEFAULT 'team1' AFTER match_id");
            $conn->query("ALTER TABLE predictions ADD COLUMN odds DECIMAL(5,2) DEFAULT 0.00 AFTER prediction_type");
        }

        $insert_stmt = $conn->prepare("INSERT INTO predictions (user_id, match_id, prediction_type, odds, bet_amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $insert_stmt->bind_param("iisdd", $user_id, $match_id, $prediction, $selected_odds, $bet_amount);
        
        if ($insert_stmt->execute()) {
            $msg = "<div class='success-msg'><i class='fa-solid fa-check-circle'></i> বেট সফলভাবে প্লেস করা হয়েছে! সম্ভাব্য জয়: " . number_format($possible_return, 2) . "</div>";
            $balance = $new_balance; // UI আপডেট করার জন্য
        } else {
            $msg = "<div class='error-msg'>সিস্টেম এরর! আবার চেষ্টা করুন।</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Place Bet - <?php echo htmlspecialchars($match['team1_name'] . ' vs ' . $match['team2_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0B0E14; --bg-card: #151A22; 
            --accent-primary: #00E701; --accent-hover: #00C801; 
            --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38;
        }

        body { 
            background-color: var(--bg-main); color: var(--text-main); 
            font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding: 0; padding-bottom: 90px;
        }

        /* Top Header Area */
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; }
        .back-btn { color: var(--text-muted); text-decoration: none; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .back-btn:hover { color: var(--text-main); }
        .balance-badge { background: rgba(0, 231, 1, 0.05); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 800; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.2); display: flex; align-items: center; gap: 6px; font-family: 'Inter', sans-serif;}

        /* Main Betting Card */
        .bet-container { max-width: 600px; margin: 0 auto; padding: 0 15px; animation: slideUp 0.4s ease forwards; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .bet-card { background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border-color); padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        
        .match-title { text-align: center; font-size: 22px; font-weight: 800; margin: 0 0 10px; font-family: 'Inter', sans-serif; letter-spacing: -0.5px;}
        .time-lock { display: inline-flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.1); color: #F59E0B; padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: 800; border: 1px solid rgba(245, 158, 11, 0.2); margin: 0 auto 25px; justify-content: center; width: max-content; margin-left: auto; margin-right: auto; display: flex;}

        /* Inner Tabs */
        .inner-tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
        .i-tab { flex: 1; text-align: center; padding: 10px; font-size: 14px; font-weight: 800; color: var(--text-muted); cursor: pointer; border-radius: 8px; transition: 0.3s; }
        .i-tab.active { background: rgba(0, 123, 255, 0.1); color: #007BFF; }

        /* Odds Selection (Radio Buttons disguised as boxes) */
        .odds-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 30px; }
        .odds-grid input[type="radio"] { display: none; } /* Hide real radio buttons */
        
        .odd-option { background: #0B0E14; border: 1px solid var(--border-color); border-radius: 12px; padding: 15px 10px; text-align: center; cursor: pointer; transition: 0.3s; display: block;}
        .odd-option:hover { background: rgba(255,255,255,0.02); }
        .o-label { display: block; font-size: 13px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; font-family: 'Inter', sans-serif;}
        .o-value { display: block; font-size: 18px; font-weight: 800; color: var(--accent-primary); font-family: 'Inter', sans-serif;}

        /* Magic: When radio is checked, change the label box style */
        .odds-grid input[type="radio"]:checked + .odd-option { border-color: var(--accent-primary); background: rgba(0, 231, 1, 0.05); box-shadow: inset 0 0 15px rgba(0,231,1,0.1), 0 0 10px rgba(0,231,1,0.1); transform: translateY(-2px);}

        /* Amount Input */
        .input-label { display: block; font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 10px; }
        .amount-box { position: relative; margin-bottom: 10px; }
        .amount-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #F59E0B; font-size: 18px; }
        .amount-box input { width: 100%; background: #0B0E14; border: 1px solid var(--border-color); padding: 16px 16px 16px 45px; border-radius: 12px; color: var(--text-main); font-size: 16px; font-weight: 800; font-family: 'Inter', sans-serif; box-sizing: border-box; outline: none; transition: 0.3s; }
        .amount-box input:focus { border-color: var(--accent-primary); }
        .return-preview { text-align: right; font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 25px; height: 15px;}
        .return-preview span { color: var(--accent-primary); font-weight: 800; font-family: 'Inter', sans-serif;}

        /* Submit Button */
        .submit-btn { width: 100%; background: var(--accent-primary); color: #000; font-size: 16px; font-weight: 800; padding: 16px; border: none; border-radius: 12px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; transition: 0.3s; box-shadow: 0 4px 15px rgba(0, 231, 1, 0.3); }
        .submit-btn:hover { background: var(--accent-hover); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 231, 1, 0.4); }

        /* Messages */
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(255, 60, 60, 0.2); font-size: 14px;}
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(0, 231, 1, 0.2); font-size: 14px;}

        @media (max-width: 600px) {
            .odds-grid { gap: 8px; }
            .odd-option { padding: 12px 5px; }
            .o-label { font-size: 11px; }
            .o-value { font-size: 16px; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="top-header">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> হোমপেজে ফিরুন</a>
        <div class="balance-badge">
            <i class="fa-solid fa-coins" style="color: #F59E0B;"></i> <span><?php echo number_format($balance, 2); ?></span>
        </div>
    </div>

    <!-- Main Betting Interface -->
    <div class="bet-container">
        <div class="bet-card">
            <div class="match-title">
                <?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?>
            </div>
            
            <div class="time-lock">
                <i class="fa-regular fa-clock"></i> প্রেডিকশন লক হবে: <?php echo date("d Jun, h:i A", strtotime($match['match_time'])); ?>
            </div>

            <!-- UI Tabs (Visual Only as per design) -->
            <div class="inner-tabs">
                <div class="i-tab active">ম্যাচ উইনার (1X2)</div>
                <div class="i-tab" style="cursor: not-allowed; opacity: 0.5;" title="শীঘ্রই আসছে">সঠিক গোল (Exact Score)</div>
            </div>

            <?php echo $msg; ?>

            <form action="place_bet.php?match_id=<?php echo $match_id; ?>" method="POST" id="betForm">
                
                <!-- DYNAMIC ODDS SELECTION -->
                <div class="odds-grid">
                    <!-- Team 1 -->
                    <label>
                        <input type="radio" name="prediction_choice" value="team1" data-odds="<?php echo $t1_odds; ?>" required>
                        <div class="odd-option">
                            <span class="o-label"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                            <span class="o-value"><?php echo $t1_odds; ?>x</span>
                        </div>
                    </label>

                    <!-- Draw -->
                    <label>
                        <input type="radio" name="prediction_choice" value="draw" data-odds="<?php echo $draw_odds; ?>">
                        <div class="odd-option">
                            <span class="o-label">ড্র (Draw)</span>
                            <span class="o-value"><?php echo $draw_odds; ?>x</span>
                        </div>
                    </label>

                    <!-- Team 2 -->
                    <label>
                        <input type="radio" name="prediction_choice" value="team2" data-odds="<?php echo $t2_odds; ?>">
                        <div class="odd-option">
                            <span class="o-label"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                            <span class="o-value"><?php echo $t2_odds; ?>x</span>
                        </div>
                    </label>
                </div>

                <!-- Amount Input -->
                <label class="input-label">বেট এমাউন্ট (কয়েন)</label>
                <div class="amount-box">
                    <i class="fa-solid fa-coins"></i>
                    <input type="number" name="bet_amount" id="betAmount" placeholder="যেমন: 100" min="10" step="1" required>
                </div>
                
                <!-- Real-time return calculation text -->
                <div class="return-preview" id="returnPreview"></div>

                <button type="submit" name="place_bet" class="submit-btn">
                    বেট প্লেস করুন <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- UNIVERSAL BOTTOM NAV BAR INCLUDE -->
    <?php include 'bottom_nav.php'; ?>

    <!-- JavaScript for Live Return Calculation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('betAmount');
            const radioButtons = document.querySelectorAll('input[name="prediction_choice"]');
            const returnPreview = document.getElementById('returnPreview');

            function calculateReturn() {
                let amount = parseFloat(amountInput.value);
                let selectedOdds = 0;

                radioButtons.forEach(radio => {
                    if (radio.checked) {
                        selectedOdds = parseFloat(radio.getAttribute('data-odds'));
                    }
                });

                if (amount > 0 && selectedOdds > 0) {
                    let totalReturn = (amount * selectedOdds).toFixed(2);
                    returnPreview.innerHTML = `সম্ভাব্য জয়: <span>${totalReturn}</span> কয়েন`;
                } else {
                    returnPreview.innerHTML = '';
                }
            }

            amountInput.addEventListener('input', calculateReturn);
            radioButtons.forEach(radio => {
                radio.addEventListener('change', calculateReturn);
            });
        });
    </script>

</body>
</html>