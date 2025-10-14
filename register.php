<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else {
        $password_check = isPasswordStrong($password);
        if ($password_check !== true) {
            $error = $password_check;
        } else {
            $conn = getDBConnection();
            
            // Check if username already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Username already exists! Please choose a different username.";
            } else {
                // Check if email already exists (by comparing encrypted emails)
                $encrypted_input_email = encryptEmail($email);
                $email_check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $email_check_stmt->bind_param("s", $encrypted_input_email);
                $email_check_stmt->execute();
                $email_check_result = $email_check_stmt->get_result();
                
                if ($email_check_result->num_rows > 0) {
                    $error = "Email address already registered!";
                    $email_check_stmt->close();
                } else {
                    // Encrypt email before storing
                    $encrypted_email = encryptEmail($email);
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    $insert_stmt->bind_param("sss", $username, $encrypted_email, $hashed_password);
                    
                    if ($insert_stmt->execute()) {
                        $success = "Registration successful! You can now <a href='login.php' style='color: #007bff;'>login to your account</a>.";
                        
                        // Clear form fields
                        $username = '';
                        $email = '';
                    } else {
                        $error = "Registration failed! Please try again. Error: " . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                }
                $email_check_stmt->close();
            }
            $check_stmt->close();
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - File Upload System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {

    background: url('https://images.unsplash.com/photo-1556761175-4b46a572b786') no-repeat center/cover;
    background-color: rgba(18, 38, 214, 0.5); /* Semi-transparent black */
    background-blend-mode: darken;
    background-repeat: no-repeat;
           font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #333;
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .register-header p {
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
        
        .register-form {
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
        
        .password-requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .password-requirements li {
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-requirements li.valid {
            color: #28a745;
        }
        
        .password-requirements li.invalid {
            color: #dc3545;
        }
        
        .register-btn {
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
        
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .register-btn:active {
            transform: translateY(0);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .security-features {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9em;
            color: #1565c0;
        }
        
        .security-features h4 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .security-features ul {
            list-style: none;
            padding-left: 0;
        }
        
        .security-features li {
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .register-header h1 {
                font-size: 1.8em;
            }
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> Create Account</h1>
            <p>Join our secure file upload system</p>
        </div>
        
        <?php if ($error): ?>
            <div class="status-message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="status-message success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="register-form">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username:</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo htmlspecialchars($username); ?>"
                       placeholder="Choose a unique username (min. 3 characters)">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address:</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($email); ?>"
                       placeholder="Enter your email address">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password:</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required 
                           placeholder="Create a strong password"
                           onkeyup="validatePassword()">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div class="password-requirements">
                    <!-- <strong>Password must contain:</strong>
                    <ul>
                        <li id="length" class="invalid">At least 8 characters</li>
                        <li id="uppercase" class="invalid">One uppercase letter</li>
                        <li id="lowercase" class="invalid">One lowercase letter</li>
                        <li id="number" class="invalid">One number</li>
                        <li id="special" class="invalid">One special character</li>
                    </ul> -->
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password:</label>
                <div class="password-toggle">
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Re-enter your password"
                           onkeyup="validatePasswordMatch()">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="password-match" style="margin-top: 5px; font-size: 0.9em;"></div>
            </div>
            
            <button type="submit" class="register-btn" id="submit-btn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign in here</a>
        </div>
        
        <div class="security-features">
            <h4><i class="fas fa-shield-alt"></i> Security Features</h4>
            <ul>
                <li><i class="fas fa-check-circle"></i> Email addresses are encrypted for privacy</li>
                <li><i class="fas fa-check-circle"></i> Passwords are securely hashed</li>
                <li><i class="fas fa-check-circle"></i> Strong password requirements enforced</li>
                <li><i class="fas fa-check-circle"></i> Secure database storage</li>
            </ul>
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
    
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.parentNode.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            field.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // Initial validation
    document.addEventListener('DOMContentLoaded', function() {
        validatePassword();
    });
    </script>
</body>
</html>