<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক: শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied! Admins only.</h2>");
}

$msg = '';

// যদি অ্যাডমিন Approve বা Reject বাটনে ক্লিক করে
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $deposit_id = intval($_POST['deposit_id']);
    $action = $_POST['action'];

    // প্রথমে চেক করব ডিপোজিটটি সত্যিই 'pending' অবস্থায় আছে কি না
    $check_sql = "SELECT user_id, amount, status FROM deposits WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $deposit_id);
    $check_stmt->execute();
    $dep_result = $check_stmt->get_result();

    if ($dep_result->num_rows == 1) {
        $deposit = $dep_result->fetch_assoc();
        
        if ($deposit['status'] == 'pending') {
            if ($action == 'approve') {
                // ট্রানজেকশন শুরু: স্ট্যাটাস আপডেট এবং ব্যালেন্স যোগ করা হবে
                $conn->begin_transaction();
                try {
                    // ১. ডিপোজিটের স্ট্যাটাস 'approved' করা
                    $upd_dep = "UPDATE deposits SET status = 'approved' WHERE id = ?";
                    $stmt1 = $conn->prepare($upd_dep);
                    $stmt1->bind_param("i", $deposit_id);
                    $stmt1->execute();

                    // ২. ইউজারের একাউন্টে ব্যালেন্স যোগ করা
                    $upd_bal = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $stmt2 = $conn->prepare($upd_bal);
                    $stmt2->bind_param("di", $deposit['amount'], $deposit['user_id']);
                    $stmt2->execute();

                    $conn->commit();
                    $msg = "<div class='success'>Deposit Approved! Balance added to user.</div>";
                } catch (Exception $e) {
                    $conn->rollback();
                    $msg = "<div class='error'>Error updating balance. Action reversed.</div>";
                }
            } elseif ($action == 'reject') {
                // রিজেক্ট করলে শুধু স্ট্যাটাস আপডেট হবে, ব্যালেন্স যোগ হবে না
                $upd_dep = "UPDATE deposits SET status = 'rejected' WHERE id = ?";
                $stmt = $conn->prepare($upd_dep);
                $stmt->bind_param("i", $deposit_id);
                if ($stmt->execute()) {
                    $msg = "<div class='success' style='color:#ef4444; border-color:#ef4444;'>Deposit Request Rejected.</div>";
                }
            }
        } else {
            $msg = "<div class='error'>This request has already been processed!</div>";
        }
    }
}

// ডাটাবেস থেকে সব Pending ডিপোজিট রিকোয়েস্ট নিয়ে আসা
$pending_sql = "
    SELECT d.*, u.username 
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 'pending'
    ORDER BY d.created_at ASC
";
$pending_requests = $conn->query($pending_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Deposits - Admin</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .admin-container { max-width: 900px; margin: 40px auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }
        h2 { text-align: center; color: #3b82f6; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #334155; }
        th { background-color: #0f172a; color: #94a3b8; font-weight: bold; text-transform: uppercase; font-size: 13px; }
        td { font-size: 14px; }
        tr:hover { background-color: #334155; }
        
        .amount { color: #fbbf24; font-weight: bold; }
        
        .action-form { display: inline-block; margin: 0; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; color: white; transition: 0.3s; }
        .btn-approve { background: #22c55e; }
        .btn-approve:hover { background: #16a34a; }
        .btn-reject { background: #ef4444; margin-left: 5px; }
        .btn-reject:hover { background: #dc2626; }
        
        .success { background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #22c55e; }
        .error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #ef4444; }
        
        .back-link { display: inline-block; margin-bottom: 20px; color: #3b82f6; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="admin-container">
        <a href="../index.php" class="back-link">← Back to Homepage</a>
        <h2>💰 Manage Pending Deposits</h2>
        
        <?php echo $msg; ?>

        <?php if($pending_requests->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>TrxID</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $pending_requests->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="amount"><?php echo number_format($row['amount'], 2); ?> Coins</td>
                            <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                            <td style="font-family: monospace; color: #94a3b8;"><?php echo htmlspecialchars($row['transaction_id']); ?></td>
                            <td style="font-size: 12px; color: #94a3b8;"><?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?></td>
                            <td>
                                <form action="" method="POST" class="action-form">
                                    <input type="hidden" name="deposit_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Are you sure you want to APPROVE this deposit?');">Approve</button>
                                </form>
                                
                                <form action="" method="POST" class="action-form">
                                    <input type="hidden" name="deposit_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject" onclick="return confirm('Are you sure you want to REJECT this deposit?');">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; color: #64748b; margin-top: 30px; padding: 20px; background: #0f172a; border-radius: 8px;">
                No pending deposit requests right now. 💤
            </div>
        <?php endif; ?>

    </div>

</body>
</html>