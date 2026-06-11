<?php
require_once 'config.php';
require_once 'functions.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) {
    if(isset($_POST['ajax_bet'])) {
        echo json_encode(['status' => 'error', 'message' => 'আপনাকে প্রথমে লগইন করতে হবে।']);
        exit;
    }
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ২. CSRF Token জেনারেট
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// AUTO DATABASE SCHEMA UPDATER (For Multi-Market)
// ==========================================
$check_col = $conn->query("SHOW COLUMNS FROM predictions LIKE 'bet_type'");
if($check_col->num_rows == 0){
    $conn->query("ALTER TABLE predictions ADD COLUMN bet_type VARCHAR(50) DEFAULT 'exact_score' AFTER match_id");
    $conn->query("ALTER TABLE predictions ADD COLUMN bet_selection VARCHAR(50) NULL AFTER bet_type");
    $conn->query("UPDATE predictions SET bet_selection = CONCAT(predicted_score1, '-', predicted_score2) WHERE bet_type = 'exact_score'");
    $conn->query("ALTER TABLE predictions MODIFY predicted_score1 INT(11) NULL");
    $conn->query("ALTER TABLE predictions MODIFY predicted_score2 INT(11) NULL");
}

// ==========================================
// ৩. AJAX POST Request Handle (Place Bet Logic)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_bet'])) {
    header('Content-Type: application/json');
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'সিকিউরিটি এরর! ইনভ্যালিড টোকেন।']);
        exit;
    }

    $match_id = intval($_POST['match_id']);
    $stake_amount = floatval($_POST['stake_amount']);
    $bet_type = sanitizeInput($_POST['bet_type'], $conn); // 'match_winner' or 'exact_score'

    if ($stake_amount < 1) {
        echo json_encode(['status' => 'error', 'message' => 'সর্বনিম্ন বেট ১ কয়েন!']);
        exit;
    }

    // Match Status Check
    $match_sql = "SELECT status FROM matches WHERE id = ? AND status = 'upcoming'";
    $stmt = $conn->prepare($match_sql);
    $stmt->bind_param("i", $match_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'ম্যাচটি শুরু হয়ে গেছে অথবা বাতিল হয়েছে!']);
        exit;
    }

    // Process Bet Type
    $score1 = NULL; $score2 = NULL; $selection = '';
    
    if ($bet_type === 'match_winner') {
        if (!isset($_POST['winner_selection'])) {
            echo json_encode(['status' => 'error', 'message' => 'দয়া করে বিজয়ী দল নির্বাচন করুন!']);
            exit;
        }
        $selection = sanitizeInput($_POST['winner_selection'], $conn); // 'team1', 'team2', 'draw'
    } else {
        $score1 = intval($_POST['score1']);
        $score2 = intval($_POST['score2']);
        $selection = $score1 . '-' . $score2;
    }

    // Database Transaction
    $conn->begin_transaction();
    try {
        // Check Balance with Row Lock
        $bal_sql = "SELECT balance FROM users WHERE id = ? FOR UPDATE";
        $bal_stmt = $conn->prepare($bal_sql);
        $bal_stmt->bind_param("i", $user_id);
        $bal_stmt->execute();
        $user_row = $bal_stmt->get_result()->fetch_assoc();

        if ($user_row['balance'] < $stake_amount) {
            throw new Exception("পর্যাপ্ত ব্যালেন্স নেই! আপনার একাউন্টে " . number_format($user_row['balance'], 2) . " কয়েন আছে।");
        }

        // Deduct Balance
        $new_balance = $user_row['balance'] - $stake_amount;
        $update_bal = "UPDATE users SET balance = ? WHERE id = ?";
        $upd_stmt = $conn->prepare($update_bal);
        $upd_stmt->bind_param("di", $new_balance, $user_id);
        $upd_stmt->execute();

        // Insert Prediction
        $insert_bet = "INSERT INTO predictions (user_id, match_id, bet_type, bet_selection, predicted_score1, predicted_score2, stake_amount) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $ins_stmt = $conn->prepare($insert_bet);
        $ins_stmt->bind_param("iisssid", $user_id, $match_id, $bet_type, $selection, $score1, $score2, $stake_amount);
        $ins_stmt->execute();

        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'আপনার বেট সফলভাবে প্লেস করা হয়েছে! শুভকামনা।',
            'new_balance' => number_format($new_balance, 2)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ৪. Normal Page Load (Frontend UI)
// ==========================================
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

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
    die("<div style='background:#0B0E14; height:100vh; display:flex; justify-content:center; align-items:center; color:#FF3C3C; font-family:sans-serif;'><h2>ম্যাচটি খুঁজে পাওয়া যায়নি অথবা লাইভ হয়ে গেছে!</h2></div>");
}
$match = $match_result->fetch_assoc();

// Get User Balance
$bal_sql = "SELECT balance FROM users WHERE id = ?";
$bal_stmt = $conn->prepare($bal_sql);
$bal_stmt->bind_param("i", $user_id);
$bal_stmt->execute();
$balance = $bal_stmt->get_result()->fetch_assoc()['balance'];
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>বেট প্লেস করুন - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-main: #0B0E14;
            --bg-card: #151A22;
            --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-primary: #00E701;
            --accent-hover: #00C801;
            --accent-blue: #007BFF;
            --text-main: #FFFFFF;
            --text-muted: #8B94A3;
            --border-color: #242B38;
        }

        body { 
            background-color: var(--bg-main); 
            color: var(--text-main); 
            font-family: 'Noto Sans Bengali', 'Inter', sans-serif; /* Bangla Priority */
            margin: 0; background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%);
            min-height: 100vh; padding-bottom: 50px;
        }

        .navbar { 
            background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 5%; 
            display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); 
            position: sticky; top: 0; z-index: 10;
        }
        .navbar .back-btn { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 15px; transition: 0.3s; }
        .navbar .back-btn:hover { color: var(--text-main); }
        .balance-badge { background: rgba(0, 231, 1, 0.1); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 600; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); font-family: 'Inter', sans-serif;}

        .bet-container { max-width: 600px; margin: 40px auto; background: var(--bg-card); padding: 30px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.4); border: 1px solid var(--border-color); }
        
        .match-header { text-align: center; margin-bottom: 25px; }
        .match-header h3 { margin: 0 0 8px 0; font-size: 22px; font-weight: 800; color: var(--text-main); font-family: 'Inter', sans-serif; }
        .match-time { font-size: 13px; font-weight: 600; color: #F59E0B; background: rgba(245, 158, 11, 0.1); padding: 6px 15px; border-radius: 20px; display: inline-block; border: 1px solid rgba(245, 158, 11, 0.2); }

        /* Market Tabs */
        .market-tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; overflow-x: auto; }
        .market-tab { 
            flex: 1; text-align: center; padding: 12px; color: var(--text-muted); cursor: pointer; 
            font-weight: 800; font-size: 15px; border-radius: 8px; transition: 0.3s; white-space: nowrap;
        }
        .market-tab.active { background: rgba(0, 123, 255, 0.1); color: var(--accent-blue); border: 1px solid rgba(0, 123, 255, 0.3); box-shadow: inset 0 0 15px rgba(0, 123, 255, 0.1); }

        .market-content { display: none; animation: fadeIn 0.4s ease; }
        .market-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Match Winner Layout */
        .winner-selection { display: flex; gap: 15px; margin-bottom: 25px; }
        .winner-btn {
            flex: 1; padding: 20px 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color);
            border-radius: 12px; text-align: center; cursor: pointer; transition: 0.3s; position: relative;
        }
        .winner-btn input[type="radio"] { display: none; }
        .winner-btn .name { display: block; font-weight: 800; font-size: 15px; margin-bottom: 8px; color: var(--text-main); font-family: 'Inter', 'Noto Sans Bengali', sans-serif;}
        .winner-btn .odds { display: block; font-size: 13px; color: var(--accent-primary); font-weight: 800; background: rgba(0, 231, 1, 0.1); padding: 4px; border-radius: 4px; width: max-content; margin: 0 auto; font-family: 'Inter', sans-serif;}
        .winner-btn.selected { background: rgba(0, 123, 255, 0.1); border-color: var(--accent-blue); box-shadow: 0 0 20px rgba(0, 123, 255, 0.3); transform: translateY(-3px); }
        .winner-btn.selected .name { color: var(--accent-blue); }

        /* Exact Score Layout */
        .score-inputs { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: rgba(0,0,0,0.2); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); }
        .team-input { display: flex; flex-direction: column; align-items: center; width: 40%; }
        .team-input label { font-weight: 800; margin-bottom: 15px; text-align: center; color: var(--text-main); font-size: 15px; font-family: 'Inter', sans-serif;}
        .team-input input { 
            width: 80px; height: 80px; text-align: center; font-size: 32px; font-weight: 800; 
            background: #0B0E14; border: 2px solid var(--border-color); color: var(--accent-primary); 
            border-radius: 12px; outline: none; transition: 0.3s; box-shadow: inset 0 2px 10px rgba(0,0,0,0.5); font-family: 'Inter', sans-serif;
        }
        .team-input input:focus { border-color: var(--accent-primary); box-shadow: 0 0 15px rgba(0, 231, 1, 0.3); }
        .vs { font-size: 20px; font-weight: 800; color: var(--text-muted); background: var(--bg-card); padding: 10px; border-radius: 50%; border: 1px solid var(--border-color); font-family: 'Noto Sans Bengali', sans-serif;}

        /* Stake Input */
        .stake-section { margin-bottom: 25px; }
        .stake-section label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 14px; }
        .stake-input-wrapper { position: relative; }
        .stake-input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #F59E0B; font-size: 18px; }
        .stake-section input { 
            width: 100%; padding: 16px 16px 16px 45px; border: 1px solid var(--border-color); 
            border-radius: 8px; background: #0B0E14; color: var(--text-main); font-size: 18px; font-weight: bold; 
            box-sizing: border-box; outline: none; transition: 0.3s; font-family: 'Inter', sans-serif;
        }
        .stake-section input:focus { border-color: #F59E0B; box-shadow: 0 0 10px rgba(245, 158, 11, 0.1); }
        
        /* Submit Button */
        .btn { 
            width: 100%; padding: 16px; background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); 
            color: #000; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 800; 
            transition: 0.3s; letter-spacing: 0.5px; display: flex; justify-content: center; align-items: center; gap: 8px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 231, 1, 0.4); }
        .btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> হোমপেজে ফিরুন</a>
        <div class="balance-badge">
            <i class="fa-solid fa-coins" style="color: #F59E0B;"></i> <span id="navBalance"><?php echo number_format($balance, 2); ?></span>
        </div>
    </nav>

    <div class="bet-container">
        <div class="match-header">
            <h3><?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></h3>
            <div class="match-time"><i class="fa-regular fa-clock"></i> প্রেডিকশন লক হবে: <?php echo date("d M, h:i A", strtotime($match['match_time'])); ?></div>
        </div>

        <div class="market-tabs">
            <div class="market-tab active" onclick="switchMarket('match_winner', this)">ম্যাচ উইনার (1X2)</div>
            <div class="market-tab" onclick="switchMarket('exact_score', this)">সঠিক গোল (Exact Score)</div>
        </div>

        <form id="betForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="bet_type" id="betType" value="match_winner">

            <div id="market_match_winner" class="market-content active">
                <div class="winner-selection">
                    <label class="winner-btn" onclick="selectWinnerBtn(this)">
                        <input type="radio" name="winner_selection" value="team1">
                        <span class="name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                        <span class="odds">2.00x</span>
                    </label>
                    <label class="winner-btn" onclick="selectWinnerBtn(this)">
                        <input type="radio" name="winner_selection" value="draw">
                        <span class="name">ড্র (Draw)</span>
                        <span class="odds">3.50x</span>
                    </label>
                    <label class="winner-btn" onclick="selectWinnerBtn(this)">
                        <input type="radio" name="winner_selection" value="team2">
                        <span class="name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                        <span class="odds">1.80x</span>
                    </label>
                </div>
            </div>

            <div id="market_exact_score" class="market-content">
                <div class="score-inputs">
                    <div class="team-input">
                        <label><?php echo htmlspecialchars($match['team1_name']); ?></label>
                        <input type="number" id="score1" name="score1" min="0" placeholder="0">
                    </div>
                    <div class="vs">বনাম</div>
                    <div class="team-input">
                        <label><?php echo htmlspecialchars($match['team2_name']); ?></label>
                        <input type="number" id="score2" name="score2" min="0" placeholder="0">
                    </div>
                </div>
            </div>

            <div class="stake-section">
                <label>বেট এমাউন্ট (কয়েন)</label>
                <div class="stake-input-wrapper">
                    <i class="fa-solid fa-coins"></i>
                    <input type="number" name="stake_amount" step="0.01" min="1" required placeholder="যেমন: 100">
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn">বেট প্লেস করুন <i class="fa-solid fa-paper-plane"></i></button>
        </form>
    </div>

    <script>
        // Market Tab Switching
        function switchMarket(marketId, el) {
            document.querySelectorAll('.market-tab').forEach(tab => tab.classList.remove('active'));
            el.classList.add('active');
            
            document.querySelectorAll('.market-content').forEach(content => content.classList.remove('active'));
            document.getElementById('market_' + marketId).classList.add('active');
            
            document.getElementById('betType').value = marketId;

            if(marketId === 'exact_score') {
                document.getElementById('score1').required = true;
                document.getElementById('score2').required = true;
            } else {
                document.getElementById('score1').required = false;
                document.getElementById('score2').required = false;
            }
        }

        // Winner Selection Styling
        function selectWinnerBtn(el) {
            document.querySelectorAll('.winner-btn').forEach(btn => btn.classList.remove('selected'));
            el.classList.add('selected');
        }

        // AJAX Form Submission
        document.getElementById('betForm').addEventListener('submit', function(e) {
            e.preventDefault(); 
            
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> প্রসেস হচ্ছে...';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('ajax_bet', '1'); 
            formData.append('match_id', '<?php echo $match_id; ?>');

            fetch('place_bet.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;

                if (data.status === 'success') {
                    // Update Balance instantly
                    document.getElementById('navBalance').innerText = data.new_balance;
                    
                    Swal.fire({
                        title: 'বেট কনফার্মড!',
                        text: data.message,
                        icon: 'success',
                        background: '#151A22',
                        color: '#fff',
                        confirmButtonColor: '#00E701',
                        iconColor: '#00E701'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                } else {
                    Swal.fire({
                        title: 'বেট ফেইলড!',
                        text: data.message,
                        icon: 'error',
                        background: '#151A22',
                        color: '#fff',
                        confirmButtonColor: '#FF3C3C'
                    });
                }
            })
            .catch(error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                Swal.fire({
                    title: 'সিস্টেম এরর',
                    text: 'সার্ভারে কোনো সমস্যা হয়েছে। একটু পর আবার চেষ্টা করুন।',
                    icon: 'error',
                    background: '#151A22',
                    color: '#fff',
                    confirmButtonColor: '#FF3C3C'
                });
            });
        });
    </script>
</body>
</html>