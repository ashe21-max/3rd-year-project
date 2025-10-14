<?php
require_once 'config.php';
requireAdmin();

$error = '';
$success = '';

// Handle recovery actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['verify_recovery'])) {
        $recovery_id = intval($_POST['recovery_id']);
        $user_id = intval($_POST['user_id']);
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'approve') {
            // Mark recovery as approved
            $conn->query("UPDATE recovery_attempts SET status = 'approved', admin_id = {$_SESSION['user_id']} WHERE id = $recovery_id");
            
            // Get user info for the recovery page
            $user_result = $conn->query("SELECT username FROM users WHERE id = $user_id");
            $user = $user_result->fetch_assoc();
            
            $success = "Recovery approved for user: {$user['username']}. They can now set new credentials.";
        } elseif ($action === 'reject') {
            $reason = sanitizeInput($_POST['reject_reason']);
            $conn->query("UPDATE recovery_attempts SET status = 'rejected', admin_id = {$_SESSION['user_id']}, notes = '$reason' WHERE id = $recovery_id");
            $success = "Recovery request rejected.";
        }
    }
    elseif (isset($_POST['restore_user'])) {
        // Restore a deleted user (if backup exists)
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username or email already exists. Please use a different username or email.";
        } else {
            // Create new user with unique username
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $temp_password = password_hash('Temp123!', PASSWORD_DEFAULT);
            $stmt->bind_param("sss", $username, $email, $temp_password);
            
            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                $success = "User '$username' created successfully! Temporary password: Temp123! - User must reset password on first login.";
                
                // Log the restoration
                $conn->query("INSERT INTO admin_logs (admin_id, action) VALUES ({$_SESSION['user_id']}, 'Created new user: $username')");
            } else {
                $error = "Failed to create user. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    $conn->close();
}

// Get pending recovery requests
$conn = getDBConnection();
$recovery_requests = $conn->query("
    SELECT ra.*, u.username, u.email 
    FROM recovery_attempts ra 
    JOIN users u ON ra.user_id = u.id 
    WHERE ra.status = 'pending' 
    ORDER BY ra.created_at DESC
");

// Get user backups
$backups_result = $conn->query("SELECT * FROM user_backups WHERE restored_at IS NULL ORDER BY backed_up_at DESC");

// Get recently deleted users (from logs)
$deleted_users_log = $conn->query("
    SELECT action, created_at 
    FROM admin_logs 
    WHERE action LIKE 'User deleted:%' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery Admin - File Upload System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-user-shield"></i> Account Recovery Admin</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo $_SESSION['username']; ?></strong>!</span>
                    <a href="admin.php" class="admin-btn"><i class="fas fa-tools"></i> Admin Panel</a>
                    <a href="index.php" class="admin-btn"><i class="fas fa-home"></i> File Manager</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="status-message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="status-message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Pending Recovery Requests -->
        <div class="admin-section">
            <h2><i class="fas fa-clock"></i> Pending Recovery Requests</h2>
            
            <?php if ($recovery_requests->num_rows > 0): ?>
                <div class="recovery-requests">
                    <?php while ($request = $recovery_requests->fetch_assoc()): ?>
                        <div class="recovery-request-card">
                            <div class="request-header">
                                <h3>User: <?php echo htmlspecialchars($request['username']); ?></h3>
                                <span class="request-date"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></span>
                            </div>
                            
                            <div class="request-details">
                                <p><strong>Method:</strong> <?php echo htmlspecialchars($request['method']); ?></p>
                                <p><strong>Details:</strong> <?php echo htmlspecialchars($request['details']); ?></p>
                            </div>
                            
                            <form method="POST" class="request-actions">
                                <input type="hidden" name="recovery_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                
                                <button type="submit" name="verify_recovery" value="approve" class="btn success">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                
                                <button type="button" onclick="showRejectForm(<?php echo $request['id']; ?>)" class="btn danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                
                                <div id="reject-form-<?php echo $request['id']; ?>" class="reject-form hidden">
                                    <input type="text" name="reject_reason" placeholder="Reason for rejection" required>
                                    <button type="submit" name="verify_recovery" value="reject" class="btn danger">
                                        Confirm Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No pending recovery requests.</p>
            <?php endif; ?>
        </div>

        <!-- User Backups Restoration -->
        <div class="admin-section">
            <h2><i class="fas fa-archive"></i> Restore from Backups</h2>
            
            <div class="security-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> This restores users from automatic backups created before deletion.
            </div>

            <?php if ($backups_result->num_rows > 0): ?>
                <div class="backups-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Backup ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Files</th>
                                <th>Backup Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($backup = $backups_result->fetch_assoc()): ?>
                                <?php
                                $backup_data = json_decode($backup['user_data'], true);
                                $file_count = $backup_data['file_count'] ?? 0;
                                ?>
                                <tr>
                                    <td>#<?php echo $backup['id']; ?></td>
                                    <td><?php echo htmlspecialchars($backup['username']); ?></td>
                                    <td><?php echo htmlspecialchars($backup['email']); ?></td>
                                    <td><?php echo $file_count; ?> files</td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($backup['backed_up_at'])); ?></td>
                                    <td>
                                        <form method="POST" class="restore-form" onsubmit="return confirm('Restore user <?php echo htmlspecialchars($backup['username']); ?>? This will create a new account.')">
                                            <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                                            <input type="hidden" name="action" value="restore_backup">
                                            <button type="submit" class="restore-btn">
                                                <i class="fas fa-user-plus"></i> Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No user backups available for restoration.</p>
            <?php endif; ?>
        </div>

        <!-- Create New User (Manual Restoration) -->
        <div class="admin-section">
            <h2><i class="fas fa-user-plus"></i> Create New User Account</h2>
            
            <div class="security-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Use this when:</strong> User lost access and no backup exists, or you need to create a completely new account.
            </div>

            <form method="POST" class="admin-form">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <button type="submit" name="restore_user" class="login-btn">
                    <i class="fas fa-user-plus"></i> Create User Account
                </button>
            </form>

            <?php if ($deleted_users_log->num_rows > 0): ?>
                <h3>Recently Deleted Users</h3>
                <div class="deleted-users-log">
                    <?php while ($log = $deleted_users_log->fetch_assoc()): ?>
                        <div class="log-entry">
                            <?php echo htmlspecialchars($log['action']); ?> - 
                            <span class="log-date"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recovery Statistics -->
        <div class="admin-section">
            <h2><i class="fas fa-chart-bar"></i> Recovery Statistics</h2>
            <div class="recovery-stats">
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <h3><?php echo $recovery_requests->num_rows; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-archive"></i>
                    <div class="stat-info">
                        <h3><?php echo $backups_result->num_rows; ?></h3>
                        <p>Available Backups</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showRejectForm(requestId) {
        const form = document.getElementById('reject-form-' + requestId);
        form.classList.toggle('hidden');
    }
    </script>
</body>
</html>