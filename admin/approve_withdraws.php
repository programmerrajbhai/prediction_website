<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// অ্যাডমিন এক্সেস চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:red; text-align:center;'>Access Denied!</h2>");
}

$msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $withdraw_id = intval($_POST['withdraw_id']);
    $action = $_POST['action'];

    // উইথড্র স্ট্যাটাস চেক
    $check_sql = "SELECT user_id, amount, status FROM withdrawals WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $withdraw_id);
    $check_stmt->execute();
    $with_result = $check_stmt->get_result();

    if ($with_result->num_rows == 1) {
        $withdraw = $with_result->fetch_assoc();
        
        if ($withdraw['status'] == 'pending') {
            if ($action == 'approve') {
                // অ্যাপ্রুভ করলে শুধু স্ট্যাটাস আপডেট হবে (টাকা ইউজারকে ম্যানুয়ালি পাঠাতে হবে)
                $upd_sql = "UPDATE withdrawals SET status = 'approved' WHERE id = ?";
                $stmt = $conn->prepare($upd_sql);
                $stmt->bind_param("i", $withdraw_id);
                if ($stmt->execute()) {
                    $msg = "<div class='success'>Withdrawal Approved! Ensure you have sent the money.</div>";
                }
            } elseif ($action == 'reject') {
                // ট্রানজেকশন শুরু: স্ট্যাটাস রিজেক্ট করা এবং ব্যালেন্স রিফান্ড করা
                $conn->begin_transaction();
                try {
                    $upd_sql = "UPDATE withdrawals SET status = 'rejected' WHERE id = ?";
                    $stmt1 = $conn->prepare($upd_sql);
                    $stmt1->bind_param("i", $withdraw_id);
                    $stmt1->execute();

                    $refund_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $stmt2 = $conn->prepare($refund_sql);
                    $stmt2->bind_param("di", $withdraw['amount'], $withdraw['user_id']);
                    $stmt2->execute();

                    $conn->commit();
                    $msg = "<div class='success' style='color:#ef4444; border-color:#ef4444;'>Request Rejected. Amount refunded to user.</div>";
                } catch (Exception $e) {
                    $conn->rollback();
                    $msg = "<div class='error'>Error processing request.</div>";
                }
            }
        } else {
            $msg = "<div class='error'>This request is already processed!</div>";
        }
    }
}

// Pending রিকোয়েস্টগুলো আনা
$pending_sql = "
    SELECT w.*, u.username 
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    WHERE w.status = 'pending'
    ORDER BY w.created_at ASC
";
$pending_requests = $conn->query($pending_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Withdrawals - Admin</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .admin-container { max-width: 900px; margin: 40px auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }
        h2 { text-align: center; color: #3b82f6; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #334155; }
        th { background-color: #0f172a; color: #94a3b8; text-transform: uppercase; font-size: 13px; }
        tr:hover { background-color: #334155; }
        .amount { color: #fbbf24; font-weight: bold; }
        .action-form { display: inline-block; margin: 0; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; color: white; transition: 0.3s; }
        .btn-approve { background: #22c55e; }
        .btn-approve:hover { background: #16a34a; }
        .btn-reject { background: #ef4444; margin-left: 5px; }
        .btn-reject:hover { background: #dc2626; }
        .success, .error { padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; font-size: 14px; }
        .success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid #22c55e; }
        .error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3b82f6; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="admin-container">
        <a href="../index.php" class="back-link">← Back to Homepage</a>
        <h2>💸 Manage Withdrawals</h2>
        
        <?php echo $msg; ?>

        <?php if($pending_requests->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Account No</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $pending_requests->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="amount"><?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                            <td style="font-family: monospace; color: #94a3b8;"><?php echo htmlspecialchars($row['account_number']); ?></td>
                            <td style="font-size: 12px; color: #94a3b8;"><?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?></td>
                            <td>
                                <form action="" method="POST" class="action-form">
                                    <input type="hidden" name="withdraw_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Ensure you have sent the money before approving!');">Approve</button>
                                </form>
                                <form action="" method="POST" class="action-form">
                                    <input type="hidden" name="withdraw_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject" onclick="return confirm('Are you sure? Amount will be refunded to user.');">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; color: #64748b; margin-top: 30px; padding: 20px; background: #0f172a; border-radius: 8px;">
                No pending withdrawal requests right now. 💤
            </div>
        <?php endif; ?>
    </div>

</body>
</html>