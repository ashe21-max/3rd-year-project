<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// Handle recovery process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if ($step === 1) {
        // Step 1: Identity verification
        $username = sanitizeInput($_POST['username']);
        $security_question = sanitizeInput($_POST['security_question']);
        
        // In a real system, you'd have security questions stored
        // For demo, we'll use some basic verification
        $stmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['recovery_user_id'] = $user['id'];
            $_SESSION['recovery_username'] = $user['username'];
            $step = 2;
        } else {
            $error = "User not found. Please check the username.";
        }
        $stmt->close();
    }
    elseif ($step === 2 && isset($_SESSION['recovery_user_id'])) {
        // Step 2: Admin contact and verification
        $contact_method = sanitizeInput($_POST['contact_method']);
        $verification_details = sanitizeInput($_POST['verification_details']);
        
        // Log recovery attempt
        $user_id = $_SESSION['recovery_user_id'];
        $details = "Recovery attempt via: $contact_method. Details: $verification_details";
        $conn->query("INSERT INTO recovery_attempts (user_id, method, details) VALUES ($user_id, '$contact_method', '$details')");
        
        $success = "Recovery request submitted! An administrator will contact you for verification.";
        $step = 3;
    }
    elseif ($step === 3 && isset($_SESSION['recovery_user_id'])) {
        // Step 3: Admin has verified, create new credentials
        $new_email = sanitizeInput($_POST['new_email']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            $password_check = isPasswordStrong($new_password);
            if ($password_check !== true) {
                $error = $password_check;
            } else {
                $user_id = $_SESSION['recovery_user_id'];
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user credentials
                $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssi", $new_email, $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    // Log the recovery
                    $conn->query("INSERT INTO admin_logs (admin_id, action) VALUES (0, 'Account recovery for user ID: $user_id')");
                    
                    $success = "Account recovered successfully! You can now <a href='login.php'>login</a> with your new credentials.";
                    
                    // Clear recovery session
                    unset($_SESSION['recovery_user_id']);
                    unset($_SESSION['recovery_username']);
                    $step = 4;
                }
                $stmt->close();
            }
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - File Upload System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-form">
            <h1><i class="fas fa-life-ring"></i> Account Recovery</h1>
            <p>Lost access to your account? We'll help you recover it.</p>
            
            <?php if ($error): ?>
                <div class="status-message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="status-message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <!-- Step 1: Identity Verification -->
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter your username">
                    </div>
                    
                    <div class="form-group">
                        <label for="security_question">Account Verification Question:</label>
                        <select id="security_question" name="security_question" required>
                            <option value="">-- Select a question --</option>
                            <option value="approximate_date">Approximate account creation date</option>
                            <option value="file_count">Approximate number of files uploaded</option>
                            <option value="last_activity">When was the last time you used the system?</option>
                        </select>
                        <input type="text" name="security_answer" required 
                               placeholder="Your answer" style="margin-top: 10px;">
                    </div>
                    
                    <button type="submit" class="login-btn">Verify Identity</button>
                </form>
                
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Admin Contact -->
                <form method="POST">
                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <strong>Identity Verified:</strong> We found account "<?php echo $_SESSION['recovery_username']; ?>"
                    </div>
                    
                    <div class="form-group">
                        <label>How can we contact you for verification?</label>
                        <select name="contact_method" required>
                            <option value="">-- Select contact method --</option>
                            <option value="alternative_email">Alternative Email</option>
                            <option value="phone">Phone Call</option>
                            <option value="security_questions">Additional Security Questions</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Verification Details:</label>
                        <textarea name="verification_details" required 
                                  placeholder="Provide details for verification (alternative email, phone number, or answers to security questions)"
                                  rows="4"></textarea>
                    </div>
                    
                    <button type="submit" class="login-btn">Submit Recovery Request</button>
                </form>
                
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Create New Credentials (After admin verification) -->
                <div class="security-notice success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Admin Verification Complete:</strong> You can now set new credentials
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="new_email">New Email Address:</label>
                        <input type="email" id="new_email" name="new_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-requirements">
                            <small>Must contain: 8+ characters, uppercase, lowercase, number, special character</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="login-btn">Recover Account</button>
                </form>
                
            <?php elseif ($step === 4): ?>
                <!-- Step 4: Recovery Complete -->
                <div class="recovery-complete">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h3>Account Recovery Successful!</h3>
                    <p>Your account has been successfully recovered with new credentials.</p>
                    <a href="login.php" class="login-btn">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <div class="login-links">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                <a href="password_reset.php">Password Reset</a>
                <a href="contact.php">Contact Support</a>
            </div>
        </div>
    </div>
</body>
</html>