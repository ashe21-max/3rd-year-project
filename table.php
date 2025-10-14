<?php
require "connect.php";
$sql="insert into  student(fname,lastname,sex) values (?,?,?)";
$stmt=$con->prepare($sql);
$stmt->bind_param("sss",$fname,$lastname,$sex);
$fname="kidus";
$lastname="gizachew";
$sex="male";
$stmt->execute();

$fname="samuel";
$lastname="alebachew";
$sex="male";
$stmt->execute();

$fname="tesfaye";
$lastname="teshale";
$sex="male";
$stmt->execute();

if(mysqli_query($con,$sql)){
    echo "connected to the table successfully!\n";
}
else{
    echo "error ocurred in this".mysqli_error($con);
}
$stmt->close();
mysqli_close($con);
?>