
<?php
session_start();
session_unset();   // تدمير الجلسة 
header('Location: login.php');
?>