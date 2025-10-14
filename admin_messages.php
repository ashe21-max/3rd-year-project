<?php
require_once 'config.php';
requireAdmin();

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['send_message'])) {
        $user_id = intval($_POST['user_id']);
        $subject = sanitizeInput($_POST['subject']);
        $message = sanitizeInput($_POST['message']);
        $message_type = sanitizeInput($_POST['message_type']);
        
        $stmt = $conn->prepare("INSERT INTO admin_messages (user_id, admin_id, subject, message, message_type, status) VALUES (?, ?, ?, ?, ?, 'sent')");
        $stmt->bind_param("iisss", $user_id, $_SESSION['user_id'], $subject, $message, $message_type);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Message sent successfully!";
        } else {
            $_SESSION['error'] = "Failed to send message.";
        }
        $stmt->close();
    }
    elseif (isset($_POST['mark_read'])) {
        $message_id = intval($_POST['message_id']);
        $conn->query("UPDATE admin_messages SET status = 'read' WHERE id = $message_id");
    }
    $conn->close();
}

// Get all users and messages
$conn = getDBConnection();
$users_result = mysqli_query($conn, "SELECT id, username FROM users WHERE role != 'admin' ORDER BY username");
$messages_result = mysqli_query($conn, "
    SELECT am.*, u.username 
    FROM admin_messages am 
    JOIN users u ON am.user_id = u.id 
    ORDER BY am.created_at DESC
");

$unread_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM admin_messages WHERE status = 'sent'")->fetch_assoc()['count'];
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - File Upload System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-envelope"></i> Admin Messages</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo $_SESSION['username']; ?></strong>!</span>
                    <span class="admin-badge"><i class="fas fa-crown"></i> Administrator</span>
                    <a href="admin.php" class="admin-btn"><i class="fas fa-tools"></i> Admin Panel</a>
                    <a href="index.php" class="admin-btn"><i class="fas fa-home"></i> File Manager</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="status-message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="status-message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="admin-section">
            <h2><i class="fas fa-paper-plane"></i> Send Message to User</h2>
            <form method="POST" class="admin-form">
                <div class="form-group">
                    <label for="user_id">Select User:</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">-- Select User --</option>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message_type">Message Type:</label>
                    <select id="message_type" name="message_type" required>
                        <option value="password_reset">Password Reset</option>
                        <option value="account_recovery">Account Recovery</option>
                        <option value="security_alert">Security Alert</option>
                        <option value="general">General Message</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" name="subject" required>
                </div>

                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea id="message" name="message" rows="6" required placeholder="Type your message here..."></textarea>
                </div>

                <button type="submit" name="send_message" class="login-btn">Send Message</button>
            </form>
        </div>

        <div class="admin-section">
            <h2><i class="fas fa-inbox"></i> Sent Messages <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?> unread</span><?php endif; ?></h2>
            
            <?php if ($messages_result->num_rows > 0): ?>
                <div class="messages-list">
                    <?php while ($message = $messages_result->fetch_assoc()): ?>
                        <div class="message-card <?php echo $message['status']; ?>">
                            <div class="message-header">
                                <strong>To: <?php echo htmlspecialchars($message['username']); ?></strong>
                                <span class="message-type"><?php echo ucfirst(str_replace('_', ' ', $message['message_type'])); ?></span>
                                <span class="message-date"><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></span>
                                <span class="message-status"><?php echo $message['status']; ?></span>
                            </div>
                            <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                            <div class="message-content"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                            
                            <?php if ($message['status'] == 'sent'): ?>
                                <form method="POST" class="mark-read-form">
                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                    <button type="submit" name="mark_read" class="btn small">Mark as Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No messages sent yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>