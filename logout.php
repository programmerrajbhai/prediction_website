<?php
session_start();
session_unset();    // সব সেশন ভ্যারিয়েবল মুছে ফেলা
session_destroy();  // সেশন ধ্বংস করা
header("Location: login.php");
exit();
?>