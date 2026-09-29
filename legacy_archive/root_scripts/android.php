<?php 
session_start();
$dbHost="localhost";
$dbUser="root";
$dbPass="root1234";
$dbName="t";

$namede=$_GET['name_dept_page'];
$_SESSION['page']=$_SERVER['REQUEST_URI'];
//ECHO $s;
           if(isset($namede)){

            //$t=$_POST['dept1'];
           
            $conm = mysqli_connect($dbHost,$dbUser,$dbPass,$dbName);
            $sql="select * from elment where  namedept_1 ='$namede'";
                $q=mysqli_query($conm,$sql);
                $result = mysqli_fetch_row($q);
                    if(!$result){
               
                        echo "<br>";
                        echo"<center>القسم غير متوفر حاليا.......</center>";
                        echo '<meta http-equiv="refresh" content="2; url=http://localhost/php/home_quiz/">';
                           }else{
                              
                             //  echo"<br>"."<center>اهلان بك في القسم   </center>";
                        // include("logeeeen.php");
                              ?>
                              <?php 
$dbHost="localhost";
$dbUser="root";
$dbPass="root1234";
$dbName="t";
$conm = mysqli_connect($dbHost,$dbUser,$dbPass,$dbName);
if($conm){
   $t=$namede;
   //echo $t;
   $sql="select el.*, leng.* ,bu.* from elment el,inlang leng ,bulinkpage bu  where el.namedept_1='$t' && leng.name_dept='$t'  ";
   $query=mysqli_query($conm,$sql);
   
     while ($row =mysqli_fetch_assoc($query)) {
      //table elment
      $name_namedept_1=$row['namedept_1'];
      $imga_dept=$row["imga_dept"];
      $title_page=$row["title_page"];
      // table lang
      $name_ga=$row['name_ga'];
      $img_ga=$row['img_ga'];
      $title_ga=$row['title_ga'];
      $text_ga=$row['text_ga'];
       $imgin_text_ga=$row['imgin_text_ga'];
       $name_dept=$row['name_dept'];

       // table button  
       // namebutt, namelink, aligntoppage, namedept
       $name1=$row['namebutt'];
       $link1=$row['namelink'];
       $align=$row['aligntoppage'];

    ?>
             
             
            
             
      
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="logeen.css">
    <title>page_dept</title>
    <style>
      .input-bu{
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
<script>    // لكي لايعيد ارسال النموذج مره اخرا 
  if(window.history.replaceState){
    window.history.replaceState(null,null,window.location.href);
  }

 </script>
 
 
 
    <header>
        <div id="div-top"><!--استعاء جدول الروابط حسب اسم القسم-->
           <?
         echo "<div onclick=document.location='$link1?n=1'>$name1</div>";
           ?>
        </div>
    </header>
    <nav>
           

<div id="idimg">
   <img src="<?php echo "../control/".$imga_dept;?>"  id='idimg' width=100% height=300px />
            </div><br><br></nav>
           <main>                 
            <div class="title-1" > 
                       <h2><?php echo $title_page;?></h2>
                   </div><br><br><br>
        <div id="gett-informtion"><!--استدعاء الغات -->
            <div class="T1">
                <img src="<?php echo "../control/".$imgin_text_ga;?>" alt="<?php echo $title_ga; ?>"  width=300 hieght=200px  max-height=200px />
                <h3><?php echo $title_ga;?></h3><br>
                <p><?php echo $text_ga ;?> </p>
               </div><br><br>
            </div><br><br>
      </main>
      <section>
         <!---->
            
            <div class="gett-long">
                <center>
<form>
   <div class="div-long">
     <img src='<?php echo "../control/".$img_ga;?>' alt='<?php echo $name_ga;?>' class='img-long' />
     <br><br><p><?php echo $name_ga;?></p>
     <br><br>
     <?php 
    
      $n="http://localhost/php/home_quiz/page_qiuz/pageqi.php?idnameqiuz=$name_ga";
    
     ?>
     
     <a href="<?php echo $n;?>" 
      class='input-bu'>بد الاختبار </a>
   <?php  // <input type='submit' name='namegett' class='input-bu' value='بد الاختبار '  /> ?>
                     </div>
    
                     
</form>
                  
                 </center>
               </div>
               
        
       
      </section><br><br>
      <footer>
        <div class="div-foorter">
        <div class="footer-left">
     <center>
        <div onclick="document.location='#'"></div><br>
        <div onclick="document.location='#'"></div><br>
        <div onclick="document.location='#'"></div><br>
     </center>
        </div>
        <div class="footer-center">
<center>
<div onclick="document.location='#'"></div><br>
        <div onclick="document.location='#'"></div><br>
       
</center>
        </div>
        <div class="footer-right">
        <center>
        <div onclick="document.location='#'"></div><br>
</center>
        </div>
        </div>
        

       
      
      </footer>
      <script>
                function alert() {
                   var a = window.confirm('القسم  او الغه غير متوفر سيتم اضافتة في اقرب وقت ');
              console.log('ok');
                }
             </script>
             <?php

} 
 

}
?>
</body>
</html>
<?php

 
                           }
                }
                ?>