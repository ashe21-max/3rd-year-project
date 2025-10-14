<?php
require_once 'config.php';
requireAdmin();

// Handle user management actions
if (isset($_POST['action'])) {
    $conn = getDBConnection();
    
    if ($_POST['action'] == 'delete_user' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        
        // Prevent admin from deleting themselves
        if ($user_id != $_SESSION['user_id']) {
            // Create backup before deletion
            $backup_id = createUserBackup($user_id);
            
            if ($backup_id) {
                // Log backup creation
                $conn->query("INSERT INTO admin_logs (admin_id, action) VALUES ({$_SESSION['user_id']}, 'Created backup #$backup_id before deleting user ID: $user_id')");
                
                // Now delete the user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User deleted successfully! Backup ID: #$backup_id";
                    
                    // Log the deletion
                    $conn->query("INSERT INTO admin_logs (admin_id, action) VALUES ({$_SESSION['user_id']}, 'Deleted user ID: $user_id. Backup: #$backup_id')");
                } else {
                    $_SESSION['error'] = "Failed to delete user.";
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Failed to create user backup. Deletion cancelled.";
            }
        } else {
            $_SESSION['error'] = "You cannot delete your own account.";
        }
    }
    elseif ($_POST['action'] == 'change_role' && isset($_POST['user_id']) && isset($_POST['role'])) {
        $user_id = intval($_POST['user_id']);
        $role = $_POST['role'];
        
        // Prevent admin from changing their own role
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $role, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "User role updated successfully!";
            }
            $stmt->close();
        }
    }
    elseif ($_POST['action'] == 'restore_user' && isset($_POST['backup_id'])) {
        $backup_id = intval($_POST['backup_id']);
        
        $new_user_id = restoreUserFromBackup($backup_id);
        if ($new_user_id) {
            $_SESSION['success'] = "User restored successfully! New user ID: $new_user_id. Temporary password: TempRestore123!";
            $conn->query("INSERT INTO admin_logs (admin_id, action) VALUES ({$_SESSION['user_id']}, 'Restored user from backup #$backup_id to new ID: $new_user_id')");
        } else {
            $_SESSION['error'] = "Failed to restore user from backup.";
        }
    }
    $conn->close();
}

// Get all users and statistics
$conn = getDBConnection();
$users_result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
$files_result = mysqli_query($conn, "SELECT COUNT(*) as total_files, SUM(size) as total_size FROM files");
$files_stats = mysqli_fetch_assoc($files_result);

// Get user backups
$backups_result = mysqli_query($conn, "SELECT * FROM user_backups ORDER BY backed_up_at DESC");

$users = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    // Get file count for each user
    $stmt = $conn->prepare("SELECT COUNT(*) as file_count FROM files WHERE user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $file_result = $stmt->get_result();
    $file_count = $file_result->fetch_assoc()['file_count'];
    $user['file_count'] = $file_count;
    $users[] = $user;
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - File Upload System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-tools"></i> Admin Panel</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo $_SESSION['username']; ?></strong>!</span>
                    <span class="admin-badge"><i class="fas fa-crown"></i> Administrator</span>
                    <a href="index.php" class="admin-btn"><i class="fas fa-home"></i> Back to Files</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="status-message success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="status-message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="admin-statistics">
            <div class="stat-card admin-stat">
                <i class="fas fa-users"></i>
                <div class="stat-info">
                    <h3><?php echo count($users); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="stat-card admin-stat">
                <i class="fas fa-file"></i>
                <div class="stat-info">
                    <h3><?php echo $files_stats['total_files']; ?></h3>
                    <p>Total Files</p>
                </div>
            </div>
            <div class="stat-card admin-stat">
                <i class="fas fa-database"></i>
                <div class="stat-info">
                    <h3><?php echo formatFileSize($files_stats['total_size']); ?></h3>
                    <p>Total Storage Used</p>
                </div>
            </div>
            <div class="stat-card admin-stat">
                <i class="fas fa-archive"></i>
                <div class="stat-info">
                    <h3><?php echo $backups_result->num_rows; ?></h3>
                    <p>User Backups</p>
                </div>
            </div>
        </div>

        <!-- User Management -->
        <div class="admin-section">
            <h2><i class="fas fa-users-cog"></i> User Management</h2>
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Files</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['id']; ?>" data-file-count="<?php echo $user['file_count']; ?>">
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="current-user-badge">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <form method="POST" class="role-form">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" onchange="this.form.submit()" <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="action" value="change_role">
                                    </form>
                                </td>
                                <td><?php echo $user['file_count']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id'] && $user['username'] != 'admin'): ?>
                                        <form method="POST" class="delete-form" onsubmit="return confirmUserDeletion(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['file_count']; ?>)">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <button type="submit" class="delete-user-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Backups Section -->
        <div class="admin-section">
            <h2><i class="fas fa-archive"></i> User Backups</h2>
            <div class="security-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Backup System:</strong> Automatic backups are created before user deletion. Backups are kept for 30 days.
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
                                            <input type="hidden" name="action" value="restore_user">
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
                <p>No user backups available.</p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="admin-section">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="quick-actions">
                <button class="action-btn" onclick="location.href='index.php'">
                    <i class="fas fa-file-upload"></i>
                    <span>File Manager</span>
                </button>
                <button class="action-btn" onclick="location.href='admin_password_reset.php'">
                    <i class="fas fa-key"></i>
                    <span>Password Reset</span>
                </button>
                <button class="action-btn" onclick="location.href='admin_recovery.php'">
                    <i class="fas fa-life-ring"></i>
                    <span>Account Recovery</span>
                </button>
                <button class="action-btn" onclick="exportUserData()">
                    <i class="fas fa-download"></i>
                    <span>Export Data</span>
                </button>
            </div>
        </div>
    </div>

    <script>
    function confirmUserDeletion(userId, username, fileCount) {
        const confirmation = prompt(`WARNING: Deleting user "${username}" will permanently delete ALL their files (${fileCount} files).\n\nType "DELETE ${username}" to confirm:`);
        
        if (confirmation === `DELETE ${username}`) {
            return confirm(`FINAL WARNING: This will delete user "${username}" and ALL ${fileCount} files permanently. A backup will be created. Continue?`);
        } else {
            alert('Confirmation text does not match. Deletion cancelled.');
            return false;
        }
    }

    function exportUserData() {
        alert('Export functionality would generate CSV/Excel reports.');
    }
    </script>
</body>
</html>