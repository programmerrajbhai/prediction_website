<?php
require_once 'config.php';
require_once 'functions.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) {
    if(isset($_POST['ajax_deposit'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please login first.']); exit;
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
// ৩. AJAX POST Request Handle
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_deposit'])) {
    header('Content-Type: application/json');
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'Security Error! Invalid Token.']); exit;
    }

    $amount = floatval($_POST['amount']);
    $method = sanitizeInput($_POST['payment_method'], $conn);
    $trx_id = sanitizeInput($_POST['transaction_id'], $conn);

    if ($amount < 50) {
        echo json_encode(['status' => 'error', 'message' => 'Minimum deposit amount is 50 Coins.']); exit;
    }

    $insert_sql = "INSERT INTO deposits (user_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("idss", $user_id, $amount, $method, $trx_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Deposit request submitted successfully! Pending admin approval.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
    exit;
}

// ==========================================
// ৪. Normal Page Load
// ==========================================
$bal_sql = "SELECT balance FROM users WHERE id = ?";
$bal_stmt = $conn->prepare($bal_sql);
$bal_stmt->bind_param("i", $user_id);
$bal_stmt->execute();
$current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Coins - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); --accent-primary: #00E701; --accent-blue: #007BFF; --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; padding-bottom: 50px; background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%); min-height: 100vh; }
        
        .navbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 10; }
        .navbar .back-btn { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 16px; transition: 0.3s; }
        .navbar .back-btn:hover { color: var(--text-main); }
        .balance-badge { background: rgba(0, 231, 1, 0.1); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 600; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); }

        .finance-container { max-width: 500px; margin: 40px auto; background: var(--bg-card); padding: 35px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.4); border: 1px solid var(--border-color); }
        .header-box { text-align: center; margin-bottom: 25px; }
        .header-box i { font-size: 40px; color: var(--accent-primary); margin-bottom: 10px; }
        .header-box h2 { margin: 0 0 5px 0; font-size: 24px; font-weight: 800; }
        .header-box p { color: var(--text-muted); font-size: 13px; margin: 0; }

        .instructions { background: rgba(0, 123, 255, 0.05); padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; border: 1px dashed rgba(0, 123, 255, 0.3); font-size: 13px; color: var(--text-main); line-height: 1.6; }
        .instructions span { color: var(--accent-primary); font-weight: 800; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 13px; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); transition: 0.3s; }
        .input-wrapper input, .input-wrapper select { width: 100%; padding: 14px 14px 14px 45px; border: 1px solid var(--border-color); border-radius: 8px; background: #0B0E14; color: var(--text-main); font-size: 15px; font-family: 'Inter', sans-serif; outline: none; transition: 0.3s; box-sizing: border-box; }
        .input-wrapper input:focus, .input-wrapper select:focus { border-color: var(--accent-primary); box-shadow: 0 0 10px rgba(0, 231, 1, 0.1); }
        .input-wrapper input:focus + i, .input-wrapper select:focus + i { color: var(--accent-primary); }

        .btn { width: 100%; padding: 15px; background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); color: #000; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 800; text-transform: uppercase; transition: 0.3s; margin-top: 10px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 231, 1, 0.4); }
        .btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Home</a>
        <div class="balance-badge"><i class="fa-solid fa-coins" style="color: #F59E0B;"></i> <?php echo number_format($current_balance, 2); ?></div>
    </nav>

    <div class="finance-container">
        <div class="header-box">
            <i class="fa-solid fa-wallet"></i>
            <h2>Top Up Wallet</h2>
            <p>Add coins to your account to place bets</p>
        </div>

        <div class="instructions">
            1. Send money to our <span>bKash/Nagad</span> Personal Number: <br><strong style="font-size: 16px;">017XX-XXXXXX</strong><br>
            2. Minimum deposit is <strong>50 BDT (50 Coins)</strong>.<br>
            3. Copy the Transaction ID and submit below.
        </div>

        <form id="depositForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="input-group">
                <label>Deposit Amount (Coins)</label>
                <div class="input-wrapper">
                    <input type="number" name="amount" min="50" required placeholder="e.g. 100">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>

            <div class="input-group">
                <label>Payment Method</label>
                <div class="input-wrapper">
                    <select name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="bKash">bKash</option>
                        <option value="Nagad">Nagad</option>
                        <option value="Rocket">Rocket</option>
                    </select>
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>

            <div class="input-group">
                <label>Transaction ID (TrxID)</label>
                <div class="input-wrapper">
                    <input type="text" name="transaction_id" required placeholder="Enter the 10-digit TrxID">
                    <i class="fa-solid fa-hashtag"></i>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn">Submit Request <i class="fa-solid fa-paper-plane" style="margin-left: 5px;"></i></button>
        </form>
    </div>

    <script>
        document.getElementById('depositForm').addEventListener('submit', function(e) {
            e.preventDefault(); 
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('ajax_deposit', '1'); 

            fetch('deposit.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;

                if (data.status === 'success') {
                    document.getElementById('depositForm').reset();
                    Swal.fire({ title: 'Request Sent!', text: data.message, icon: 'success', background: '#151A22', color: '#fff', confirmButtonColor: '#00E701', iconColor: '#00E701' });
                } else {
                    Swal.fire({ title: 'Failed', text: data.message, icon: 'error', background: '#151A22', color: '#fff', confirmButtonColor: '#FF3C3C' });
                }
            })
            .catch(error => {
                btn.innerHTML = originalText; btn.disabled = false;
                Swal.fire({ title: 'System Error', text: 'Something went wrong.', icon: 'error', background: '#151A22', color: '#fff', confirmButtonColor: '#FF3C3C' });
            });
        });
    </script>

</body>
</html>