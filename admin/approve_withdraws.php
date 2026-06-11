<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// অ্যাডমিন এক্সেস চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:#FF3C3C; text-align:center;'>Access Denied!</h2>");
}

$msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $withdraw_id = intval($_POST['withdraw_id']);
    $action = $_POST['action'];

    $check_sql = "SELECT user_id, amount, status FROM withdrawals WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $withdraw_id);
    $check_stmt->execute();
    $with_result = $check_stmt->get_result();

    if ($with_result->num_rows == 1) {
        $withdraw = $with_result->fetch_assoc();
        
        if ($withdraw['status'] == 'pending') {
            if ($action == 'approve') {
                // অ্যাপ্রুভ করলে শুধু স্ট্যাটাস আপডেট হবে (টাকা ইউজারকে ম্যানুয়ালি পাঠাতে হবে)
                $upd_sql = "UPDATE withdrawals SET status = 'approved' WHERE id = ?";
                $stmt = $conn->prepare($upd_sql);
                $stmt->bind_param("i", $withdraw_id);
                if ($stmt->execute()) {
                    $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> উইথড্র অ্যাপ্রুভ করা হয়েছে! আশা করি আপনি ইউজারকে টাকা পাঠিয়েছেন।</div>";
                }
            } elseif ($action == 'reject') {
                // ট্রানজেকশন শুরু: রিজেক্ট করা এবং ব্যালেন্স রিফান্ড করা
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
                    $msg = "<div class='success-msg' style='color:#FF3C3C; border-color:rgba(255,60,60,0.3); background:rgba(255,60,60,0.1);'><i class='fa-solid fa-arrow-rotate-left'></i> রিকোয়েস্ট রিজেক্ট করা হয়েছে এবং কয়েন ইউজারের একাউন্টে রিফান্ড করা হয়েছে।</div>";
                } catch (Exception $e) {
                    $conn->rollback();
                    $msg = "<div class='error-msg'>সিস্টেম এরর! রিকোয়েস্ট প্রসেস করা যায়নি।</div>";
                }
            }
        } else {
            $msg = "<div class='error-msg'>এই রিকোয়েস্টটি আগেই প্রসেস করা হয়েছে!</div>";
        }
    }
}

// Pending রিকোয়েস্টগুলো আনা
$pending_sql = "SELECT w.*, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' ORDER BY w.created_at ASC";
$pending_requests = $conn->query($pending_sql);

// সাইডবার ব্যাজের জন্য কাউন্ট
$with_count = $pending_requests->num_rows;
$dep_count = $conn->query("SELECT id FROM deposits WHERE status = 'pending'")->num_rows;
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>উইথড্র অ্যাপ্রুভাল - Admin PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); --accent-admin: #FF3C3C; --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38; --sidebar-width: 280px; }
        body { background-color: var(--bg-main); color: var(--text-main); font-family: 'Noto Sans Bengali', 'Inter', sans-serif; margin: 0; display: flex; min-height: 100vh; overflow-x: hidden; background-image: radial-gradient(circle at 50% -20%, #2a1111, var(--bg-main) 60%); }
        
        /* Sidebar & Topbar */
        .sidebar { width: var(--sidebar-width); background: var(--bg-card); border-right: 1px solid var(--border-color); height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000; display: flex; flex-direction: column; transition: 0.3s; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .sidebar-header i { font-size: 28px; color: var(--accent-admin); }
        .sidebar-header h2 { margin: 0; font-size: 24px; font-weight: 800; font-family: 'Inter'; letter-spacing: -1px; }
        .nav-menu { list-style: none; padding: 20px 15px; margin: 0; overflow-y: auto; }
        .nav-title { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin: 15px 0 10px 10px; font-family: 'Inter'; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.3s; margin-bottom: 5px;}
        .nav-link i { width: 20px; text-align: center; font-size: 16px; }
        .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
        .nav-link.active { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-left: 3px solid var(--accent-admin); }
        .badge { background: var(--accent-admin); color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 11px; margin-left: auto; font-weight: 800; }
        .badge-warning { background: #F59E0B; }
        
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - var(--sidebar-width)); }
        .topbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; }
        .mobile-toggle { display: none; background: none; border: none; color: var(--text-main); font-size: 24px; cursor: pointer; }
        .logout-btn { padding: 8px 15px; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .logout-btn:hover { background: rgba(255,60,60,0.1); color: var(--accent-admin); border-color: var(--accent-admin); }

        /* Dashboard */
        .dashboard-container { padding: 30px; }
        .page-title { font-size: 24px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: #00E701; }
        
        .card { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 800; text-transform: uppercase; font-size: 12px; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .amount { color: #00E701; font-weight: 800; font-family: 'Inter'; font-size: 16px;}
        .acc-no { font-family: monospace; color: var(--text-muted); background: #0B0E14; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); font-size: 15px; font-weight: bold;}
        
        .action-btns { display: flex; gap: 8px; }
        .btn-action { padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; color: white; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 5px;}
        .btn-approve { background: rgba(0, 231, 1, 0.1); color: #00E701; border: 1px solid rgba(0, 231, 1, 0.3); }
        .btn-approve:hover { background: #00E701; color: #000; box-shadow: 0 4px 10px rgba(0, 231, 1, 0.3); }
        .btn-reject { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.3); }
        .btn-reject:hover { background: #FF3C3C; color: white; box-shadow: 0 4px 10px rgba(255, 60, 60, 0.3); }

        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(0, 231, 1, 0.2); }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(255, 60, 60, 0.2); }

        @media (max-width: 992px) { .sidebar { left: -300px; } .sidebar.active { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.8); } .main-content { margin-left: 0; width: 100%; } .mobile-toggle { display: block; } }
    </style>
</head>
<body>

    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header"><i class="fa-solid fa-shield-halved"></i><h2>Admin Panel</h2></div>
        <ul class="nav-menu">
            <div class="nav-title">মেইন</div>
            <a href="index.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> ড্যাশবোর্ড</a>
            
            <div class="nav-title">ম্যানেজমেন্ট</div>
            <a href="manage_matches.php" class="nav-link"><i class="fa-solid fa-earth-americas"></i> ম্যাচ ও দেশ ম্যানেজ</a>
            <a href="update_result.php" class="nav-link"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a>
            <a href="manage_users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> ইউজার ম্যানেজমেন্ট</a>

            <div class="nav-title">ফাইন্যান্স (অর্থ)</div>
            <a href="approve_deposits.php" class="nav-link">
                <i class="fa-solid fa-money-bill-transfer"></i> ডিপোজিট রিকোয়েস্ট
                <?php if($dep_count > 0): ?><span class="badge badge-warning"><?php echo $dep_count; ?></span><?php endif; ?>
            </a>
            <a href="approve_withdraws.php" class="nav-link active">
                <i class="fa-solid fa-hand-holding-dollar"></i> উইথড্র রিকোয়েস্ট
                <?php if($with_count > 0): ?><span class="badge"><?php echo $with_count; ?></span><?php endif; ?>
            </a>
        </ul>
    </aside>

    <div style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; display:none;" id="mobileOverlay"></div>

    <div class="main-content">
        <header class="topbar">
            <button class="mobile-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <div style="flex-grow: 1;"></div>
            <a href="../logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> লগআউট</a>
        </header>

        <div class="dashboard-container">
            <div class="page-title"><i class="fa-solid fa-hand-holding-dollar"></i> পেন্ডিং উইথড্র রিকোয়েস্ট</div>
            
            <?php echo $msg; ?>

            <div class="card">
                <div class="table-responsive">
                    <?php if($pending_requests->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ইউজার</th>
                                    <th>পরিমাণ (টাকা)</th>
                                    <th>রিসিভ মেথড</th>
                                    <th>একাউন্ট নম্বর</th>
                                    <th>সময় ও তারিখ</th>
                                    <th>অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $pending_requests->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 800; color:var(--text-main);"><i class="fa-regular fa-user" style="color:var(--text-muted);"></i> <?php echo htmlspecialchars($row['username']); ?></td>
                                        <td class="amount">-<?php echo number_format($row['amount'], 2); ?></td>
                                        <td><span style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                                        <td><span class="acc-no"><?php echo htmlspecialchars($row['account_number']); ?></span></td>
                                        <td style="font-size: 12px; color: var(--text-muted);"><?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <form id="approve_form_<?php echo $row['id']; ?>" action="" method="POST" style="margin:0;">
                                                    <input type="hidden" name="withdraw_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="button" class="btn-action btn-approve" onclick="confirmAction(<?php echo $row['id']; ?>, 'approve')"><i class="fa-solid fa-check"></i> পেইড</button>
                                                </form>
                                                
                                                <form id="reject_form_<?php echo $row['id']; ?>" action="" method="POST" style="margin:0;">
                                                    <input type="hidden" name="withdraw_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="button" class="btn-action btn-reject" onclick="confirmAction(<?php echo $row['id']; ?>, 'reject')"><i class="fa-solid fa-xmark"></i> রিজেক্ট ও রিফান্ড</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                            <i class="fa-solid fa-mug-hot" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>এই মুহূর্তে কোনো পেন্ডিং উইথড্র রিকোয়েস্ট নেই।</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('mobileOverlay');
        function toggleSidebar() { sidebar.classList.toggle('active'); overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none'; }
        toggleBtn.addEventListener('click', toggleSidebar); overlay.addEventListener('click', toggleSidebar);

        function confirmAction(id, actionType) {
            let isApprove = actionType === 'approve';
            let titleText = isApprove ? 'টাকা কি পাঠানো হয়েছে?' : 'উইথড্র রিজেক্ট করবেন?';
            let msgText = isApprove 
                ? "অ্যাপ্রুভ করার আগে নিশ্চিত করুন যে আপনি ইউজারের মোবাইল ব্যাংকিং নাম্বারে টাকা পাঠিয়েছেন।" 
                : "এই রিকোয়েস্টটি বাতিল করা হবে এবং কেটে নেওয়া কয়েন পুনরায় ইউজারের একাউন্টে রিফান্ড করা হবে।";
            let btnColor = isApprove ? '#00E701' : '#FF3C3C';
            let btnText = isApprove ? 'হ্যাঁ, আমি টাকা পাঠিয়েছি' : 'হ্যাঁ, রিজেক্ট ও রিফান্ড করুন';

            Swal.fire({
                title: titleText, text: msgText, icon: 'warning', showCancelButton: true,
                background: '#151A22', color: '#fff',
                confirmButtonColor: btnColor, cancelButtonColor: '#334155',
                confirmButtonText: btnText, cancelButtonText: 'বাতিল'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(actionType + '_form_' + id).submit();
                }
            });
        }
    </script>
</body>
</html>