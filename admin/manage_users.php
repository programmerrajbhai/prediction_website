<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:red; text-align:center;'>Access Denied!</h2>");
}

$msg = '';

// ইউজার অ্যাকশন (Ban/Unban/Balance Update) হ্যান্ডেল করা
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = intval($_POST['user_id']);

    if (isset($_POST['update_balance'])) {
        $new_balance = floatval($_POST['balance']);
        $sql = "UPDATE users SET balance = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $new_balance, $user_id);
        if ($stmt->execute()) $msg = "<div class='success'>Balance updated successfully!</div>";
    }

    if (isset($_POST['toggle_status'])) {
        $new_status = ($_POST['current_status'] == 'active') ? 'banned' : 'active';
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) $msg = "<div class='success'>User status updated to $new_status!</div>";
    }
}

// ইউজার লিস্ট আনা
$users = $conn->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-main: #0B0E14; --bg-card: #151A22; --accent-admin: #FF3C3C; --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; }
        .admin-container { max-width: 1000px; margin: 40px auto; background: rgba(21, 26, 34, 0.8); backdrop-filter: blur(15px); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); }
        h2 { text-align: center; color: var(--text-main); margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-size: 12px; text-transform: uppercase; }
        
        .btn { padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; color: white; transition: 0.3s; }
        .btn-update { background: #3b82f6; }
        .btn-ban { background: #ef4444; }
        .btn-active { background: #22c55e; }
        
        .success { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid rgba(0, 231, 1, 0.2); }
    </style>
</head>
<body>

    <div class="admin-container">
        <a href="index.php" style="color:var(--text-muted); text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <h2>👥 User Management</h2>
        <?php echo $msg; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <form action="" method="POST" style="display:flex; gap:5px;">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <input type="number" name="balance" step="0.01" value="<?php echo $row['balance']; ?>" style="width:80px; background:#0B0E14; border:1px solid #334155; color:white; padding:5px; border-radius:4px;">
                            <button type="submit" name="update_balance" class="btn btn-update">Save</button>
                        </form>
                    </td>
                    <td><?php echo $row['status']; ?></td>
                    <td>
                        <form action="" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $row['status']; ?>">
                            <button type="submit" name="toggle_status" class="btn <?php echo ($row['status'] == 'active') ? 'btn-ban' : 'btn-active'; ?>">
                                <?php echo ($row['status'] == 'active') ? 'Ban User' : 'Unban User'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</body>
</html>