<?php 
session_start();

$namel=$_GET['idnameqiuz'];
 $namel;
 $_SESSION['namelw']="$namel";

//session_register("namel"); 
if(!isset($_SESSION['user'])){ 
        
  header('location:http://localhost/php/home_quiz/logne_page/login.php?name_logo_page='.$namel);
}else{
 

?>
<!DOCTYPE html>

<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Quiz App PHP</title>
    <link rel="stylesheet" href="mi.css" />
  </head>
  <body>
   
  <script>
    /*
    let logform = document.getElementById("loginform");
    logform.onload =(form)=>{
      form.PreventDefault();
      let data1 = new Data1(logform);
      fetch("dataw.php",{
      method:"POST",
      body:data1
    }).then(response => response.text()).then(data =>{
      console.log(data);
    })
    }
  
  */

   /*
   let user={"us":"<?php //echo $namel ?>"}
    fetch("dataw.php",{
      "method":"POST",
      "headers":{
        "Content-Type": "application/json; charset=utf-8"
      },
    "body": JSON.stringify(user)
    }).then(function(response){
      return response.text();
    }).then(function(data){
     // console.log(data);
    });
   
    */
    </script>
   
    <div class="quiz-app">
      <div class="quiz-info">
        <div class="category">نوع الاختبار : <span><?php echo $namel?></span></div>
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

   <script src="mii.js"></script>
  </body>
</html>
  <?php
}
?>