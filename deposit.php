<?php
require_once 'config.php';
require_once 'functions.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) {
    if(isset($_POST['ajax_deposit'])) {
        echo json_encode(['status' => 'error', 'message' => 'আপনাকে প্রথমে লগইন করতে হবে।']); exit;
    }
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ২. CSRF Token জেনারেট
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ৩. ডাটাবেস থেকে গ্লোবাল সেটিংস (বিকাশ/নগদ নাম্বার ও লিমিট) নিয়ে আসা
$settings_sql = "SELECT min_deposit, bkash_number, nagad_number FROM settings WHERE id = 1";
$settings_res = $conn->query($settings_sql);
$settings = ($settings_res && $settings_res->num_rows > 0) ? $settings_res->fetch_assoc() : null;

$min_deposit = $settings ? floatval($settings['min_deposit']) : 50.00;
$bkash_no = $settings ? htmlspecialchars($settings['bkash_number']) : '017XX-XXXXXX';
$nagad_no = $settings ? htmlspecialchars($settings['nagad_number']) : '017XX-XXXXXX';

// ==========================================
// ৪. AJAX POST Request Handle
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_deposit'])) {
    header('Content-Type: application/json');
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'সিকিউরিটি এরর! ইনভ্যালিড টোকেন।']); exit;
    }

    $amount = floatval($_POST['amount']);
    $method = sanitizeInput($_POST['payment_method'], $conn);
    $trx_id = sanitizeInput($_POST['transaction_id'], $conn);

    if ($amount < $min_deposit) {
        echo json_encode(['status' => 'error', 'message' => 'সর্বনিম্ন ডিপোজিট ' . $min_deposit . ' কয়েন/টাকা।']); exit;
    }

    // ট্রানজেকশন আইডি চেক
    $trx_check = $conn->query("SELECT id FROM deposits WHERE transaction_id = '$trx_id'");
    if($trx_check->num_rows > 0){
        echo json_encode(['status' => 'error', 'message' => 'এই Transaction ID টি ইতোমধ্যে ব্যবহার করা হয়েছে!']); exit;
    }

    $insert_sql = "INSERT INTO deposits (user_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("idss", $user_id, $amount, $method, $trx_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'ডিপোজিট রিকোয়েস্ট সফলভাবে পাঠানো হয়েছে! অ্যাডমিন অ্যাপ্রুভ করলে ব্যালেন্স যোগ হবে।']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'সিস্টেম এরর। আবার চেষ্টা করুন।']);
    }
    exit;
}

// ==========================================
// ৫. Normal Page Load
// ==========================================
$bal_sql = "SELECT balance FROM users WHERE id = ?";
$bal_stmt = $conn->prepare($bal_sql);
$bal_stmt->bind_param("i", $user_id);
$bal_stmt->execute();
$current_balance = $bal_stmt->get_result()->fetch_assoc()['balance'];
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ডিপোজিট করুন - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --bg-main: #0B0E14; --bg-card: #151A22; --bg-glass: rgba(21, 26, 34, 0.85); 
            --accent-primary: #00E701; --accent-hover: #00C801; 
            --text-main: #FFFFFF; --text-muted: #8B94A3; --border-color: #242B38; 
        }
        
        body { 
            background-color: var(--bg-main); color: var(--text-main); 
            font-family: 'Noto Sans Bengali', 'Inter', sans-serif; 
            margin: 0; padding-bottom: 50px; 
            background-image: radial-gradient(circle at 50% -20%, #1a2235, var(--bg-main) 60%); 
            min-height: 100vh; overflow-x: hidden;
        }
        
        /* Navbar */
        .navbar { background: var(--bg-glass); backdrop-filter: blur(15px); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 10; }
        .navbar .back-btn { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 15px; transition: 0.3s; }
        .navbar .back-btn:hover { color: var(--text-main); }
        .balance-badge { background: rgba(0, 231, 1, 0.1); padding: 8px 16px; border-radius: 30px; font-size: 15px; font-weight: 800; color: var(--accent-primary); border: 1px solid rgba(0, 231, 1, 0.3); font-family: 'Inter', sans-serif;}

        /* Main Container */
        .flow-container { max-width: 550px; margin: 40px auto; padding: 0 20px; }
        
        .header-box { text-align: center; margin-bottom: 40px; opacity: 0; animation: slideInFade 0.6s forwards; }
        .header-box i { font-size: 40px; color: var(--accent-primary); margin-bottom: 15px; filter: drop-shadow(0 0 10px rgba(0,231,1,0.3)); }
        .header-box h2 { margin: 0 0 8px 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;}
        .header-box p { color: var(--text-muted); font-size: 14px; margin: 0; }

        /* Step Flow System */
        .step-wrapper {
            position: relative;
            padding-left: 45px;
            margin-bottom: 30px;
            opacity: 0; /* For animation */
            animation: slideInFade 0.6s forwards;
        }
        
        /* Animation Delays for Flow */
        .delay-1 { animation-delay: 0.2s; }
        .delay-2 { animation-delay: 0.4s; }
        .delay-3 { animation-delay: 0.6s; }

        @keyframes slideInFade {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* Vertical Line Connector */
        .step-wrapper::before {
            content: ''; position: absolute; left: 15px; top: 40px; bottom: -30px;
            width: 2px; background: var(--border-color); z-index: 1;
        }
        .step-wrapper:last-child::before { display: none; } /* Hide line on last step */

        /* Step Number Badge */
        .step-number {
            position: absolute; left: 0; top: 0; width: 32px; height: 32px;
            background: var(--bg-main); border: 2px solid var(--accent-primary);
            color: var(--accent-primary); border-radius: 50%; display: flex;
            justify-content: center; align-items: center; font-weight: 800; font-family: 'Inter';
            z-index: 2; font-size: 14px; box-shadow: 0 0 10px rgba(0,231,1,0.2);
        }

        .step-content {
            background: var(--bg-card); padding: 25px; border-radius: 16px;
            border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .step-title { margin: 0 0 20px 0; font-size: 18px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;}

        /* Payment Boxes */
        .pay-box { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-radius: 12px; margin-bottom: 12px; font-family: 'Inter', sans-serif; transition: 0.3s; cursor: pointer;}
        .bkash-box { background: rgba(226, 19, 110, 0.05); border: 1px solid rgba(226, 19, 110, 0.2); }
        .bkash-box:hover { background: rgba(226, 19, 110, 0.1); border-color: rgba(226, 19, 110, 0.5); transform: translateY(-2px);}
        .nagad-box { background: rgba(247, 147, 30, 0.05); border: 1px solid rgba(247, 147, 30, 0.2); }
        .nagad-box:hover { background: rgba(247, 147, 30, 0.1); border-color: rgba(247, 147, 30, 0.5); transform: translateY(-2px);}
        
        .pay-box .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 15px;}
        .bkash-box .brand { color: #E2136E; }
        .nagad-box .brand { color: #F7931E; }
        
        .pay-box .number { font-size: 18px; font-weight: 800; color: var(--text-main); letter-spacing: 1px;}
        .copy-btn { background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer; transition: 0.3s; padding: 5px;}
        .copy-btn:hover { color: var(--text-main); transform: scale(1.1); }

        .min-dep-text { background: rgba(0, 231, 1, 0.05); color: var(--accent-primary); padding: 10px; text-align: center; border-radius: 8px; font-size: 13px; font-weight: 800; border: 1px solid rgba(0, 231, 1, 0.2); margin-top: 15px;}

        /* Form Inputs */
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 600; font-size: 13px; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); transition: 0.3s; }
        .input-wrapper input, .input-wrapper select { width: 100%; padding: 15px 15px 15px 45px; border: 1px solid var(--border-color); border-radius: 10px; background: #0B0E14; color: var(--text-main); font-size: 15px; font-family: 'Inter', sans-serif; outline: none; transition: 0.3s; box-sizing: border-box; }
        .input-wrapper input:focus, .input-wrapper select:focus { border-color: var(--accent-primary); box-shadow: 0 0 15px rgba(0, 231, 1, 0.1); }
        .input-wrapper input:focus + i, .input-wrapper select:focus + i { color: var(--accent-primary); }

        /* Submit Button */
        .btn { width: 100%; padding: 16px; background: linear-gradient(90deg, var(--accent-primary), var(--accent-hover)); color: #000; border: none; border-radius: 10px; cursor: pointer; font-size: 16px; font-weight: 800; text-transform: uppercase; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(0, 231, 1, 0.2);}
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0, 231, 1, 0.4); }
        .btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> হোমপেজ</a>
        <div class="balance-badge"><i class="fa-solid fa-coins" style="color: #F59E0B;"></i> <?php echo number_format($current_balance, 2); ?></div>
    </nav>

    <div class="flow-container">
        
        <div class="header-box">
            <i class="fa-solid fa-wallet"></i>
            <h2>টপ-আপ ওয়ালেট</h2>
            <p>কয়েন কিনতে নিচের ধাপগুলো অনুসরণ করুন</p>
        </div>

        <form id="depositForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="step-wrapper delay-1">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3 class="step-title"><i class="fa-solid fa-paper-plane" style="color: #007BFF;"></i> পেমেন্ট করুন</h3>
                    <p style="font-size:13px; color:var(--text-muted); margin-top:-15px; margin-bottom:15px;">নিচের যেকোনো একটি নাম্বারে Send Money করুন।</p>
                    
                    <div class="pay-box bkash-box" onclick="copyText('bkashNumber')">
                        <div class="brand"><i class="fa-solid fa-building-columns"></i> bKash</div>
                        <div class="number" id="bkashNumber"><?php echo $bkash_no; ?></div>
                        <button type="button" class="copy-btn" title="কপি করুন"><i class="fa-regular fa-copy"></i></button>
                    </div>

                    <div class="pay-box nagad-box" onclick="copyText('nagadNumber')">
                        <div class="brand"><i class="fa-solid fa-building-columns"></i> Nagad</div>
                        <div class="number" id="nagadNumber"><?php echo $nagad_no; ?></div>
                        <button type="button" class="copy-btn" title="কপি করুন"><i class="fa-regular fa-copy"></i></button>
                    </div>

                    <div class="min-dep-text">
                        <i class="fa-solid fa-circle-info"></i> সর্বনিম্ন ডিপোজিট: <?php echo $min_deposit; ?> টাকা (কয়েন)
                    </div>
                </div>
            </div>

            <div class="step-wrapper delay-2">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3 class="step-title"><i class="fa-solid fa-file-invoice" style="color: #F59E0B;"></i> তথ্য দিন</h3>
                    <p style="font-size:13px; color:var(--text-muted); margin-top:-15px; margin-bottom:20px;">টাকা পাঠানোর পর নিচের তথ্যগুলো পূরণ করুন।</p>

                    <div class="input-group">
                        <label>পাঠানো টাকার পরিমাণ</label>
                        <div class="input-wrapper">
                            <input type="number" name="amount" min="<?php echo $min_deposit; ?>" required placeholder="যেমন: 500">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>যে মাধ্যম থেকে পাঠিয়েছেন</label>
                        <div class="input-wrapper">
                            <select name="payment_method" required>
                                <option value="">নির্বাচন করুন...</option>
                                <option value="bKash">bKash (বিকাশ)</option>
                                <option value="Nagad">Nagad (নগদ)</option>
                            </select>
                            <i class="fa-solid fa-mobile-screen"></i>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>ট্রানজেকশন আইডি (TrxID)</label>
                        <div class="input-wrapper">
                            <input type="text" name="transaction_id" required placeholder="TrxID এখানে দিন">
                            <i class="fa-solid fa-hashtag"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-wrapper delay-3">
                <div class="step-number">3</div>
                <div class="step-content" style="background: transparent; border: none; box-shadow: none; padding: 0;">
                    <button type="submit" id="submitBtn" class="btn">রিকোয়েস্ট সাবমিট করুন <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>

        </form>
    </div>

    <script>
        // Copy to Clipboard (Clicking anywhere on the box copies it)
        function copyText(elementId) {
            var text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(function() {
                Swal.fire({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
                    icon: 'success', title: 'নাম্বার কপি হয়েছে!', background: '#151A22', color: '#fff'
                });
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // AJAX Form Submission
        document.getElementById('depositForm').addEventListener('submit', function(e) {
            e.preventDefault(); 
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> প্রসেস হচ্ছে...';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('ajax_deposit', '1'); 

            fetch('deposit.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;

                if (data.status === 'success') {
                    document.getElementById('depositForm').reset();
                    Swal.fire({ 
                        title: 'সাবমিট সফল!', 
                        text: data.message, 
                        icon: 'success', 
                        background: '#151A22', color: '#fff', 
                        confirmButtonColor: '#00E701' 
                    }).then(() => {
                        window.location.href = 'index.php'; // রিডাইরেক্ট করে ফিডে পাঠিয়ে দেওয়া ভালো
                    });
                } else {
                    Swal.fire({ title: 'ফেইলড', text: data.message, icon: 'error', background: '#151A22', color: '#fff', confirmButtonColor: '#FF3C3C' });
                }
            })
            .catch(error => {
                btn.innerHTML = originalText; btn.disabled = false;
                Swal.fire({ title: 'সিস্টেম এরর', text: 'সার্ভারে কোনো সমস্যা হয়েছে।', icon: 'error', background: '#151A22', color: '#fff', confirmButtonColor: '#FF3C3C' });
            });
        });
    </script>

</body>
</html>