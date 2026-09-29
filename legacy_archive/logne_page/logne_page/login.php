<?php
session_start();   //بداية الجلسة 
if(isset($_SESSION['user'])){     // شرط اذا كان المستخدم موجود ينقله الى الصفحة تم التسجيل 
   
    header('location:profile.php?page2='+$s);
  exit();
}
if(isset($_POST['submit'])){ 
 include 'conn-db.php';
   $password=filter_var($_POST['password'],FILTER_SANITIZE_STRING);
   $email=filter_var($_POST['email'],FILTER_SANITIZE_EMAIL);
  
   $errors=[];
   

   // validate email
   if(empty($email)){
    $errors[]="يجب كتابة البريد الاكترونى";
   }


   // validate password
   if(empty($password)){
        $errors[]="يجب كتابة  كلمة المرور ";
   }



   // insert or errros 
   if(empty($errors)){  //شرط اذا كانت المخزن الايوجد فيه اخطاء 
   
      // echo "check db";

    $stm="SELECT * FROM users WHERE email ='$email'";
    $q=$conn->prepare($stm);
    $q->execute();
    $data=$q->fetch();
    if(!$data){
       $errors[] = "خطأ فى تسجيل الدخول";
    }else{
        
         $password_hash=$data['password'];   //  يقوم بتشفير كلمة المرور 
         
         if(!password_verify($password,$password_hash)){
            $errors[] = "خطأ فى تسجيل الدخول";
         }else{
            $_SESSION['user']=[
                "name"=>$data['name'],
                "email"=>$email,
              ];
            header('location:profile.php?n=1'); //  صفحة تم التسجيل 
          
      
        
         }
    }
     
    
   }
}

?>
 <script>    // لكي لايعيد ارسال النموذج مره اخرا 
  if(window.history.replaceState){
    window.history.replaceState(null,null,window.location.href);
  }

 </script>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="slohin.css">
    <title> تسجيل دخول </title>
</head>
<body id ="id">
<header>
    <form method="post">
    <div class="div1"><center>
    <div><a href="http://127.0.0.1/php/home_quiz/">الرئسية </a></div>
    <div><a href="register.php"> انشاء حساب جديد  </a></div>
   </center>
   </div>
    </form>
   
    </header>
    <main> 
       
            <div class="classdiv">
                <form action="login.php" method="post">
                <?php     //  كود الذي يعرض ريسالة الخطاء
        if(isset($errors)){
            if(!empty($errors)){
                foreach($errors as $msg){
                    echo $msg . "<br>";
                }
            }
        }
    ?>
                   <div>
                    <label> البريدالكتروني </label>
                    <input type="email"class="in2" value="<?php if(isset($_POST['email'])){echo $_POST['email'];} ?>" name="email" placeholder="email">
                </div>
                   <div>
                    <label>كلمة  المرور </label>
                    
                    <input type="password"class="in2" name="password" placeholder="كلمة السر">
                
                </div><br><br><br>
                   <button  type="submit" name="submit">دخول</button>
                   <br><br>
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
   
   
   
