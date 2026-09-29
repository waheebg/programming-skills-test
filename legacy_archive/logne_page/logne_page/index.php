<?php
session_start();  // بدياية الاجلسة 
session_unset(); // تدمير متغيرات الجلسة
header('Location: login.php');

?>
