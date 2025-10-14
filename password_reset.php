<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$show_reset_form = false;
$token = isset($_GET['token']) ? sanitizeInput($_GET['token']) : '';

// Handle password reset process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_reset'])) {
        // Step 1: Request password reset
        $email = sanitizeInput($_POST['email']);
        
        $conn = getDBConnection();
        
        // Since emails are encrypted, we need to search differently
        $users_result = mysqli_query($conn, "SELECT id, username, email FROM users");
        $user_found = null;
        
        while ($user = mysqli_fetch_assoc($users_result)) {
            // Decrypt each email to compare
            $decrypted_email = decryptEmail($user['email']);
            if ($decrypted_email === $email) {
                $user_found = $user;
                break;
            }
        }
        
        if ($user_found) {
           // In password_reset.php - around line where token is created:
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hours')); // ← Change this line

// This makes reset links expire in 1 minute - perfect for testing!
            
            // Store token in database
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_found['id'], $token, $expires);
            
            if ($stmt->execute()) {
                // Create reset link
                $reset_link = "http://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?token=$token";
                
                $success = "
                    <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        <h4><i class='fas fa-envelope'></i> Password Reset Link Sent!</h4>
                        <p><strong>To:</strong> $email</p>
                        <p><strong>Reset Link:</strong> <a href='$reset_link' style='word-break: break-all;'>$reset_link</a></p>
                        <p><small><em>This link will expire in 1 hour. In a production system, this would be sent via email.</em></small></p>
                    </div>
                ";
                
                // Log the reset request
                mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action) VALUES (0, 'Password reset requested for user: {$user_found['username']}')");
            } else {
                $error = "Error generating reset token. Please try again.";
            }
            $stmt->close();
        } else {
            $error = "No account found with that email address.";
        }
        $conn->close();
    }
    elseif (isset($_POST['reset_password'])) {
        // Step 2: Reset the password
        $token = sanitizeInput($_POST['token']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            $password_check = isPasswordStrong($password);
            if ($password_check !== true) {
                $error = $password_check;
            } else {
                $conn = getDBConnection();
                
                // Verify token is valid and not expired
                $stmt = $conn->prepare("SELECT pr.user_id, u.username, u.email FROM password_resets pr 
                                       JOIN users u ON pr.user_id = u.id 
                                       WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $data = $result->fetch_assoc();
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt2->bind_param("si", $hashed_password, $data['user_id']);
                    
                    if ($stmt2->execute()) {
                        // Mark token as used
                        $stmt3 = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                        $stmt3->bind_param("s", $token);
                        $stmt3->execute();
                        $stmt3->close();
                        
                        // Decrypt email for display
                        $user_email = decryptEmail($data['email']);
                        
                        $success = "
                            <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                                <h4><i class='fas fa-check-circle'></i> Password Reset Successful!</h4>
                                <p>Your password has been successfully reset for account: <strong>{$data['username']}</strong></p>
                                <p>You can now <a href='login.php' style='color: #007bff; font-weight: bold;'>login with your new password</a>.</p>
                            </div>
                        ";
                        
                        // Log the password reset
                        mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action) VALUES (0, 'Password reset completed for user: {$data['username']}')");
                    } else {
                        $error = "Error updating password. Please try again.";
                    }
                    $stmt2->close();
                } 
                else {
                    $error = "Invalid or expired reset token. Please request a new reset link.";
                }
                $stmt->close();
                $conn->close();
            }
        }
    }
}

// Check if token is provided in URL (user clicked reset link)
if (!empty($token)) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT pr.user_id, u.username, u.email FROM password_resets pr 
                           JOIN users u ON pr.user_id = u.id 
                           WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $show_reset_form = true;
        $user_data = $result->fetch_assoc();
        $user_email = decryptEmail($user_data['email']);
    } 
    // else {
    //     $error = "Invalid or expired reset token.";
    // }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - File Upload System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .reset-header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .status-message.error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        .status-message.success {
            background: #e8f5e8;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .reset-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.95em;
        }
        
        .form-group input {
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-top: 5px;
            font-size: 0.85em;
            color: #666;
        }
        
        .reset-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .login-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .login-links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .security-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9em;
            color: #1565c0;
        }
        
        .user-info {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator:before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            border: 3px solid white;
        }
        
        .step-number.active {
            background: #667eea;
            color: white;
        }
        
        .step-number.completed {
            background: #4caf50;
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            color: #666;
        }
        
        .step-label.active {
            color: #667eea;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .reset-container {
                padding: 30px 20px;
            }
            
            .login-links {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1><i class="fas fa-key"></i> Password Reset</h1>
            <p>Recover access to your account</p>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step">
                <div class="step-number <?php echo $show_reset_form ? 'completed' : 'active'; ?>">1</div>
                <div class="step-label <?php echo !$show_reset_form ? 'active' : ''; ?>">Enter Email</div>
            </div>
            <div class="step">
                <div class="step-number <?php echo $show_reset_form ? 'active' : ''; ?>">2</div>
                <div class="step-label <?php echo $show_reset_form ? 'active' : ''; ?>">New Password</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="status-message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="status-message success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_reset_form): ?>
            <!-- Step 2: Reset Password Form -->
            <div class="user-info">
                <h4><i class="fas fa-user"></i> Account Verification</h4>
                <p>Resetting password for: <strong><?php echo $user_data['username']; ?></strong></p>
                <p>Email: <strong><?php echo $user_email; ?></strong></p>
            </div>

            <form method="POST" class="reset-form">
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> New Password:</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your new password" onkeyup="validatePassword()">
                    
                    <div class="password-requirements">
                        <strong>Password must contain:</strong>
                        <ul>
                            <li id="length">At least 8 characters</li>
                            <li id="uppercase">One uppercase letter</li>
                            <li id="lowercase">One lowercase letter</li>
                            <li id="number">One number</li>
                            <li id="special">One special character</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm your new password" onkeyup="validatePasswordMatch()">
                    <div id="password-match" style="margin-top: 5px; font-size: 0.9em;"></div>
                </div>
                
                <button type="submit" name="reset_password" class="reset-btn" id="submit-btn">
                    <i class="fas fa-sync-alt"></i> Reset Password
                </button>
            </form>
            
        <?php else: ?>
            <!-- Step 1: Request Reset Form -->
            <form method="POST" class="reset-form">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="Enter your account email address">
                </div>
                
                <button type="submit" name="request_reset" class="reset-btn">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            
            <div class="security-info">
                <h4><i class="fas fa-info-circle"></i> How it works:</h4>
                <ol style="margin-left: 20px; margin-top: 10px;">
                    <li>Enter your email address</li>
                    <li>Check for the reset link (displayed on this page for demo)</li>
                    <li>Click the link to set a new password</li>
                    <li>Login with your new credentials</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <div class="login-links">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            <a href="register.php">Create New Account</a>
            <a href="recovery.php">Account Recovery Help</a>
        </div>
    </div>

    <script>
    function validatePassword() {
        const password = document.getElementById('password').value;
        
        // Length check
        document.getElementById('length').className = password.length >= 8 ? 'valid' : 'invalid';
        
        // Uppercase check
        document.getElementById('uppercase').className = /[A-Z]/.test(password) ? 'valid' : 'invalid';
        
        // Lowercase check
        document.getElementById('lowercase').className = /[a-z]/.test(password) ? 'valid' : 'invalid';
        
        // Number check
        document.getElementById('number').className = /[0-9]/.test(password) ? 'valid' : 'invalid';
        
        // Special character check
        document.getElementById('special').className = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password) ? 'valid' : 'invalid';
        
        validatePasswordMatch();
    }
    
    function validatePasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const matchElement = document.getElementById('password-match');
        const submitBtn = document.getElementById('submit-btn');
        
        if (!submitBtn) return;
        
        if (confirmPassword === '') {
            matchElement.innerHTML = '';
            matchElement.style.color = '';
        } else if (password === confirmPassword) {
            matchElement.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            matchElement.style.color = '#28a745';
            submitBtn.disabled = false;
        } else {
            matchElement.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
            matchElement.style.color = '#dc3545';
            submitBtn.disabled = true;
        }
    }
    
    // Add CSS for validation states
    const style = document.createElement('style');
    style.textContent = `
        .valid { color: #28a745; }
        .invalid { color: #dc3545; }
        .valid:before { content: "✓ "; }
        .invalid:before { content: "✗ "; }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>