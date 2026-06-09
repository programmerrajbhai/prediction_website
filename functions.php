<?php
// ইনপুট স্যানিটাইজ করার ফাংশন
function sanitizeInput($data, $conn) {
    $data = trim($data); // স্পেস রিমুভ করবে
    $data = stripslashes($data); 
    $data = htmlspecialchars($data); // HTML ট্যাগগুলোকে ব্লক করবে
    return $conn->real_escape_string($data); // SQL ইনজেকশন প্রোটেকশন
}
?>