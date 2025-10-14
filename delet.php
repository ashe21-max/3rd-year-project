<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';

// Database to delete
$dbname = 'file_uploads';

// Create connection
$conn = mysqli_connect($host, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Delete the database
$sql = "DROP DATABASE $dbname";

if (mysqli_query($conn, $sql)) {
    echo "Database deleted successfully";
} else {
    echo "Error deleting database: " . mysqli_error($conn);
}

// Close connection
mysqli_close($conn);
?>