<?php
require "upload.php";
$sql="CREATE TABLE uploads (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filesize INT(10) NOT NULL,
    filetype VARCHAR(100) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if(mysqli_query($con,$sql)){
    echo "the data base is created successfully";
}

?>