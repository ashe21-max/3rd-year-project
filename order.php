<?php

require "connect.php";
$sql ="select id,fname,lastname,sex from student limit 3,4";

$result=mysqli_query($con,$sql);
echo "<table border=1>";
echo" <tr><td>id</td><td>fname</td><td>lastname</td>
<td>sex</td></tr>";

while($row=mysqli_fetch_assoc($result)){
   echo "<tr><td>{$row["id"]}</td><td>{$row["fname"]}</td>
   <td>{$row["lastname"]}</td><td>{$row["sex"]}</td>";
}
mysqli_close($con);
?>