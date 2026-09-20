<?php
$dbHost="localhost";
$dbUser="root";
$dbPass="root1234";
$dbName="t";

try{
    $conn= new PDO("mysql:host=$dbHost;dbname=$dbName",$dbUser,$dbPass);
    //echo "connect yes";
}catch(Exception $e){
    echo $e->getMessage(); //يعرض الاخطاء حق الاتصال
    exit();
}

?>