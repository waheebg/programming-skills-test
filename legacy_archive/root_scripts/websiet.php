<?php
session_start();//  بدايت الاجلسة  
?>
<script>
 
 function html(){
    <?php
        $html =$_POST['html'];
        $php =$_POST['php'];
        $css =$_POST['css'];
        if($html == 'html'){
            if(isset($_SESSION['user'])){ 
              header('location:http://localhost/php/home_quiz/qi/?n=1');
            }else{
              header('location:http://localhost/php/home_quiz/logne_page/login.php?n=1');
            }
        
        }
        if($php == 'php'){
          if(isset($_SESSION['user'])){ 
            header('location:http://localhost/php/home_quiz/qiuz_php/?n=1');
          }else{
            header('location:http://localhost/php/home_quiz/logne_page/login.php?n=1');
          }
        }
        if($css == 'css'){
          if(isset($_SESSION['user'])){ 
            header('location:http://localhost/php/home_quiz/qiuzcss/?n=1');
          }else{
            header('location:http://localhost/php/home_quiz/logne_page/login.php?n=1');
          }
        }
        ?>
 }
 

</script>
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
    <link rel="stylesheet" href="web.css.css">
    <title>wib-siet</title>
</head>
<body>
    <header>
        <div id="div-top">
            <div><a href="http://localhost/php/home_quiz/?n=1">الصفحة الرئسية </a></div>
            <div>
            <a href="http://localhost/php/home_quiz/logne_page/login.php">انشاء حساب جديد</a> 
            </div>
            <div>
               <a href="http://localhost/php/home_quiz/logne_page/login.php">دخول</a>
            </div>
            <div onclick="alert()"> الاشروحات</div>
           
        </div>
    </header>
      <main>
        
       <div id="idimg">
       </div><br><br>
            <div > 
                <h2 class="hhg">تمهيد قبل بداية التحدي</h2>
            </div>
        <div id="T">
            <div class="T1">
                <img src="gif/html-intro.gif" alt="HTML">
                <h1>لغة اتش تي ام ال HTML  </h1><br>
                <p>
                    HTML أو HyperText Markup Language هي أساس تطوير الويب الذي يحتاج كل مطور إلى معرفته.  وهي اللغة الثانية الأكثر استخدامًا. على الرغم من أنها ليست لغة برمجة كاملة، إلا أنها اللغة القياسية المستخدمة لإنشاء صفحات الويب. توفر HTML بنية صفحات الويب وهي المسؤولة عن التنسيق المناسب للنصوص والصور.
                </p>
                <h3>الايجابيات </h3>
                <p>
                    سهلة التعلم والتنفيذ.<br>
                    مدعومة من كل متصفح.<br>
                    مجانية ويمكن الوصول إليها.    <br>                
                </p>
                <h3>سلبيات</h3>
                <p>
                    لغة ثابتة، لذلك لا يمكن إنشاء صفحات ديناميكية.<br>
تحتاج إلى كتابة الكثير من التعليمات البرمجية لتطوير صفحة ويب بسيطة.<br>
                </p>
               </div>
               <div class="T1">
                <img src="gif/e.jpg" alt="js">
                <h1>لغة جافا سكريبت JavaScript </h1><br>
              <p>
                هي لغة البرمجة الأكثر استخدامًا في العالم. أحد أسباب شعبيتها هو أنه يمكن استخدامها لتطوير الويب للواجهة الأمامية والخلفية. يتم استخدامه لإضافة السلوك والتفاعل إلى صفحات الويب. تعد JavaScript خيارًا مفضلًا للمطورين لإنشاء عناصر ويب ديناميكية مثل الأزرار القابلة للنقر أو الرسومات المتحركة.إلى جانب تطوير الويب، يمكن أيضًا استخدامها لتطوير تطبيقات الأجهزة المحمولة والألعاب. 
<br>
                الايجابيات<br>
                
                سريعة جدًا.<br>
                سهلة الدمج مع اللغات الأخرى.<br>
                 بسيطة ومتعدد الاستخدامات.<br>
                سلبيات<br>
                
                يتطلب من المطورين تشغيل الكود على منصات متعددة مخصصة لضمان عدم حدوث أي خلل فني. 
                أقل أمانًا مقارنة باللغات الأخرى.
                
              </p>
               </div>
               <div class="T1"><br>
                <img src="gif/What-is-PHP.gif" alt="php">
                <h1>  لغة بي إتش بي PHP</h1><br>
                       <p>
                        PHP أو Hypertext Preprocessor هي لغة برمجة نصية مفتوحة المصدر تستخدم لتطوير الواجهة الخلفية، الغالب لتطوير مواقع ويب ديناميكية مليئة بالبيانات. إنها واحدة من أكثر لغات صفحات الويب شيوعًا، وتستفيد أطر عمل مثل Drupal و WordPress من PHP.
<br>
                        الايجابيات
                        <br>
                        متوافقة مع الخدمات السحابية.<br>
                        سهلة التعلم والاستخدام.<br>
                        يمكن استخدامها على جميع أنظمة التشغيل الرئيسية.<br>
                        <br>
                        سلبيات
<br>                        
                        ميزات معالجة الخطأ ليست ممتازة.<br>
                        يمكن أن يكون التطوير باستخدام PHP بطيئًا فقط.<br>
                        
                       </p>
               </div>
               

            </div>
            <!---->
           <h2>التحدي الذي قمنا في انشاءه من اجلك </h2>
            <div class="TT1">
                <form method="post">
                    <center>
                
                        <div class="TT1-android"><br>
                            <img src="img/html2.png" ><br><br><p>HTML</p><br><br><br><br>
                            <button name="html" value="html" onclick="html()"> html</button>
                           <br><br>
                         </div>
                         <div class="TT1-android"><br>
                             <img src="img/jsicon.png" ><br><br><p>جافا سكريبت</p><br><br><br><br>
                             <button name="javajs" value="javajs" onclick="alert()"> javajs</button><br><br>
                          </div>
                          <div class="TT1-android"><br>
                             <img src="img/phicon.png"  ><br><br><p>PHP</p><br><br><br><br>
                             <button name="php" value="php"> php </button><br><br>
                          </div>
                          <div class="TT1-android"><br>
                             <img src="img/css2.png" ><br><br><p>CSS</p><br><br><br><br>
                             <button name="css" value="css"> css </button> <br><br>
                         
                            </div>
                          <div class="TT1-android"><br>
                             <img src="img/python2.png" ><br><br><p>python</p><br><br><br><br>
                             <button name="python" value="python" onclick="alert()"> python </button><br><br>
                          </div>
                          <script>
      function alert() {
         var a = window.confirm('القسم  او الغه غير متوفر سيتم اضافتة في اقرب وقت ');
    console.log('ok');
      }
   </script>
                      </center>
                </form>
               </div>
           
        
       
      </main>
      <footer>
        <center>
        
        </center>
        <div>#</div>
        <div>#</div>
        <div>#</div>

       
      
      </footer>
</body>
</html>