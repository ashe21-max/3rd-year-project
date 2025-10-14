<?php
require_once 'config.php';
requireAdmin();

$error = '';
$success = '';

// Handle admin password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $password_check = isPasswordStrong($new_password);
        if ($password_check !== true) {
            $error = $password_check;
        } else {
            if (adminResetUserPassword($user_id, $new_password)) {
                // Get username for logging
                $conn = getDBConnection();
                $user_result = $conn->query("SELECT username FROM users WHERE id = $user_id");
                $user = $user_result->fetch_assoc();
                
                // Log this action
                $admin_id = $_SESSION['user_id'];
                $action = "Password reset for user: " . $user['username'];
                $conn->query("INSERT INTO admin_logs (admin_id, action) VALUES ($admin_id, '$action')");
                $conn->close();
                
                $success = "Password reset successfully for user: " . $user['username'];
            } else {
                $error = "Failed to reset password.";
            }
        }
    }
}

// Get all users
$conn = getDBConnection();
$users_result = mysqli_query($conn, "SELECT id, username, email, role FROM users ORDER BY username");
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset - File Upload System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .admin-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .admin-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        select, input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .status-message {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .login-btn.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }
        
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-user-shield"></i> Admin Password Reset</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo $_SESSION['username']; ?></strong>!</span>
                    <a href="admin.php" class="admin-btn" style="color: white; text-decoration: none; margin-left: 15px;">
                        <i class="fas fa-tools"></i> Admin Panel
                    </a>
                    <a href="index.php" class="admin-btn" style="color: white; text-decoration: none; margin-left: 15px;">
                        <i class="fas fa-home"></i> File Manager
                    </a>
                </div>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="status-message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="status-message success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="admin-section">
            <h2><i class="fas fa-key"></i> Reset User Password</h2>
            
            <div class="security-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Security Notice:</strong> Use this tool carefully. Only reset passwords when absolutely necessary.
                Users will need to be notified of their new password. All actions are logged.
            </div>

            <form method="POST" class="admin-form">
                <div class="form-group">
                    <label for="user_id">Select User:</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?> 
                                (<?php echo $user['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="text" id="new_password" name="new_password" required 
                           value="<?php echo generateSecurePassword(12); ?>">
                    <div class="password-requirements">
                        <small>Automatically generated secure password. You can change it.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="text" id="confirm_password" name="confirm_password" required 
                           value="<?php echo generateSecurePassword(12); ?>">
                </div>

                <button type="submit" class="login-btn danger">
                    <i class="fas fa-exclamation-triangle"></i> Reset Password
                </button>
            </form>
        </div>
    </div>

    <script>
    // Auto-generate password when user is selected
    document.getElementById('user_id').addEventListener('change', function() {
        const password = generatePassword();
        document.getElementById('new_password').value = password;
        document.getElementById('confirm_password').value = password;
    });
    
    function generatePassword() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return password;
    }
    </script>
</body>
</html>