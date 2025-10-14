<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'file_uploads');

// File upload configuration
define('MAX_FILE_SIZE', 40000000); // 38MB
define('UPLOAD_DIR', 'uploads/');
define('ALLOWED_EXTENSIONS', [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'],
    'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx', 'csv'],
    'media' => ['mp3', 'wav', 'ogg', 'mp4', 'avi', 'mov', 'mkv', 'webm', 'flv'],
    'archive' => ['zip', 'rar', '7z', 'tar', 'gz']
]);

// Password strength configuration
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SYMBOLS', true);

// Encryption configuration - FIXED IV LENGTH
define('ENCRYPTION_KEY', '0123456789abcdef0123456789abcdef'); // 32-character key
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('ENCRYPTION_IV_LENGTH', 16); // Must be 16 for AES-256-CBC

// FIXED Encryption functions
function encryptData($data) {
    if (empty($data)) return $data;
    
    $key = ENCRYPTION_KEY;
    $method = ENCRYPTION_METHOD;
    $iv_length = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptData($data) {
    if (empty($data)) return $data;
    
    $key = ENCRYPTION_KEY;
    $method = ENCRYPTION_METHOD;
    $iv_length = openssl_cipher_iv_length($method);
    
    $data = base64_decode($data);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    return openssl_decrypt($encrypted, $method, $key, 0, $iv);
}

// Email encryption/decryption
function encryptEmail($email) {
    return encryptData($email);
}

function decryptEmail($encrypted_email) {
    return decryptData($encrypted_email);
}

// Create database connection with error handling
function getDBConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}

// Check password strength
function isPasswordStrong($password) {
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        return "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long";
    }
    
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter";
    }
    
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter";
    }
    
    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number";
    }
    
    if (PASSWORD_REQUIRE_SYMBOLS && !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        return "Password must contain at least one special character";
    }
    
    return true;
}

// Generate secure password
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['error'] = "Access denied. Administrator privileges required.";
        header('Location: index.php');
        exit;
    }
}

// Check if user can modify file (owner or admin)
function canModifyFile($file_user_id) {
    return isAdmin() || (isLoggedIn() && $_SESSION['user_id'] == $file_user_id);
}

// Check if user can view file (owner or admin)
function canViewFile($file_user_id) {
    return isAdmin() || (isLoggedIn() && $_SESSION['user_id'] == $file_user_id);
}

// Get user's files or all files if admin
function getUserFiles($conn, $user_id, $is_admin = false, $search_term = '') {
    if ($is_admin) {
        if (!empty($search_term)) {
            $sql = "SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id 
                    WHERE f.original_name LIKE ? OR f.tags LIKE ?
                    ORDER BY f.upload_date DESC";
            $search_term = "%$search_term%";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $search_term, $search_term);
        } else {
            $sql = "SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id 
                    ORDER BY f.upload_date DESC";
            $stmt = $conn->prepare($sql);
        }
    } else {
        if (!empty($search_term)) {
            $sql = "SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id 
                    WHERE f.user_id = ? AND (f.original_name LIKE ? OR f.tags LIKE ?)
                    ORDER BY f.upload_date DESC";
            $search_term = "%$search_term%";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $search_term, $search_term);
        } else {
            $sql = "SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id 
                    WHERE f.user_id = ? ORDER BY f.upload_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $files = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $files;
}

// Extract tags from filename
function extractTagsFromFilename($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $tags = [];
    
    // Split by common separators
    $words = preg_split('/[\s_\-\.]+/', $name);
    
    foreach ($words as $word) {
        // Keep words that are 3+ characters and not just numbers
        if (strlen($word) >= 3 && !is_numeric($word) && preg_match('/[a-zA-Z]/', $word)) {
            $tags[] = strtolower($word);
        }
    }
    
    return array_unique($tags);
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 B";
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

// Get file icon
function getFileIcon($extension) {
    $iconMap = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'txt' => 'fa-file-alt', 'rtf' => 'fa-file-alt',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'csv' => 'fa-file-csv',
        'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
        'zip' => 'fa-file-archive', 'rar' => 'fa-file-archive', '7z' => 'fa-file-archive', 
        'tar' => 'fa-file-archive', 'gz' => 'fa-file-archive',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image', 
        'gif' => 'fa-file-image', 'svg' => 'fa-file-image', 'webp' => 'fa-file-image', 'bmp' => 'fa-file-image',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'ogg' => 'fa-file-audio',
        'mp4' => 'fa-file-video', 'mov' => 'fa-file-video', 'avi' => 'fa-file-video', 
        'mkv' => 'fa-file-video', 'webm' => 'fa-file-video', 'flv' => 'fa-file-video'
    ];
    return isset($iconMap[$extension]) ? $iconMap[$extension] : 'fa-file';
}

// Get category from extension
function getFileCategory($extension) {
    $extension = strtolower($extension);
    foreach (ALLOWED_EXTENSIONS as $category => $extensions) {
        if (in_array($extension, $extensions)) {
            return $category;
        }
    }
    return 'other';
}

// Admin function to reset user password
function adminResetUserPassword($user_id, $new_password) {
    $conn = getDBConnection();
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

// Function to create user backup before deletion
function createUserBackup($user_id) {
    $conn = getDBConnection();
    
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return false;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Get user's file count and list
    $files_result = $conn->query("SELECT COUNT(*) as count FROM files WHERE user_id = $user_id");
    $file_count_data = $files_result->fetch_assoc();
    $file_count = $file_count_data['count'] ?? 0;
    
    $files_list = [];
    if ($file_count > 0) {
        $files_data = $conn->query("SELECT original_name, size, category, upload_date FROM files WHERE user_id = $user_id");
        $files_list = $files_data->fetch_all(MYSQLI_ASSOC);
    }
    
    // Prepare backup data
    $backup_data = [
        'user' => $user,
        'file_count' => $file_count,
        'files' => $files_list,
        'backup_time' => date('Y-m-d H:i:s'),
        'backup_reason' => 'pre_deletion'
    ];
    
    // Insert backup
    $stmt = $conn->prepare("INSERT INTO user_backups (user_id, username, email, user_data) VALUES (?, ?, ?, ?)");
    $json_data = json_encode($backup_data);
    $stmt->bind_param("isss", $user_id, $user['username'], $user['email'], $json_data);
    $result = $stmt->execute();
    $backup_id = $stmt->insert_id;
    
    $stmt->close();
    $conn->close();
    
    return $backup_id;
}

// Function to restore user from backup
function restoreUserFromBackup($backup_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM user_backups WHERE id = ?");
    $stmt->bind_param("i", $backup_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return false;
    }
    
    $backup = $result->fetch_assoc();
    $user_data = json_decode($backup['user_data'], true);
    
    if (!$user_data || !isset($user_data['user'])) {
        $stmt->close();
        $conn->close();
        return false;
    }
    
    $user_info = $user_data['user'];
    
    // Check if username already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $user_info['username']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    $original_username = $user_info['username'];
    if ($check_result->num_rows > 0) {
        // Username exists, modify it
        $counter = 1;
        do {
            $new_username = $original_username . '_restored_' . $counter;
            $counter++;
            $check_stmt->bind_param("s", $new_username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
        } while ($check_result->num_rows > 0 && $counter < 10);
        
        $user_info['username'] = $new_username;
    }
    $check_stmt->close();
    
    // Create new user account with encrypted email
    $temp_password = password_hash('TempRestore123!', PASSWORD_DEFAULT);
    $encrypted_email = encryptEmail($user_info['email']);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user_info['username'], $encrypted_email, $temp_password, $user_info['role']);
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        return $new_user_id;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

// Check and create missing tables
function checkAndCreateMissingTables() {
    $conn = getDBConnection();
    
    // Check if password_resets table exists
    $result = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE password_resets (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }
    
    // Check if admin_logs table exists
    $result = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE admin_logs (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            admin_id INT(11) NOT NULL,
            action TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }
    
    // Check if recovery_attempts table exists
    $result = $conn->query("SHOW TABLES LIKE 'recovery_attempts'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE recovery_attempts (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            method VARCHAR(50) NOT NULL,
            details TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            admin_id INT(11) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }
    
    // Check if user_backups table exists
    $result = $conn->query("SHOW TABLES LIKE 'user_backups'");
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE user_backups (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            user_data JSON NOT NULL,
            backed_up_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            restored_at TIMESTAMP NULL
        )";
        $conn->query($sql);
    }
    
    // Check if tags column exists in files table
    $result = $conn->query("SHOW COLUMNS FROM files LIKE 'tags'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE files ADD COLUMN tags TEXT NULL AFTER category");
    }
    
    $conn->close();
}

// Function to clean up expired password reset tokens
function cleanupExpiredTokens() {
    $conn = getDBConnection();
    $conn->query("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
    $conn->close();
}

// Run table check and cleanup on config load
checkAndCreateMissingTables();
cleanupExpiredTokens();

// Migration function to fix existing encrypted data
function migrateEncryptedData() {
    $conn = getDBConnection();
    
    // Check if we have old encrypted data (12-byte IV issue)
    $result = $conn->query("SELECT id, email FROM users WHERE LENGTH(email) > 0");
    
    while ($user = $result->fetch_assoc()) {
        $email_data = base64_decode($user['email']);
        
        // If IV is 12 bytes (old format), we need to migrate
        if (strlen($email_data) > 0) {
            $iv_length = 16; // Correct IV length for AES-256-CBC
            $old_iv_length = 12; // Problematic old IV length
            
            // Check if this might be old format data
            if (strlen($email_data) > $old_iv_length) {
                try {
                    // Try to decrypt with correct IV length first
                    $iv = substr($email_data, 0, $iv_length);
                    $encrypted = substr($email_data, $iv_length);
                    
                    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
                    
                    if ($decrypted === false) {
                        // If that fails, try with old IV length (for migration)
                        $iv = substr($email_data, 0, $old_iv_length);
                        $encrypted = substr($email_data, $old_iv_length);
                        
                        $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
                        
                        if ($decrypted !== false) {
                            // Re-encrypt with correct IV length
                            $correctly_encrypted = encryptEmail($decrypted);
                            
                            // Update the database
                            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                            $stmt->bind_param("si", $correctly_encrypted, $user['id']);
                            $stmt->execute();
                            $stmt->close();
                            
                            error_log("Migrated encrypted email for user ID: " . $user['id']);
                        }
                    }
                } catch (Exception $e) {
                    // Skip if there's an error with this record
                    error_log("Error migrating user ID " . $user['id'] . ": " . $e->getMessage());
                }
            }
        }
    }
    
    $conn->close();
}

// Run migration once (you can remove this after it runs successfully)
// migrateEncryptedData();
?>