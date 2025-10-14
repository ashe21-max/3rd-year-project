<?php
// setup.php - With encryption support
session_start();

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'file_uploads';

// Create connection
$conn = mysqli_connect($host, $user, $pass);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Simple encryption function for setup
function simpleEncrypt($data, $key = '0123456789abcdef0123456789abcdef') {
    if (empty($data)) return $data;
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if (mysqli_query($conn, $sql)) {
    echo "<div class='status success'>✅ Database created successfully</div>";
} else {
    echo "<div class='status error'>❌ Database creation failed: " . mysqli_error($conn) . "</div>";
}

// Select database
mysqli_select_db($conn, $dbname);

// Create users table with encrypted email support
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email TEXT NOT NULL,  -- Changed to TEXT for encrypted emails
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)";

if (mysqli_query($conn, $sql)) {
    echo "<div class='status success'>✅ Users table created</div>";
} else {
    echo "<div class='status error'>❌ Users table failed: " . mysqli_error($conn) . "</div>";
}

// Create other tables (same as before)
$tables = [
    'files' => "CREATE TABLE IF NOT EXISTS files (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        name VARCHAR(255),
        original_name VARCHAR(255),
        size INT(11),
        category VARCHAR(50) DEFAULT 'other',
        tags TEXT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        downloads INT(11) DEFAULT 0
    )",
    
    'password_resets' => "CREATE TABLE IF NOT EXISTS password_resets (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'admin_logs' => "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        admin_id INT(11) NOT NULL,
        action TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'recovery_attempts' => "CREATE TABLE IF NOT EXISTS recovery_attempts (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        method VARCHAR(50) NOT NULL,
        details TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_id INT(11) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'user_backups' => "CREATE TABLE IF NOT EXISTS user_backups (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        user_data JSON NOT NULL,
        backed_up_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        restored_at TIMESTAMP NULL
    )"
];

foreach ($tables as $tableName => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "<div class='status success'>✅ $tableName table created</div>";
    } else {
        echo "<div class='status error'>❌ $tableName table failed: " . mysqli_error($conn) . "</div>";
    }
}

// Create default users with ENCRYPTED emails
$defaultUsers = [
    'admin' => [
        'email' => 'admin@example.com', 
        'password' => 'admin123',
        'role' => 'admin'
    ],
    // 'user' => [
    //     'email' => 'user@example.com', 
    //     'password' => 'user123',
    //     'role' => 'user'
    // ]
];

foreach ($defaultUsers as $username => $userData) {
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    
    if (mysqli_num_rows($check) == 0) {
        // Encrypt the email before storing
        $encrypted_email = simpleEncrypt($userData['email']);
        $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, role) VALUES (
            '$username', 
            '$encrypted_email', 
            '$password_hash', 
            '{$userData['role']}'
        )";
        
        if (mysqli_query($conn, $sql)) {
            echo "<div class='status success'>✅ User '$username' created with encrypted email</div>";
        } else {
            echo "<div class='status error'>❌ Error creating user '$username': " . mysqli_error($conn) . "</div>";
        }
    } else {
        echo "<div class='status info'>ℹ️ User '$username' already exists</div>";
    }
}

// Create uploads directory
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
    echo "<div class='status success'>✅ Uploads directory created</div>";
}

echo "<div class='credentials'>";
echo "<h3>🔐 Login Credentials (Emails are Encrypted in Database)</h3>";
echo "<p><strong>Admin Account:</strong> username=admin, password=admin123</p>";
echo "<p><strong>User Account:</strong> username=user, password=user123</p>";
echo "<p>✅ Emails are stored encrypted in the database</p>";
echo "<p>✅ Passwords are securely hashed</p>";
echo "</div>";

mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Complete</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .status { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .success { background: #d4ffd4; border-left: 4px solid #00cc00; }
        .error { background: #ffd4d4; border-left: 4px solid #ff0000; }
        .info { background: #d4eaff; border-left: 4px solid #0066cc; }
        .credentials { background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Setup Complete</h1>
    <a href="login.php">Go to Login</a>
</body>
</html>