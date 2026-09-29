<?php 
 

 //$namel=$_GET['idnameqiuz'];
 //echo $namel;

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