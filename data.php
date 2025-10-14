<?php
require "connect.php";
$sql="create table student(id int(5) unsigned 
auto_increment primary key,
fname varchar(30),
lastname varchar(30),
sex varchar(30),
reg_date timestamp default current_timestamp on update current_timestamp
)";
if(mysqli_query($con,$sql)){
    echo "connected to the table successfully!\n";
}
else{
    echo "error ocurred in this".mysqli_error($con);
}
mysqli_close($con);
?>