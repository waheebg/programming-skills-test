<?php
session_start();
  $i=strip_tags($_POST['incommet']);
if(isset($_POST['butcomment'])== true){
    $errors=[];
  if(!isset($_SESSION['user'])){
    $errors[]="الموقع لم يتعرف عليك  يجب ادخال بريدك الاكتروني  واذا لم تسجل بريد في الموقع من قبل قم بانشاء حساب جديد<br> 
      لتسجيل الدخول وا انشاء حساب <br><a href='http://localhost/php/home_quiz/logne_page/login.php?n=1' >تسجيل الدخول</a>";
    
     }else {
      include 'connhome.php';
      $uname=$_SESSION['user']['name'];
     $req="SELECT id FROM users where name= '$uname'";
     $query= mysqli_query($con,$req);
     while($fetch =mysqli_fetch_assoc($query)){
   $id= $fetch['id'];
     }

      $page ="الصفحه الرئسيه";
    $insert ="INSERT INTO comments (comment,userid,pagename) VALUES ('$i','$id','$page')";
     $q = mysqli_query($con,$insert);
    
    
     }
    
    
     
}


?>
<script>    // لكي لايعيد ارسال النموذج مره اخرا 
  if(window.history.replaceState){
    window.history.replaceState(null,null,window.location.href);
  }

 </script>
 <!DOCTYPE html>
<html>
    <head>
        <title>شاشة الترحيب </title>
        <meta charset="uTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewpor" content="width=device-width,inital-scale">
        <link rel="stylesheet" href="styelhome.css">
      <style>
        .dept{
    width: 60%;
   display: inline-block;
  margin-left: 150px;
  }
.dept div{
    width: 200px;
    height: 330px;
    border-radius: 60px 0px 50px 0px;
  background-position: center;
  box-sizing: border-box;
  z-index: -9;
  float: left;
  background-color:#ffffffa8;
    margin: 10px;
    padding: 20px;
    margin-left: 150px;
}
.img-long{
    width: 150px; 
    height: 100px;
    border-radius: 130px;
 }
.input-bu{
    width: 100px;
    height: 70px;
    padding: 2px;
    border-radius: 50%;
}
.input-dname{
  text-align:center;
  background-position: center;
  border-radius:17% ;
 background:transparent;
 
  
 

}
.ccc{
  width: 100%;
   height: 300px;
  background-image: url("img/q.jpg");
  background-size: cover;
 
  }
  .input-but{
         width: auto;
    height: auto;
background-color: hsl(261, 93%, 53%,0.6)  ;
text-decoration: none;
font-size: 14px;
padding: 10px;
border-radius: 50%;
color:#fff;

      }
     </style>
    </head>
    <body>
     
 <header>

 <div  class="pp">
          <!-- القوائم-->
          
         <div class="menu-bar"><!-- القايمة الرئسية-->
            <ul>
            <span><li  class="active"><a href="#">الرئسية</a></li></span>
          <span> <li><a href="http://localhost/php/home_quiz/logne_page/">تسجيل الدخول </a></li>
               </span>

          <span>  <li><a href="#">معلومات  عن</a>
                      <div class="sub-menu-1"><!-- القايمة الاولة-حق اضافت الاسيلة-->
                        <ul>
                            <li class="hover-me"><a  href="#">تطبيقات الاندرويد</a>
                            <div class="sub-menu-2"><!-- زرار لضهار قايمة اخرا داخل قايمة -->
                                <ul>
                                    <li><a href="#">java</a></li>
                                    <li><a href="#">kotlin</a></li>
                                    <li><a href="#">معلومات عامه</a></li>
                                </ul>
                            </div>
                            </li>
                            <li><a  href="#">iso</a></li>
                            <li  class="hover-me" ><a href="#">Wab Sites</a>
                            <div class="sub-menu-2"><!-- زرار لضهار قايمة اخرا داخل قايمة -->
                                <ul>
                                    <li><a href="#">HTML</a></li>
                                    <li><a href="#">CSS</a></li>
                                    <li><a href="#">PHP</a></li>
                                    <li><a href="#">JAVA SCRIPT</a></li>
                                </ul>
                            </div>
                          </li>
                            <li><a href="#">C#</a></li>
                            <li class="hover-me"><a href="#">DATABASES
                            <div class="sub-menu-2"><!-- زرار لضهار قايمة اخرا داخل قايمة -->
                                <ul>
                                    <li><a href="#">sql</a></li>
                                    <li><a href="#">mysql</a></li>
                                </ul>
                            </div>
                            </a></li>
             </ul> 
         </div>
     </li>
              </span>
                
                
               
         
            </ul>
        </div>
      </div>
  </header>
  
  <main>
  
  <div class="ccc"> <div></div></div>
  <div id="T">
  <div class="class1">
    <h3> شروط الموقع لدخول الى الاختبار </h3><br>
    <p>ان يكون لدية حساب في اللموقع </p>
    <p>واذا لم يكن لديك حساب من قبل قم بانشاء حساب جديد </p>
    <p> حفظ حسابك الذي قمت بي تسجيلة </p>
    <br><br><br>
    <h3>شروط اضافة تعليق </h3><br>
    <p>ان يكون لديك حساب في الموقع</p>
    <br><br>
    <h3>الا قسام الموجوده حاليا</h3>
    <p>تطبيقات الاندرويد</p><br>
    <p>تصميم وبرمجة الانترنت</p><br>
    <p>قواعد البيانات </p><br>
    <br><br>
    <h3>الغات الموجوده حاليا</h3><br>
    <p>php</p><br>
    <p>css</p><br>
    <p>HTML</p><br>
    <br><br>
    <h3>ملاحضة </h3>
    <p>سيتم اضافة بقية الغات في وفت لاحق وذالك في التحديث الجاي وسيتم اضافة شروحات اذا ازداد تفاعلكم</p>
<br>  
</div>
<div class="T1"><br>
                <img src="img/logo.png" alt="!">
                <h1>البرمجة التنافسية   :</h1><br>
               <p id="p1">
               هي رياضة ذهنية ُتقام عاد ًة عبر الإنترنت أو عبر شبكة محلية،<br> وتشمل المشاركين الذين
       يحاولون البرمجة وف ًقا لمعاير محددة. تعد البرمجة التنافسية شيء معروف لدى العديد من شركات البرمجيات
       متعددة الجنسيات والكبرى في الأنترنت، حيث تقدم تلك الشركات برامج فعاليات تحتضن رياضة البرمجة
       التنافسية، ومن أبرزها 1 [Google [وFacebook.
             </p>
               </div><br><br><br><br>
               <div class="T1">
                <img src="gif/ew.JPEG" alt="!">
                <h2> مقالة ستفيدك اذا كنت مبتدا</h2><br>
                <h1>لو انت مبتدا في البرمجة :</h1><br>
              <p id="p2">
              أضن أن الأساسيات هي أن تعلم في ماذا تستعمل و كيف تستعمل
               و ما هدف جميع لغات البرمجة ، فالأشياء التي يجب أتطن تعرفها هي في ماذا تريد أن تستخدم لغات البرمجة ، هل تريد أن تكون مبرمج تطبيقات
                او مطور تطبيقات في هذه الحالة ، هناك مسار للغات عليك ان تتعلمه مثل java و لغة xml
              </p>
               </div>
               <div class="T1">
               
                <h3>أما التوحيه الآخر هل تريد أن تكون مبرمج مواقع أو مصمم مواقع او مطور مواقع . </h3><br>
              <p>
              مبرمج مواقع : هنا أدنى شيئ علكوتعلمه و هو لغة البرمجة js 
              </p>
               </div><br>
               <div class="T1">
              <h3>مصمم مواقع : طبعا هنا تحتاج لتعلم لغتين أساسيتين في تصميم المواقع html و css</h3><br><br>
              <h1>مطور مواقع </h1><br>
              <p id="p3">

              : هنا يجب أن تتعلم سلسلة من اللغات بشكل متسلسل في الأول ستتعلم لغة html ستستطيع بها وضع أساسات الموقع ،
 و ثم لغة css ستقوم بها بوضع الألوان الأشكال … و من ثم سينقصك تفاعل ستتعلم لغة js و من بعدها تحتاج لغة php لعدة أدوار و كونها الأساسية 
في نقل و جلب المعلومات من database ومن بعدها ءستحتاج الى sql التي ستقوم ببرمجة قاعدة البايانات ، هي سلسلة تماما كالمنهاج الدراسي .

              </p> 
              </div>
             
              <marquee direction="left" behavior="Alternate">
               <img src="gif/html-intro.gif" alt="!" >
               <img src="gif/andpak.gif" alt="!">
               <img src="gif/pp.gif" alt="!">
               
               </marquee>
              
    </div><br><br>
    <center>
    <div class='dept'>
    <?php 
    $dbHost="localhost";
    $dbUser="root";
    $dbPass="root1234";
    $dbName="t";
    $conm = mysqli_connect($dbHost,$dbUser,$dbPass,$dbName);

    $select1="select * from dept";
    $rqs=mysqli_query($conm,$select1);
    while($row = mysqli_fetch_array($rqs)){
         $dname=$row['deptname'];
         $imagss=$row['deptimg'];
         $link=$row['on_off_link'];
    echo"<center>";
    //echo "<form action='android.php' method='post' enctype='multipart/form-data'>";
    echo"<div class='As'>";
    echo"<img src='http://localhost/php/control/$imagss' alt='$dname' class='img-long' /><br><br>"."<br>";
     $button="document.location='$link'";
    //<input type='text'  name='dept1' value='$dname' class='input-dname' readonly  />
    echo "<br>"."<p><br>$dname</p><br><br>";
     //echo " <input type='submit' name='namedept' class='input-bu' value='دخول القسم '  ></input><br>";
     
     ?><a href="http://localhost/php/home_quiz/android.php?name_dept_page=<?php echo $dname ;?>" class="input-but">دخول القسم </a>
     <?php
    // $urltext="Contact Us";
    // echo "<a href='$link&name="."$dname"."'> $urltext</a>";
    echo"</div>";
    //echo "</form>";
    echo"</center>";
    }
    //onclick=document.location='$link'
    ?>
    
    <?php 
     
   /*
    if(isset($_POST['namedept'])){
            $t=$_POST['dept1'];
            $selec="select * from dept where  deptname ='$t' ";
            $rqss=mysqli_query($conm,$selec);
            while($roww = mysqli_fetch_array($rqss)){
                 $deptname=$roww['deptname'];
                 $imagss=$roww['deptimg'];
                 $link=$roww['on_off_link'];
                
                 echo $deptname;  
                 ///echo $link;
               
                //$selec="select * from elment where  deptname ='$t' ";
              // $rqss=mysqli_query($conm,$selec);
           // while($roww = mysqli_fetch_array($rqss)){
      
           // }
           
          }
        }
           */
 
    ?>
    
</div>   
<?php 
   
    ?>
    </center>
    <br>
   
           
             <script>
      function alert() {
         var a = window.confirm('القسم  او الغه غير متوفر سيتم اضافتة في اقرب وقت ');
    console.log('ok');
      }
   </script>
                </center>
              
             </div></center><br>
             <br>
  </main><br><br>
     
  <footer>
             <div class="commet">
             <div>
             <fieldset>
              <center><p id="msg">
              <?php 
        if(isset($errors)){
            if(!empty($errors)){
                foreach($errors as $msg){
                    echo $msg . "<br>";
                }
            }
        }
    ?> 
            </p>
         
          </center> 
             <legend> اضف تعليق  </legend>
             <form action="index.php" method="POST">
              <div class="boxtext">
              <div> 
             <textarea type="text"  name="incommet" id="textcomment" class="texta" rows="3"
           placeholder="اكتب تعليق" required
          
          ></textarea></div>
            <div class="divget">
            <button type="submit" class="input_submit" name="butcomment"></button> </div>
              </div>
              </form>
            </fieldset><br>
            <p>التعليقات </p><br>
            <div class="showcomint">
              <div class="commint1">
              <?php include "D:/AppServ/www/php/com.php";?>
              </div>
            </div>
           
           
             </div>
            
          
                
             
             
             </div><br><br>
             <div class="linkf">
             <div class="paro">
                <center>
                    <h4>العودة الى الفقرات السابقة</h4><br>
                <a href="#p1">البرمجة التنافسية</a><br><br>
                 <a href="#p2">لوانت مبتدا في البرمجة</a><br><br>
                 <a href="#p3">مطور المواقع</a><br><br>
                </center>
                
             </div>
             <div class="parocenter">
                <center><h4>هل تريد الانتقال الى قسم من التالي </h4><br>
                <a href="websiet.php?n=1">قسم تصميم وبرمجة الانترنت </a><br><br>
                 <a href="android.html?n=1">قسم الاندرويد</a><br><br>
                 <a href="datebase.html?n=1">قسم قواعد البيانات</a><br><br>
                </center>
             </div>
             <div class="linkfoot">
                <center>
                    <h4>من نحن</h4><br>
              <a onclick="myi()">معلومات عن مصمم ومبرمج الموقع</a><br><br>
              <a  onclick="myiweb()">من هم المشتركين في الموقع</a><br><br>
               <a onclick="myget()">تواصل معنا </a><br><br>
              
                </center>

             
             </div>
             </div><br><br>
            
 <br>
 <div><center>لا تنسو الاشتراك في حساباتنا على منصات التواصل الجتماعي </center></div><br>
 
 
 <div class="divswot">
   
    <center>
    <div> <img  class="img" src="img/i1.png" alt="فيسبوك" /></div>
    <div><img class="img" src="img/i2.png" alt="انستجران"/></div>
    <div><img  class="img" src="img/i3.png" alt="وتس اب"/></div>
</center> 
 </div>

 
 <br> <div> <center> <p>شكرن على زيارتكم </p>  </center></div><br><br>
  </footer>
  <script>
      function myi() {
         var a = window.confirm('وهيب امين طالب في المعهد التقني تخصص تقنية معلومات');
    console.log('ok');
      }
     
      function myget() {
         var a = window.confirm('تلفون 734439126');
    console.log('ok');
      }
   </script>
    <?php //include("D:/AppServ/www/php/control/selectdept.php");?>
    </body>
</html>