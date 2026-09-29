<?php 
session_start();
  $b=$_SESSION['namelw'];
 
 
 
$dbHost="localhost";
$dbUser="root";
$dbPass="root1234";
$dbName="t";
$con = mysqli_connect($dbHost,$dbUser,$dbPass,$dbName);
//echo $na;

   // $data =file_get_contents("php://input");
    //$user =json_decode($data,true);
   
  // $tt=print_r($_POST["n"]);
    //$tt=$user["us"];
  
  //file_put_contents($json_encode($ar_j));
   //$tt =$user["us"];
  
  

 
$myselect="SELECT * FROM insertquis where namelang='$b'";
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

  // $selectquiz = json_encode($json_array);
   $selectquiz = json_encode($json_array);
  echo $selectquiz;

?>
