<?php
 session_start();
 $namede=$_GET['name_dept_page'];
  //echo $name_logo_pagee;
   $_SESSION['page'];
  $nn=$_SESSION['page'];
  if(!isset($_SESSION['user'])){
    header('location:login.php?n=1');
    exit();
 }
 if(isset($_SESSION['user'])){
   ?>
   
   <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="profile.css">
    <meta http-equiv="refresh" content="5; url=<?php echo $nn?>">
    <title>login && logut</title>
</head>
<body>
    <header>
    <div> <button onclick="document.location=' http://localhost/php/home_quiz/?n=0' ">الصفحة الرئسية </button></div>
   <div> <button onclick="document.location='http://localhost/php/home_quiz/websiet.php?n=0'">wabsites</button></div>
   <div> <button onclick="document.location='http://localhost/php/home_quiz/android.html?n=0'" > android </button></div>
   <div> <button  onclick="alert()"> c #</button></div>
   <div> <button  onclick="document.location='http://localhost/php/home_quiz/datebase.html?n=0'" > databases</button></div>
    <div><button class="logut"> <a href="index.php">دخول جديد</a></button></div>
   <div> <button class="logut" > <a href="logout.php">خروج</a></button></div>
   <script>
      function alert() {
         var a = window.confirm('القسم  او الغه غير متوفر سيتم اضافتة في اقرب وقت ');
    console.log('ok');
      }
   </script>
    </header>
   <main>
   <center>
    <br><br>
    <div id="msg">
     

    </div>
   
   <br><br><br><br><br>
<h1> مبروك  <?php echo $_SESSION['user']['name']; ?>      لقد تم انشاء حسابك     </h1><br>
<p>  يجب عليك حفظ البريد الذي قمت بتسجيلة وكلمة السر 
من اجل تدخلة اذا قام بطلبك  تسجيل الدخول </p><br>
<p>شكرا لك</p><br>


<p>هاذا اسم المستخدم الذي قمت ابدخالة والبريد الكتروني</p>
<br>
 <label>
 اسم المستخدم      :      <?php echo $_SESSION['user']['name']; ?>    <br><br>
 </label>
 <label>
 البريد الإكتروني      :      <?php echo $_SESSION['user']['email']; ?>     <br><br>
 </label>
 <br><br><br>
 <p>بعد تسجيل حسابك  لن يطلبك تسجيل مره اخرا وشكرا</p><br><br>
   </center>
   <div >
<h3 class="hh3">
     سيتم نقلك الى الاختبار تلقائي بعد 5 ثواني    
</h3><br><p>اذا لم ينتقل اضغط  </p><a href="">هناء</a>

 </div>
   <div ><?php  ?></div>
   <div><?php    ?></div>
   <div><?php   ?></div>
   <div></div>
   </main>

   
   

   
    
   

  
   
</body>
</html>
   <?php
   exit();
}
?>


