<?php
require_once 'config.php';
$api_token = "YOUR_FREE_API_TOKEN_HERE"; // এখানেও আপনার টোকেনটি দিন
$url = "https://api.football-data.org/v4/competitions/WC/standings";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["X-Auth-Token: $api_token"],
]);
$response = curl_exec($curl);
curl_close($curl);
$data = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লাইভ পয়েন্ট টেবিল - PredX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+Bengali:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0B0E14; color: #FFF; font-family: 'Inter', sans-serif; padding-bottom: 90px; margin: 0; }
        .header { padding: 20px; text-align: center; background: #151A22; border-bottom: 1px solid #242B38; font-weight: 800; font-size: 20px;}
        .container { max-width: 800px; margin: 20px auto; padding: 0 15px; }
        .group-card { background: #151A22; border-radius: 12px; margin-bottom: 25px; border: 1px solid #242B38; overflow: hidden; }
        .group-title { background: rgba(0, 231, 1, 0.1); padding: 12px 20px; font-weight: 800; color: #00E701; border-bottom: 1px solid #242B38; }
        table { width: 100%; border-collapse: collapse; text-align: center; font-size: 14px; }
        th { background: rgba(255,255,255,0.02); padding: 10px; color: #8B94A3; font-weight: 600; }
        td { padding: 12px 10px; border-bottom: 1px solid #242B38; }
        tr:last-child td { border-bottom: none; }
        .team-info { display: flex; align-items: center; gap: 10px; text-align: left; font-weight: 800; }
        .team-info img { width: 25px; height: 25px; object-fit: contain; }
    </style>
</head>
<body>

    <div class="header">🏆 ফিফা লাইভ পয়েন্ট টেবিল</div>

    <div class="container">
        <?php if(isset($data['standings'])): ?>
            <?php foreach($data['standings'] as $group): ?>
                <div class="group-card">
                    <div class="group-title"><?php echo str_replace('_', ' ', $group['group']); ?></div>
                    <table>
                        <tr>
                            <th style="text-align:left;">টিম</th>
                            <th>MP</th> <!-- Matches Played -->
                            <th>W</th>  <!-- Won -->
                            <th>D</th>  <!-- Draw -->
                            <th>L</th>  <!-- Lost -->
                            <th>Pts</th><!-- Points -->
                        </tr>
                        <?php foreach($group['table'] as $row): ?>
                        <tr>
                            <td>
                                <div class="team-info">
                                    <img src="<?php echo $row['team']['crest']; ?>" alt="logo">
                                    <?php echo $row['team']['tla']; ?> <!-- Short Name -->
                                </div>
                            </td>
                            <td><?php echo $row['playedGames']; ?></td>
                            <td><?php echo $row['won']; ?></td>
                            <td><?php echo $row['draw']; ?></td>
                            <td><?php echo $row['lost']; ?></td>
                            <td style="color:#00E701; font-weight:800;"><?php echo $row['points']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; color:#8B94A3;">পয়েন্ট টেবিল এখন উপলব্ধ নেই।</p>
        <?php endif; ?>
    </div>

    <?php include 'bottom_nav.php'; ?>
</body>
</html>