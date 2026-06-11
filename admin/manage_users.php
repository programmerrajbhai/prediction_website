<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:#FF3C3C; text-align:center; font-family:sans-serif;'>Access Denied!</h2>");
}

$msg = '';

// ইউজার অ্যাকশন (Ban/Unban/Balance Update) হ্যান্ডেল করা
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = intval($_POST['user_id']);

    // ব্যালেন্স আপডেট লজিক
    if (isset($_POST['update_balance'])) {
        $new_balance = floatval($_POST['balance']);
        $sql = "UPDATE users SET balance = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $new_balance, $user_id);
        if ($stmt->execute()) {
            $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> ইউজারের ব্যালেন্স সফলভাবে আপডেট করা হয়েছে!</div>";
        } else {
            $msg = "<div class='error-msg'>সিস্টেম এরর! ব্যালেন্স আপডেট হয়নি।</div>";
        }
    }

    // স্ট্যাটাস টগল (Ban/Active) লজিক
    if (isset($_POST['toggle_status'])) {
        $new_status = ($_POST['current_status'] == 'active') ? 'banned' : 'active';
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $status_bng = ($new_status == 'banned') ? 'ব্যান (Ban)' : 'অ্যাক্টিভ (Active)';
            $msg = "<div class='success-msg'><i class='fa-solid fa-user-shield'></i> ইউজারের স্ট্যাটাস $status_bng করা হয়েছে!</div>";
        } else {
            $msg = "<div class='error-msg'>সিস্টেম এরর! স্ট্যাটাস আপডেট হয়নি।</div>";
        }
    }
}

// ইউজার লিস্ট আনা
$users = $conn->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইউজার ম্যানেজমেন্ট - Admin PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-admin: #FF3C3C; --accent-admin-hover: #D32F2F; --text-main: #FFFFFF;
            --text-muted: #8B94A3; --border-color: #242B38; --sidebar-width: 280px;
        }

        body { 
            background-color: var(--bg-main); color: var(--text-main); font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding: 0; display: flex; min-height: 100vh; overflow-x: hidden;
            background-image: radial-gradient(circle at 50% -20%, #2a1111, var(--bg-main) 60%);
        }

        /* --- Sidebar & Topbar --- */
        .sidebar { width: var(--sidebar-width); background: var(--bg-card); border-right: 1px solid var(--border-color); height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000; display: flex; flex-direction: column; transition: 0.3s ease; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .sidebar-header i { font-size: 28px; color: var(--accent-admin); }
        .sidebar-header h2 { margin: 0; font-size: 24px; font-weight: 800; font-family: 'Inter', sans-serif; letter-spacing: -1px; }
        .nav-menu { list-style: none; padding: 20px 15px; margin: 0; flex-grow: 1; overflow-y: auto; }
        .nav-title { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin: 15px 0 10px 10px; font-family: 'Inter', sans-serif; }
        .nav-item { margin-bottom: 5px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 8px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.3s; }
        .nav-link i { width: 20px; text-align: center; font-size: 16px; }
        .nav-link:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
        .nav-link.active { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-left: 3px solid var(--accent-admin); }
        
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - var(--sidebar-width)); }
        .topbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; }
        .mobile-toggle { display: none; background: none; border: none; color: var(--text-main); font-size: 24px; cursor: pointer; }
        .logout-btn { padding: 8px 15px; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.3s; }
        .logout-btn:hover { background: rgba(255, 60, 60, 0.1); color: var(--accent-admin); border-color: var(--accent-admin); }

        /* --- Dashboard Area --- */
        .dashboard-container { padding: 30px; }
        .page-title { font-size: 24px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--accent-admin); }

        /* Card & Table */
        .card { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .card-header { font-size: 18px; font-weight: 800; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: var(--text-main); display: flex; justify-content: space-between; align-items: center;}
        .card-header .badge { background: rgba(0, 123, 255, 0.1); color: #007BFF; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-family: 'Inter', sans-serif; border: 1px solid rgba(0, 123, 255, 0.3); }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        /* Balance Form Styles */
        .balance-form { display: flex; align-items: center; gap: 5px; }
        .balance-input { width: 100px; padding: 8px 10px; background: #0B0E14; border: 1px solid var(--border-color); color: #F59E0B; font-weight: 800; border-radius: 6px; outline: none; font-family: 'Inter', sans-serif; transition: 0.3s; }
        .balance-input:focus { border-color: #00E701; box-shadow: 0 0 10px rgba(0, 231, 1, 0.1); }
        .btn-save { padding: 8px 12px; background: rgba(0, 123, 255, 0.1); color: #007BFF; border: 1px solid rgba(0, 123, 255, 0.3); border-radius: 6px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; }
        .btn-save:hover { background: #007BFF; color: white; }

        /* Status & Buttons */
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-block;}
        .bg-active { background: rgba(0, 231, 1, 0.1); color: #00E701; border: 1px solid rgba(0, 231, 1, 0.3); }
        .bg-banned { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.3); }

        .btn-toggle { padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: bold; color: white; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-ban { background: linear-gradient(90deg, #FF3C3C, #D32F2F); box-shadow: 0 4px 10px rgba(255, 60, 60, 0.2); }
        .btn-ban:hover { box-shadow: 0 4px 15px rgba(255, 60, 60, 0.4); transform: translateY(-2px); }
        .btn-unban { background: linear-gradient(90deg, #00E701, #00C801); color: #000; box-shadow: 0 4px 10px rgba(0, 231, 1, 0.2); }
        .btn-unban:hover { box-shadow: 0 4px 15px rgba(0, 231, 1, 0.4); transform: translateY(-2px); }

        /* Messages */
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(0, 231, 1, 0.2); }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(255, 60, 60, 0.2); }

        @media (max-width: 992px) {
            .sidebar { left: -300px; } .sidebar.active { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.8); }
            .main-content { margin-left: 0; width: 100%; } .mobile-toggle { display: block; }
        }
    </style>
</head>
<body>

    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-shield-halved"></i>
            <h2>Admin Panel</h2>
        </div>
        <ul class="nav-menu">
            <div class="nav-title">মেইন</div>
            <li class="nav-item"><a href="index.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> ড্যাশবোর্ড</a></li>
            <li class="nav-item"><a href="../index.php" class="nav-link" target="_blank"><i class="fa-solid fa-globe"></i> মেইন ওয়েবসাইট</a></li>
            
            <div class="nav-title">ম্যানেজমেন্ট</div>
            <li class="nav-item"><a href="manage_matches.php" class="nav-link"><i class="fa-solid fa-earth-americas"></i> ম্যাচ ও দেশ ম্যানেজ</a></li>
            <li class="nav-item"><a href="update_result.php" class="nav-link"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a></li>
            <li class="nav-item"><a href="manage_users.php" class="nav-link active"><i class="fa-solid fa-users-gear"></i> ইউজার ম্যানেজমেন্ট</a></li>
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
            <div class="page-title"><i class="fa-solid fa-users-gear"></i> ইউজার কন্ট্রোল প্যানেল</div>
            
            <?php echo $msg; ?>

            <div class="card">
                <div class="card-header">
                    <span>📋 সকল ইউজারের তালিকা</span>
                    <span class="badge">Total: <?php echo $users->num_rows; ?></span>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ইউজারনেম</th>
                                <th>ইমেইল</th>
                                <th>ব্যালেন্স (🪙)</th>
                                <th>স্ট্যাটাস</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($users->num_rows > 0): ?>
                                <?php while($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 800; color: var(--text-main);"><i class="fa-regular fa-user" style="color:var(--text-muted); margin-right:5px;"></i> <?php echo htmlspecialchars($row['username']); ?></td>
                                    <td style="color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td>
                                        <form action="" method="POST" class="balance-form" id="balForm_<?php echo $row['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="update_balance" value="1">
                                            <input type="number" name="balance" step="0.01" value="<?php echo $row['balance']; ?>" class="balance-input">
                                            <button type="button" class="btn-save" onclick="confirmBalanceUpdate(<?php echo $row['id']; ?>)" title="ব্যালেন্স সেভ করুন">
                                                <i class="fa-solid fa-floppy-disk"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if($row['status'] == 'active'): ?>
                                            <span class="status-badge bg-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge bg-banned">Banned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="" method="POST" id="statusForm_<?php echo $row['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $row['status']; ?>">
                                            <input type="hidden" name="toggle_status" value="1">
                                            
                                            <?php if($row['status'] == 'active'): ?>
                                                <button type="button" class="btn-toggle btn-ban" onclick="confirmStatusToggle(<?php echo $row['id']; ?>, 'banned')">
                                                    <i class="fa-solid fa-ban"></i> ব্যান করুন
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-toggle btn-unban" onclick="confirmStatusToggle(<?php echo $row['id']; ?>, 'active')">
                                                    <i class="fa-solid fa-check"></i> আনব্যান করুন
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">কোনো ইউজার পাওয়া যায়নি।</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Sidebar Toggle Logic
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('mobileOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }
        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // SweetAlert2 for Balance Update Confirmation
        function confirmBalanceUpdate(id) {
            Swal.fire({
                title: 'ব্যালেন্স আপডেট?',
                text: "আপনি কি ইউজারের এই নতুন ব্যালেন্সটি সেভ করতে চান?",
                icon: 'question',
                showCancelButton: true,
                background: '#151A22', color: '#fff',
                confirmButtonColor: '#007BFF', cancelButtonColor: '#334155',
                confirmButtonText: 'হ্যাঁ, সেভ করুন', cancelButtonText: 'বাতিল'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('balForm_' + id).submit();
                }
            });
        }

        // SweetAlert2 for Status Toggle (Ban/Unban) Confirmation
        function confirmStatusToggle(id, action) {
            let msgText = action === 'banned' ? "এই ইউজারকে ব্যান করা হবে। সে আর ওয়েবসাইটে লগইন করতে পারবে না!" : "এই ইউজারকে আনব্যান করা হবে। সে পুনরায় ওয়েবসাইটে এক্সেস পাবে।";
            let btnColor = action === 'banned' ? '#FF3C3C' : '#00E701';
            let btnText = action === 'banned' ? 'হ্যাঁ, ব্যান করুন!' : 'হ্যাঁ, আনব্যান করুন!';

            Swal.fire({
                title: 'আপনি কি নিশ্চিত?',
                text: msgText,
                icon: 'warning',
                showCancelButton: true,
                background: '#151A22', color: '#fff',
                confirmButtonColor: btnColor, cancelButtonColor: '#334155',
                confirmButtonText: btnText, cancelButtonText: 'বাতিল'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('statusForm_' + id).submit();
                }
            });
        }
    </script>
</body>
</html>