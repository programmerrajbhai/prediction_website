<?php
session_start();
// config এবং functions ফাইলের পাথ ঠিক করে দেওয়া হলো (যেহেতু admin ফোল্ডার এক ডিরেক্টরি ভেতরে)
require_once '../config.php';
require_once '../functions.php';

// সিকিউরিটি চেক: ইউজার কি লগইন করা আছে এবং সে কি অ্যাডমিন?
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied! Only Admins can view this page.</h2>");
}

$msg = '';

// ফর্ম সাবমিট হলে ম্যাচ ডেটাবেসে সেভ করার লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_match'])) {
    $team1_id = $_POST['team1'];
    $team2_id = $_POST['team2'];
    $match_time = $_POST['match_time']; // Format: YYYY-MM-DDTHH:MM

    if ($team1_id == $team2_id) {
        $msg = "<div class='error'>A team cannot play against itself!</div>";
    } else {
        $sql = "INSERT INTO matches (team1_id, team2_id, match_time) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $team1_id, $team2_id, $match_time);
        
        if ($stmt->execute()) {
            $msg = "<div class='success'>Match Scheduled Successfully!</div>";
        } else {
            $msg = "<div class='error'>Error scheduling match.</div>";
        }
    }
}

// ড্রপডাউনের জন্য সব টিমের লিস্ট নিয়ে আসা
$teams = $conn->query("SELECT * FROM teams ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Matches - Admin</title>
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; }
        .admin-container { max-width: 600px; margin: 40px auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }
        h2 { text-align: center; color: #3b82f6; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; color: #94a3b8; }
        .input-group select, .input-group input { width: 100%; padding: 10px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; outline: none; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #22c55e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 10px; transition: 0.3s; }
        .btn:hover { background: #16a34a; }
        .success { color: #22c55e; text-align: center; margin-bottom: 15px; font-weight: bold; }
        .error { color: #ef4444; text-align: center; margin-bottom: 15px; font-weight: bold; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>

    <div class="admin-container">
        <h2>🗓️ Schedule New Match</h2>
        <?php echo $msg; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Team 1 (Home)</label>
                <select name="team1" required>
                    <option value="">Select Team</option>
                    <?php 
                    // ড্রপডাউনে টিমগুলো দেখানো
                    if($teams->num_rows > 0) {
                        $teams->data_seek(0); // পয়েন্টার রিসেট
                        while($row = $teams->fetch_assoc()) {
                            echo "<option value='".$row['id']."'>".$row['name']."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="input-group">
                <label>Team 2 (Away)</label>
                <select name="team2" required>
                    <option value="">Select Team</option>
                    <?php 
                    if($teams->num_rows > 0) {
                        $teams->data_seek(0);
                        while($row = $teams->fetch_assoc()) {
                            echo "<option value='".$row['id']."'>".$row['name']."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="input-group">
                <label>Match Date & Time</label>
                <input type="datetime-local" name="match_time" required>
            </div>

            <button type="submit" name="add_match" class="btn">Create Match</button>
        </form>

        <a href="../index.php" class="back-link">← Back to Homepage</a>
    </div>

</body>
</html>