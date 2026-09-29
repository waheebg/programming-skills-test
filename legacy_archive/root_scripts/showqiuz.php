<?php 
 

$namel=$_GET['idnameqiuz'];
echo $namel;

    include("connhome.php");
    if($con){
        $myselect="SELECT * FROM insertquis where namelang='$namel'";
        $query2=mysqli_query($con,$myselect);
        $json_array = array ();
        while ($rowq=mysqli_fetch_assoc($query2)) {

          $json_array[] =$rowq;


            // `title`, `, `Anwesrt2`, `Anwesrt3`
            //, `Anwesrt4`, `Anwesrttrue`, `usermoid`, `namelang`
            /*
        $title=$rowq['title'];
        $Anwesrt1=$rowq['`Anwesrt1'];
        $Anwesrt2=$rowq['`Anwesrt2'];
        $Anwesrt3=$rowq['`Anwesrt3'];
        $Anwesrt4=$rowq['`Anwesrt4'];
        $Anwesrttrue=$rowq['Anwesrttrue'];
        $namelang=$rowq['namelang'];

*/
}
  echo  json_encode($json_array);
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Quiz App</title>
    <link rel="stylesheet" href="showqui.css" />
  </head>
  <body>
    <?php 
   // if(){
        echo"<center>"."لايوجد اسئلة في هاذة الغة بعد سيتم اضافتها في اقرب وقت</center>";
    //}
    ?>
    <div class="quiz-app">
      <div class="quiz-info">
        <div class="category">نوع الاختبار : <span><?php //echo $namelang;  ?></span></div>
        <div class="count">عدد الاسئلة : <span></span></div>
      </div>
      <div class="quiz-area">
      <?php //echo $title;  ?>
      </div>
      <div class="answers-area"></div>
      <button class="submit-button">ارسال الاجابة</button>
      <div class="bullets">
        <div class="spans"></div>
        <div class="countdown"></div>
      </div>
      <div class="results"></div>
      
    </div>
    <script src="main.js"></script>
   
    
  </body>
</html>