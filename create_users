<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "file_uploads");

// Create default admin user
$check = mysqli_query($conn, "SELECT * FROM users WHERE username='admin'");
if (mysqli_num_rows($check) == 0) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (username, password, email) VALUES ('admin', '$password', 'admin@example.com')");
    echo "Default user created: admin / admin123";
} else {
    echo "User already exists";
}
?>