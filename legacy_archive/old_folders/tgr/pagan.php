<?php 
session_start();
 $namel=$_GET['idnameqiuz'];
    // echo $namel;
      
$dbHost="localhost";
$dbUser="root";
$dbPass="root1234";
$dbName="t";
$con = mysqli_connect($dbHost,$dbUser,$dbPass,$dbName);
//echo $na;
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

   $selectquiz = json_encode($json_array);
   $_SESSION["selectquiz"] =$selectquiz;
  
 
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Quiz App PHP</title>
    <link rel="stylesheet" href="main.css" />
    <style>
      .clasdiv{
  visibility: hidden;
}
    </style>
  </head>
  <body>
    <div class="clasdiv">
    <?php 
   include("dataquiz.php");
   // echo "<br>";
///echo $_POST['namel'];
    // ' طريقة الارسال اول ماديخل الصفحة تلقاي يتم الرسال 

    ?>
    </div>
    
    <script src="jQuery v2.1.js"></script>
   
   <script>
    /*
     function submitname() {
      var xnn = new XMLHttpRequest();
      var nameloga = document.getElementById("nlo").value;
      xnn.onreadystatechange =function() {
        if(xnn.readyState == 4 && xnn.status == 200){
          document.getElementById("nd").innerHTML =xnn.responseText;
        }
      };
      url ="dataquiz.php?mama="+nameloga;
      xnn.open("GET", url, true);
      xnn.send();
    } */

   // document.getElementById("cf").submit();
  // var input = document.getElementById("nol").value;
     //console.log(input);  
/*
    
        let loginform=document.getElementById("cf");
    loginform.nosubmit =(form)=>{
      form.preventDefault();
      let formData = new Formdata(loginform);
      fetch("dataquiz.php",{
        method:"POST",
        body:formData
      }).then(response => response.text()).then(data =>{
        console.log(data);
      })

    }
    */
   
   
     /*
    $(function() {
      $('#cf').ready(function() {
        var input =$("#nlo").val();
        var byjson ={'text':input};
        $.ajax({
      url: "dataquiz.php",
      type:"post",
      data:byjson,
      success: function(dat){
      var d= $('.namequiz').text(dat);
      console.log(print_r(d));
      }
    });

return false;
     });
    
});*/


/*
$(document).ready(function() {
  $('#cf').click(function() {
$.post("dataquiz.php",{no1:$('#nlo').val()},
function(r){
  console.log(r);

});
});
 });
 */


/*
$(document).ready(function() {
  $('#cf').click(function() {
$.post("dataquiz.php",{no1:$('#nlo').val()},
function(r){
  console.log(r);

});
});
 });*/
   </script>
   
   
    <div class="quiz-app">
      <div class="quiz-info">
        <div class="category">نوع الاختبار : <span id='nd'> </span></div>
        <div class="count">عدد الاسئلة : <span></span></div>
      </div>
      <div class="quiz-area"></div>
      <div class="answers-area"></div>
      <button class="submit-button">ارسال الاجابة</button>
      <div class="bullets">
        <div class="spans"></div>
        <div class="countdown"></div>
      </div>
      <div class="results"></div>
      
    </div>
    <script src="maint1.js"></script>
  </body>
</html>