<?php
// admin/auto_sync.php
require_once '../config.php';

$api_token = "2715b15330314f008676aaa22ed7deca"; // আপনার টোকেন দিন
$url = "https://api.football-data.org/v4/competitions/WC/matches";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["X-Auth-Token: $api_token"],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) die("cURL Error #:" . $err);
$data = json_decode($response, true);
if (!isset($data['matches'])) die("API থেকে ডাটা আসেনি!");

foreach ($data['matches'] as $match) {
    $api_match_id = $match['id'];
    $match_time = date('Y-m-d H:i:s', strtotime($match['utcDate']));
    $api_status = $match['status']; 
    $status = 'upcoming'; 
    
    if (in_array($api_status, ['IN_PLAY', 'PAUSED'])) $status = 'live';
    elseif (in_array($api_status, ['FINISHED', 'AWARDED'])) $status = 'finished';
    elseif (in_array($api_status, ['CANCELLED', 'POSTPONED', 'SUSPENDED'])) $status = 'canceled';

    if(!isset($match['homeTeam']['name']) || !isset($match['awayTeam']['name'])) continue; 

    $team1_name = $match['homeTeam']['name'];
    $team2_name = $match['awayTeam']['name'];
    
    // API থেকে অফিসিয়াল লোগো (Crest) নেওয়া হচ্ছে
    $team1_logo = isset($match['homeTeam']['crest']) ? $match['homeTeam']['crest'] : '⚽';
    $team2_logo = isset($match['awayTeam']['crest']) ? $match['awayTeam']['crest'] : '⚽';

    $t1_check = $conn->query("SELECT id FROM teams WHERE name = '$team1_name'");
    if ($t1_check->num_rows > 0) {
        $team1_id = $t1_check->fetch_assoc()['id'];
        // লোগো আপডেট করে দিচ্ছি যদি আগে ইমোজি থেকে থাকে
        $conn->query("UPDATE teams SET flag = '$team1_logo' WHERE id = $team1_id");
    } else {
        $conn->query("INSERT INTO teams (name, flag) VALUES ('$team1_name', '$team1_logo')");
        $team1_id = $conn->insert_id;
    }

    $t2_check = $conn->query("SELECT id FROM teams WHERE name = '$team2_name'");
    if ($t2_check->num_rows > 0) {
        $team2_id = $t2_check->fetch_assoc()['id'];
        $conn->query("UPDATE teams SET flag = '$team2_logo' WHERE id = $team2_id");
    } else {
        $conn->query("INSERT INTO teams (name, flag) VALUES ('$team2_name', '$team2_logo')");
        $team2_id = $conn->insert_id;
    }

    $t1_odds = rand(150, 250) / 100;
    $draw_odds = rand(280, 350) / 100;
    $t2_odds = rand(150, 300) / 100;

    $match_check = $conn->query("SELECT id FROM matches WHERE api_match_id = $api_match_id");
    if ($match_check->num_rows > 0) {
        $db_match_id = $match_check->fetch_assoc()['id'];
        $conn->query("UPDATE matches SET status = '$status' WHERE id = $db_match_id");
        echo "Updated: $team1_name vs $team2_name <br>";
    } else {
        $insert_sql = "INSERT INTO matches (api_match_id, team1_id, team2_id, match_time, team1_odds, draw_odds, team2_odds, status) 
                       VALUES ($api_match_id, $team1_id, $team2_id, '$match_time', $t1_odds, $draw_odds, $t2_odds, '$status')";
        $conn->query($insert_sql);
        echo "Inserted: $team1_name vs $team2_name <br>";
    }
}
echo "<h3 style='color:green;'>Sync Complete!</h3>";
?>