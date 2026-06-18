<?php
// বর্তমান পেজ ডিটেক্ট করার জন্য
$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in_nav = isset($_SESSION['user_id']);
$nav_balance = 0.00;
$nav_user_role = 'user';
$nav_username = '';

if ($is_logged_in_nav) {
    $nav_user_id = $_SESSION['user_id'];
    $nav_username = $_SESSION['username'];
    $nav_sql = "SELECT balance, role FROM users WHERE id = ?";
    $nav_stmt = $conn->prepare($nav_sql);
    $nav_stmt->bind_param("i", $nav_user_id);
    $nav_stmt->execute();
    $nav_result = $nav_stmt->get_result();
    if ($nav_row = $nav_result->fetch_assoc()) {
        $nav_balance = $nav_row['balance'];
        $nav_user_role = $nav_row['role'];
    }
}
?>

<style>
    /* --- Sidebar CSS --- */
    .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 10001; opacity: 0; visibility: hidden; transition: 0.3s ease; }
    .sidebar-overlay.active { opacity: 1; visibility: visible; }
    .sidebar { position: fixed; top: 0; right: -350px; width: 300px; max-width: 80vw; height: 100vh; background: #151A22; border-left: 1px solid #242B38; z-index: 10002; transition: right 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); display: flex; flex-direction: column; box-shadow: -5px 0 25px rgba(0,0,0,0.5); }
    .sidebar.active { right: 0; }
    .sidebar-header { padding: 25px 20px; border-bottom: 1px solid #242B38; display: flex; justify-content: space-between; align-items: center; }
    .sidebar .user-info .name { font-size: 18px; font-weight: 800; color: #FFFFFF; font-family: 'Inter', sans-serif;}
    .sidebar .user-info .role { font-size: 12px; color: #00E701; text-transform: uppercase; font-weight: 600; letter-spacing: 1px;}
    .close-sidebar { background: none; border: none; color: #8B94A3; font-size: 24px; cursor: pointer; transition: 0.3s; padding: 0; }
    .close-sidebar:hover { color: #FF3C3C; transform: rotate(90deg); }
    .sidebar-wallet { padding: 20px; background: linear-gradient(145deg, #1A212D, #0F131A); border-bottom: 1px solid #242B38; text-align: center; }
    .sidebar-wallet .amt { font-size: 28px; font-weight: 800; color: #00E701; margin: 5px 0; font-family: 'Inter', sans-serif;}
    .sidebar-menu { list-style: none; padding: 15px 0; margin: 0; flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; }
    .sidebar-menu a { display: flex; align-items: center; gap: 15px; padding: 12px 15px; border-radius: 8px; color: #8B94A3; text-decoration: none; font-weight: 600; transition: 0.3s; margin: 0 15px 5px;}
    .sidebar-menu a:hover { background: rgba(255,255,255,0.05); color: #FFFFFF; padding-left: 20px; }
    .sidebar-menu a.active { background: rgba(0, 231, 1, 0.1); color: #00E701; border: 1px solid rgba(0, 231, 1, 0.2);}
    .sidebar-footer { padding: 20px; border-top: 1px solid #242B38; }
    .logout-btn { display: block; width: 100%; padding: 12px; text-align: center; background: rgba(255, 60, 60, 0.1); color: #FF3C3C; border: 1px solid rgba(255, 60, 60, 0.2); border-radius: 8px; font-weight: 800; text-decoration: none; transition: 0.3s; }
    .logout-btn:hover { background: #FF3C3C; color: #fff; box-shadow: 0 4px 15px rgba(255, 60, 60, 0.3);}

    /* --- Floating Curved Bottom Nav CSS (Premium) --- */
    body { padding-bottom: 100px; } /* Content space for floating nav */
    
    .bottom-nav-container {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 40px);
        max-width: 450px;
        z-index: 9999;
    }

    .bottom-nav {
        background: rgba(21, 26, 34, 0.85); 
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 35px; /* Fully Curved System */
        display: flex; 
        justify-content: space-around; 
        align-items: center;
        padding: 8px 10px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.6), inset 0 1px 1px rgba(255,255,255,0.1);
    }

    .b-nav-item {
        position: relative;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-decoration: none; color: #8B94A3; font-size: 11px; font-weight: 600;
        flex: 1; height: 50px; font-family: 'Inter', sans-serif;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); /* Bouncy Animation */
        z-index: 1;
    }

    .b-nav-item i { 
        font-size: 20px; 
        margin-bottom: 4px;
        transition: all 0.4s ease; 
    }

    /* Active Animation */
    .b-nav-item.active { 
        color: #00E701; 
        transform: translateY(-8px); /* Pop Up Effect */
    }
    
    .b-nav-item.active i {
        font-size: 22px;
        filter: drop-shadow(0 4px 6px rgba(0,231,1,0.4));
    }

    /* Glowing Dot Indicator */
    .b-nav-item::after {
        content: ''; position: absolute; bottom: -5px; width: 5px; height: 5px;
        background: #00E701; border-radius: 50%; opacity: 0;
        transform: scale(0); transition: 0.3s;
        box-shadow: 0 0 10px #00E701;
    }
    .b-nav-item.active::after {
        opacity: 1; transform: scale(1);
    }

    .b-nav-item:hover:not(.active) { color: #FFFFFF; }

    @media (min-width: 1024px) {
        .bottom-nav-container { display: none; } /* Hide on PC */
        body { padding-bottom: 0; }
    }
</style>

<?php if ($is_logged_in_nav): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="userSidebar">
        <div class="sidebar-header">
            <div class="user-info">
                <span class="name"><?php echo htmlspecialchars($nav_username); ?></span>
                <span class="role"><?php echo $nav_user_role; ?> একাউন্ট</span>
            </div>
            <button class="close-sidebar" id="closeMenuBtn"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="sidebar-wallet">
            <p style="margin: 0; font-size: 13px; color: #8B94A3; font-weight: 600;">বর্তমান ব্যালেন্স</p>
            <div class="amt"><?php echo number_format($nav_balance, 2); ?> <i class="fa-solid fa-coins" style="font-size: 18px;"></i></div>
            <div style="display:flex; gap:10px; margin-top:15px;">
                <a href="deposit.php" style="flex:1; padding:10px; background:#00E701; color:#000; border-radius:6px; text-decoration:none; font-weight:800; font-size:13px; margin:0; justify-content:center;">ডিপোজিট</a>
                <a href="withdraw.php" style="flex:1; padding:10px; background:rgba(255,255,255,0.1); color:#fff; border-radius:6px; text-decoration:none; font-weight:800; font-size:13px; border: 1px solid #242B38; margin:0; justify-content:center;">উইথড্র</a>
            </div>
        </div>

        <div class="sidebar-menu">
            <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> হোম (ফিড)</a>
            <a href="bet_history.php" class="<?php echo ($current_page == 'bet_history.php') ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> আমার প্রেডিকশন</a>
            <a href="leaderboard.php" class="<?php echo ($current_page == 'leaderboard.php') ? 'active' : ''; ?>"><i class="fa-solid fa-trophy"></i> লিডারবোর্ড</a>
            
            <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>"><i class="fa-solid fa-user-gear"></i> আমার প্রোফাইল</a>
            
            <?php if ($nav_user_role === 'admin'): ?>
                <div style="margin: 15px 15px 5px 15px; border-top: 1px solid #242B38; padding-top: 15px;">
                    <span style="font-size: 11px; color: #8B94A3; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-family: 'Inter', sans-serif;">Admin Area</span>
                </div>
                <a href="admin/index.php"><i class="fa-solid fa-gauge-high"></i> ড্যাশবোর্ড</a>
                <a href="admin/manage_matches.php"><i class="fa-solid fa-calendar-plus"></i> ম্যাচ ম্যানেজমেন্ট</a>
                <a href="admin/update_result.php"><i class="fa-solid fa-check-double"></i> রেজাল্ট আপডেট</a>
            <?php endif; ?>
        </div>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> লগআউট করুন</a>
        </div>
    </div>
<?php endif; ?>

<div class="bottom-nav-container">
    <div class="bottom-nav">
        <a href="index.php" class="b-nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
            <span>হোম</span>
        </a>
        <a href="<?php echo $is_logged_in_nav ? 'bet_history.php' : 'login.php'; ?>" class="b-nav-item <?php echo ($current_page == 'bet_history.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-receipt"></i>
            <span>মাই বেট</span>
        </a>
        <a href="leaderboard.php" class="b-nav-item <?php echo ($current_page == 'leaderboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-trophy"></i>
            <span>লিডারবোর্ড</span>
        </a>
        <?php if($is_logged_in_nav): ?>
            <a href="javascript:void(0)" class="b-nav-item" id="bottomMenuBtn">
                <i class="fa-solid fa-bars"></i>
                <span>মেনু</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="b-nav-item <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-user"></i>
                <span>লগইন</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const bottomBtn = document.getElementById('bottomMenuBtn');
        const openBtn = document.getElementById('openMenuBtn'); // For Top Nav bar
        const closeBtn = document.getElementById('closeMenuBtn');
        const sidebar = document.getElementById('userSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleMenu() {
            if(sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
            }
        }

        if(bottomBtn) bottomBtn.addEventListener('click', toggleMenu);
        if(openBtn) openBtn.addEventListener('click', toggleMenu);
        if(closeBtn) closeBtn.addEventListener('click', toggleMenu);
        if(overlay) overlay.addEventListener('click', toggleMenu);
    });
</script>