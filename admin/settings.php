<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // এখানে আমরা একটি সেটিংস টেবিল বা ফাইল থেকে ডেটা সেভ করার লজিক লিখব
    $msg = "<div class='success'>Settings Saved!</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Settings - Admin</title>
    <style>
        body { background: #0B0E14; color: white; font-family: 'Inter', sans-serif; padding: 40px; }
        .card { max-width: 500px; margin: auto; background: #151A22; padding: 30px; border-radius: 16px; border: 1px solid #242B38; }
        input { width: 100%; padding: 12px; margin: 10px 0; background: #0B0E14; border: 1px solid #242B38; color: white; border-radius: 6px; }
        .btn { width: 100%; padding: 12px; background: #00E701; border: none; border-radius: 6px; font-weight: 800; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Site Settings</h2>
        <?php echo $msg; ?>
        <form method="POST">
            <label>Min Deposit Limit</label>
            <input type="number" name="min_dep" value="50">
            <label>Min Withdraw Limit</label>
            <input type="number" name="min_with" value="100">
            <button class="btn" type="submit">Update Settings</button>
        </form>
    </div>
</body>
</html>