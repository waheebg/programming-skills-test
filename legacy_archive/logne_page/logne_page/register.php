<?php
// صفحة انشاء الاحسابات  الجديدة
session_start();//  بدايت الاجلسة  
if(isset($_SESSION['user'])){ 
    header('location:profile.php?n=1'); 
    exit();
}


if(isset($_POST['submit'])){ //   فلترة البيانات 
include 'conn-db.php';// عملة تستدعي ملف الاتصال بي قاعدة البيانات
   $name=filter_var($_POST['name'],FILTER_SANITIZE_STRING); //عمل فلترة لبيانات لكي لايحدث خطاء 
   $password=filter_var($_POST['password'],FILTER_SANITIZE_STRING);
   $email=filter_var($_POST['email'],FILTER_SANITIZE_EMAIL);

   $errors=[];   // لاتخزين الاخطاء


   // valiterdate name
   if(empty($name)){   
       $errors[]="يجب كتابة الاسم";
   }elseif(strlen($name)>60){
       $errors[]="يجب ان لايكون الاسم اكبر من 60 حرف ";
   }

   // validate email
   if(empty($email)){
    $errors[]="يجب كتابة البريد الاكترونى";
   }elseif(filter_var($email,FILTER_VALIDATE_EMAIL)==false){
    $errors[]="البريد الاكترونى غير صالح";
   }

   $stm="SELECT email FROM users WHERE email ='$email'";
   $q=$conn->prepare($stm);
   $q->execute();
   $data=$q->fetch();

   if($data){ // شرط ان لايكون البريد مكرر 
     $errors[]="البريد الاكترونى موجود بالفعل";
   }


   // validate password
   if(empty($password)){
        $errors[]="يجب كتابة  كلمة المرور ";
   }elseif(strlen($password)<6){
    $errors[]="يجب ان لايكون كلمة المرور  اقل  من 6 حرف ";
}



   // insert or errros 
   if(empty($errors)){
      // echo "insert db";
      $password=password_hash($password,PASSWORD_DEFAULT); // لتشفير كلمة المرور 
      $stm="INSERT INTO users (name,email,password) VALUES ('$name','$email','$password')";
      $conn->prepare($stm)->execute();
     echo "تم الاضافة";
    
     $_POST['name']='';
   $_POST['email']='';

   $_SESSION['user']=[
     "name"=>$name,
     "email"=>$email,
   ];
  

      header('location:profile.php?n=1');
    
   }
}

?>
 <script>    // لكي لايعيد ارسال النموذج مره اخرا 
  if(window.history.replaceState){
    window.history.replaceState(null,null,window.location.href);
  }

 </script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styltt.css">
    <title>انشاء حساب جديد</title>
</head>
<body id ="id">
<header>
   <div class="div1"><center>
    <div><a href="http://127.0.0.1/php/home_quiz/?n=1">الرئسية </a></div>
    <div><a href="login.php?n=1"> تسجيل الدخول  </a></div>
   
   </center>
   </div>
    </header>
    <main>
       
            <div class="classdiv">
                <form action="register.php?n=1" method="post">
                <?php 
        if(isset($errors)){
            if(!empty($errors)){
                foreach($errors as $msg){
                    echo $msg . "<br>";
                }
            }
        }
    ?> 
    
    
    <div>
        <label> اسم المستخدم</label>
        <input type="text" class="in1" value="<?php if(isset($_POST['name'])){echo $_POST['name'];} ?>" name="name" placeholder="name">
    </div>
                   <div>
                    <label> البريدالكتروني </label>
                    <input type="email"class="in1" value="<?php if(isset($_POST['email'])){echo $_POST['email'];} ?>" name="email" placeholder="email">
                </div>
                   <div>
                    <label>كلمة  المرور </label>
                    
                    <input class="in2" type="password" name="password"placeholder="كلمة المرور">
                
                </div><br><br><br>
                   <button  type="submit" name="submit" value ="submit">دخول</button>
                </form>
               </div>
    </main>
    <footer>
        <div class="clss">
        <div><center><h3>ملاحضة</h3><br></center>
<p>يجب عليك انشاء حساب جديد لكي تستطيع الدخول الى صفحة التحدي وتقيس متسواك ولتقويه مهاراتك واذا قد قمت بنشاء حساب من قبل فقم بتسجل الدخول 
</p>
<br><br></div>
       
        </div>
    </footer>


</body>
</html>