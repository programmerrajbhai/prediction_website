<?php
// সেশন স্টার্ট করা হলো (যাতে লগইন ইনফো সব পেজে পাওয়া যায়)
session_start();

// ডেটাবেস ক্রেডেনশিয়াল
$host = "localhost";
$db_user = "root";       // XAMPP এর ডিফল্ট ইউজারনেম
$db_pass = "";           // XAMPP এর ডিফল্ট পাসওয়ার্ড (ফাঁকা থাকে)
$db_name = "prediction_website";

// কানেকশন তৈরি
$conn = new mysqli($host, $db_user, $db_pass, $db_name);

// কানেকশন চেক
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ডিফল্ট টাইমজোন সেট করা (বাংলাদেশের জন্য)
date_default_timezone_set("Asia/Dhaka");
?>