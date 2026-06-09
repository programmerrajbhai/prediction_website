<?php
require_once 'config.php';
require_once 'functions.php';

// লগইন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// ইউজারের বর্তমান ব্যালেন্স আনা
$bal_sql = "SELECT balance FROM users WHERE id = ?";
$bal_stmt = $conn->prepare($bal_sql);
$bal_stmt->bind_param("i", $user_id);
$bal_stmt->execute();
$current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];

// ফর্ম সাবমিট হলে ডাটাবেসে Pending স্ট্যাটাসে সেভ করার লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_deposit'])) {
    $amount = floatval($_POST['amount']);
    $method = sanitizeInput($_POST['payment_method'], $conn);
    $trx_id = sanitizeInput($_POST['transaction_id'], $conn);

    if ($amount < 50) {
        $msg = "<div class='error'>Minimum deposit amount is 50 Coins.</div>";
    } else {
        $insert_sql = "INSERT INTO deposits (user_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("idss", $user_id, $amount, $method, $trx_id);
        
        if ($stmt->execute()) {
            $msg = "<div class='success'>Deposit request submitted successfully! Please wait for admin approval.</div>";
        } else {
            $msg = "<div class='error'>Something went wrong. Please try again.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Coins - Prediction Web</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .navbar { background-color: #1e293b; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .navbar a { color: #3b82f6; text-decoration: none; font-weight: bold; }
        .balance-badge { background-color: #334155; padding: 8px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; color: #fbbf24; border: 1px solid #475569; }
        
        .deposit-container { max-width: 500px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); border: 1px solid #334155; }
        .deposit-container h2 { text-align: center; color: #cbd5e1; margin-bottom: 5px; }
        .instructions { background: #0f172a; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #94a3b8; border: 1px dashed #475569; }
        .instructions span { color: #22c55e; font-weight: bold; }
        
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; color: #94a3b8; font-weight: bold; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; outline: none; box-sizing: border-box; font-size: 15px; }
        .input-group input:focus, .input-group select:focus { border-color: #3b82f6; }
        
        .btn { width: 100%; padding: 15px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; margin-top: 10px; }
        .btn:hover { background: #2563eb; }
        
        .error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #ef4444; font-size: 14px; }
        .success { background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #22c55e; font-size: 14px; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">← Back to Feed</a>
        <div class="balance-badge">🪙 <span><?php echo number_format($current_balance, 2); ?></span> Coins</div>
    </div>

    <div class="deposit-container">
        <h2>Add Funds</h2>
        <p style="text-align: center; color: #64748b; font-size: 14px; margin-bottom: 20px;">Top up your wallet to place predictions.</p>

        <div class="instructions">
            1. Send money to our <span>bKash/Nagad</span> Personal Number: <strong>017XX-XXXXXX</strong><br>
            2. Minimum deposit is <strong>50 BDT (50 Coins)</strong>.<br>
            3. Copy the Transaction ID and submit the form below.
        </div>

        <?php echo $msg; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Amount (Coins)</label>
                <input type="number" name="amount" min="50" required placeholder="e.g. 100">
            </div>

            <div class="input-group">
                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="">Select Method</option>
                    <option value="bKash">bKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Rocket">Rocket</option>
                </select>
            </div>

            <div class="input-group">
                <label>Transaction ID (TrxID)</label>
                <input type="text" name="transaction_id" required placeholder="Enter the 10-digit TrxID">
            </div>

            <button type="submit" name="submit_deposit" class="btn">Submit Deposit Request</button>
        </form>
    </div>

</body>
</html>