<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক: শুধুমাত্র অ্যাডমিন এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:#FF3C3C; text-align:center;'>Access Denied!</h2>");
}

$msg = '';

// ==========================================
// AUTO SCHEMA UPDATER (For Settings Table)
// ==========================================
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if($check_table->num_rows == 0) {
    // টেবিল না থাকলে তৈরি করবে
    $create_sql = "CREATE TABLE settings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        site_name VARCHAR(100) DEFAULT 'PredX',
        min_deposit DECIMAL(10,2) DEFAULT 50.00,
        min_withdraw DECIMAL(10,2) DEFAULT 100.00,
        bkash_number VARCHAR(20) DEFAULT '017XX-XXXXXX',
        nagad_number VARCHAR(20) DEFAULT '017XX-XXXXXX'
    )";
    $conn->query($create_sql);
    // ডিফল্ট একটি রো (Row) ইনসার্ট করবে
    $conn->query("INSERT INTO settings (site_name, min_deposit, min_withdraw) VALUES ('PredX', 50, 100)");
}

// ==========================================
// UPDATE SETTINGS LOGIC
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $site_name = sanitizeInput($_POST['site_name'], $conn);
    $min_dep = floatval($_POST['min_dep']);
    $min_with = floatval($_POST['min_with']);
    $bkash = sanitizeInput($_POST['bkash_number'], $conn);
    $nagad = sanitizeInput($_POST['nagad_number'], $conn);

    $update_sql = "UPDATE settings SET site_name=?, min_deposit=?, min_withdraw=?, bkash_number=?, nagad_number=? WHERE id=1";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sddss", $site_name, $min_dep, $min_with, $bkash, $nagad);
    
    if ($stmt->execute()) {
        $msg = "<div class='success-msg'><i class='fa-solid fa-circle-check'></i> সেটিংস সফলভাবে আপডেট করা হয়েছে!</div>";
    } else {
        $msg = "<div class='error-msg'><i class='fa-solid fa-triangle-exclamation'></i> সিস্টেম এরর! আপডেট ব্যর্থ হয়েছে।</div>";
    }
}

// ডেটাবেস থেকে বর্তমান সেটিংস নিয়ে আসা
$settings_query = $conn->query("SELECT * FROM settings WHERE id = 1");
$settings = $settings_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>গ্লোবাল সেটিংস - Admin PredX</title>
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

        /* --- Page Content --- */
        .dashboard-container { padding: 30px; display: flex; justify-content: center; align-items: flex-start;}
        .form-card { background: var(--bg-card); padding: 35px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); width: 100%; max-width: 600px; position: relative; overflow: hidden; }
        .form-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--accent-admin), #FF8A8A); }
        
        .header-box { text-align: center; margin-bottom: 30px; }
        .header-box i { font-size: 40px; color: var(--accent-admin); margin-bottom: 10px; }
        .header-box h2 { margin: 0 0 5px 0; font-size: 24px; font-weight: 800; }
        .header-box p { color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 600; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 13px; }
        
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); transition: 0.3s; }
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; border: 1px solid var(--border-color); border-radius: 8px; background: #0B0E14; color: var(--text-main); font-family: 'Inter', 'Noto Sans Bengali', sans-serif; font-size: 15px; outline: none; transition: 0.3s; box-sizing: border-box; }
        .input-wrapper input:focus { border-color: var(--accent-admin); box-shadow: 0 0 10px rgba(255, 60, 60, 0.1); }
        .input-wrapper input:focus + i { color: var(--accent-admin); }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .btn { width: 100%; padding: 15px; background: linear-gradient(90deg, var(--accent-admin), var(--accent-admin-hover)); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 800; text-transform: uppercase; transition: 0.3s; box-shadow: 0 4px 15px rgba(255, 60, 60, 0.2); margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 60, 60, 0.4); }
        
        .success-msg { background: rgba(0, 231, 1, 0.1); color: #00E701; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(0, 231, 1, 0.2); }
        .error-msg { background: rgba(255, 60, 60, 0.1); color: #FF3C3C; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 25px; font-weight: 600; border: 1px solid rgba(255, 60, 60, 0.2); }

        @media (max-width: 992px) {
            .sidebar { left: -300px; } .sidebar.active { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.8); }
            .main-content { margin-left: 0; width: 100%; } .mobile-toggle { display: block; }
            .grid-2 { grid-template-columns: 1fr; }
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
            <li class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> ইউজার ম্যানেজমেন্ট</a></li>

            <div class="nav-title">সিস্টেম</div>
            <li class="nav-item"><a href="settings.php" class="nav-link active"><i class="fa-solid fa-gear"></i> গ্লোবাল সেটিংস</a></li>
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
            <div class="form-card">
                <div class="header-box">
                    <i class="fa-solid fa-gear"></i>
                    <h2>সিস্টেম সেটিংস</h2>
                    <p>এখান থেকে ওয়েবসাইটের গুরুত্বপূর্ণ রুলস পরিবর্তন করুন</p>
                </div>
                
                <?php echo $msg; ?>

                <form id="settingsForm" action="" method="POST">
                    
                    <div class="input-group">
                        <label>ওয়েবসাইটের নাম (Site Name)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-globe"></i>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="input-group">
                            <label>সর্বনিম্ন ডিপোজিট (Min Deposit)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-arrow-down-to-line"></i>
                                <input type="number" name="min_dep" value="<?php echo $settings['min_deposit']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <label>সর্বনিম্ন উইথড্র (Min Withdraw)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                <input type="number" name="min_with" value="<?php echo $settings['min_withdraw']; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="input-group">
                            <label>বিকাশ নাম্বার (Admin bKash)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-mobile-screen"></i>
                                <input type="text" name="bkash_number" value="<?php echo htmlspecialchars($settings['bkash_number']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <label>নগদ নাম্বার (Admin Nagad)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-mobile-screen"></i>
                                <input type="text" name="nagad_number" value="<?php echo htmlspecialchars($settings['nagad_number']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="update_settings" value="1">
                    
                    <button type="button" class="btn" onclick="confirmUpdate()">সেটিংস সেভ করুন <i class="fa-solid fa-floppy-disk"></i></button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('mobileOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }
        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // SweetAlert2 Confirmation
        function confirmUpdate() {
            const form = document.getElementById('settingsForm');
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'আপডেট কনফার্ম করুন',
                text: "আপনি কি নিশ্চিত যে ওয়েবসাইটের এই গ্লোবাল সেটিংস সেভ করবেন?",
                icon: 'question',
                showCancelButton: true,
                background: '#151A22',
                color: '#fff',
                confirmButtonColor: '#FF3C3C',
                cancelButtonColor: '#334155',
                confirmButtonText: 'হ্যাঁ, সেভ করুন',
                cancelButtonText: 'বাতিল'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>