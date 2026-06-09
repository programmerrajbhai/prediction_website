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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_withdraw'])) {
    $amount = floatval($_POST['amount']);
    $method = sanitizeInput($_POST['payment_method'], $conn);
    $account_no = sanitizeInput($_POST['account_number'], $conn);

    if ($amount < 100) {
        $msg = "<div class='error'>Minimum withdrawal amount is 100 Coins.</div>";
    } elseif ($amount > $current_balance) {
        $msg = "<div class='error'>Insufficient Balance! You only have " . number_format($current_balance, 2) . " Coins.</div>";
    } else {
        // ডাটাবেস ট্রানজেকশন শুরু
        $conn->begin_transaction();
        
        try {
            // ১. ইউজারের ব্যালেন্স কাটা
            $new_balance = $current_balance - $amount;
            $upd_bal = "UPDATE users SET balance = ? WHERE id = ?";
            $stmt_bal = $conn->prepare($upd_bal);
            $stmt_bal->bind_param("di", $new_balance, $user_id);
            $stmt_bal->execute();

            // ২. উইথড্র টেবিলে এন্ট্রি করা
            $ins_with = "INSERT INTO withdrawals (user_id, amount, payment_method, account_number) VALUES (?, ?, ?, ?)";
            $stmt_with = $conn->prepare($ins_with);
            $stmt_with->bind_param("idss", $user_id, $amount, $method, $account_no);
            $stmt_with->execute();

            $conn->commit();
            $msg = "<div class='success'>Withdrawal request submitted! Amount deducted from balance.</div>";
            $current_balance = $new_balance; // UI আপডেট করার জন্য
        } catch (Exception $e) {
            $conn->rollback();
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
    <title>Withdraw Coins - Prediction Web</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .navbar { background-color: #1e293b; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .navbar a { color: #3b82f6; text-decoration: none; font-weight: bold; }
        .balance-badge { background-color: #334155; padding: 8px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; color: #fbbf24; border: 1px solid #475569; }
        
        .withdraw-container { max-width: 500px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); border: 1px solid #334155; }
        .withdraw-container h2 { text-align: center; color: #cbd5e1; margin-bottom: 5px; }
        
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; color: #94a3b8; font-weight: bold; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; outline: none; box-sizing: border-box; font-size: 15px; }
        .input-group input:focus, .input-group select:focus { border-color: #3b82f6; }
        
        .btn { width: 100%; padding: 15px; background: #22c55e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; margin-top: 10px; }
        .btn:hover { background: #16a34a; }
        
        .error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #ef4444; font-size: 14px; }
        .success { background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #22c55e; font-size: 14px; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php">← Back to Feed</a>
        <div class="balance-badge">🪙 <span><?php echo number_format($current_balance, 2); ?></span> Coins</div>
    </div>

    <div class="withdraw-container">
        <h2>Cash Out Winnings</h2>
        <p style="text-align: center; color: #64748b; font-size: 14px; margin-bottom: 20px;">Withdraw your coins to your mobile banking account.</p>

        <?php echo $msg; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Amount (Coins)</label>
                <input type="number" name="amount" min="100" max="<?php echo $current_balance; ?>" required placeholder="Min. 100 Coins">
            </div>

            <div class="input-group">
                <label>Receive Method</label>
                <select name="payment_method" required>
                    <option value="">Select Method</option>
                    <option value="bKash">bKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Rocket">Rocket</option>
                </select>
            </div>

            <div class="input-group">
                <label>Account Number</label>
                <input type="text" name="account_number" required placeholder="Enter your mobile number">
            </div>

            <button type="submit" name="submit_withdraw" class="btn">Submit Request</button>
        </form>
    </div>

</body>
</html>