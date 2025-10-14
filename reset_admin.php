<?php
// reset_admin.php - Emergency admin password reset
session_start();
$conn = mysqli_connect("localhost", "root", "", "file_uploads");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password === $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update admin password
        $result = mysqli_query($conn, "UPDATE users SET password = '$hashed_password' WHERE username = 'admin'");
        
        if ($result) {
            $success = "Admin password reset successfully!";
        } else {
            $error = "Error resetting password: " . mysqli_error($conn);
        }
    } else {
        $error = "Passwords do not match!";
    }
}

// Check if admin exists
$admin_check = mysqli_query($conn, "SELECT * FROM users WHERE username = 'admin'");
$admin_exists = mysqli_num_rows($admin_check) > 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Admin Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 100px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h2>Reset Admin Password</h2>
    
    <?php if (!$admin_exists): ?>
        <div class="error">
            <strong>Admin user not found!</strong> Please run setup.php first.
        </div>
        <p><a href="setup.php">Run Setup</a></p>
    <?php else: ?>
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
            <p><a href="login.php">Go to Login</a></p>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>New Password:</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password:</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit">Reset Admin Password</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    
    <hr>
    <h3>Debug Information:</h3>
    <?php
    echo "<p>Database connection: " . ($conn ? "Success" : "Failed") . "</p>";
    if ($admin_exists) {
        $admin = mysqli_fetch_assoc($admin_check);
        echo "<p>Admin user exists: Yes</p>";
        echo "<p>Admin username: " . $admin['username'] . "</p>";
        echo "<p>Admin role: " . $admin['role'] . "</p>";
    } else {
        echo "<p>Admin user exists: No</p>";
    }
    ?>
</body>
</html>