<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'file_uploads';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create files table if it doesn't exist with the correct schema
$createTable = "
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    size INT NOT NULL,
    downloads INT DEFAULT 0,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $createTable)) {
    // Table created successfully or already exists
} else {
    die("Error creating table: " . mysqli_error($conn));
}
?>