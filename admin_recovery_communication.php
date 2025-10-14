<?php
require_once 'config.php';
requireAdmin();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['send_message'])) {
        $recovery_id = intval($_POST['recovery_id']);
        $message = sanitizeInput($_POST['message']);
        $action = sanitizeInput($_POST['action']);
        
        // Get recovery details
        $recovery_result = $conn->query("SELECT * FROM recovery_attempts WHERE id = $recovery_id");
        $recovery = $recovery_result->fetch_assoc();
        
        if ($action === 'request_info') {
            // Send request for more information
            $conn->query("INSERT INTO recovery_messages (recovery_id, admin_id, message_type, message, status) VALUES ($recovery_id, {$_SESSION['user_id']}, 'info_request', '$message', 'sent')");
            
            // Generate verification token for user
            $token = bin2hex(random_bytes(32));
            $conn->query("UPDATE recovery_attempts SET verification_token = '$token', status = 'awaiting_response' WHERE id = $recovery_id");
            
            $_SESSION['success'] = "Message sent to user. They can respond with additional information.";
        } elseif ($action === 'verify_identity') {
            // Approve and send verification link
            $token = bin2hex(random_bytes(32));
            $verification_url = "http://$_SERVER[HTTP_HOST]/recovery.php?action=admin_verify&token=$token";
            
            $conn->query("UPDATE recovery_attempts SET verification_token = '$token', status = 'approved', admin_id = {$_SESSION['user_id']} WHERE id = $recovery_id");
            $conn->query("INSERT INTO recovery_messages (recovery_id, admin_id, message_type, message, status) VALUES ($recovery_id, {$_SESSION['user_id']}, 'verification', 'Your identity has been verified. Use this link to reset your credentials: $verification_url', 'sent')");
            
            $_SESSION['success'] = "User identity verified. Verification link generated.";
        } elseif ($action === 'reject') {
            $conn->query("UPDATE recovery_attempts SET status = 'rejected', admin_id = {$_SESSION['user_id']} WHERE id = $recovery_id");
            $conn->query("INSERT INTO recovery_messages (recovery_id, admin_id, message_type, message, status) VALUES ($recovery_id, {$_SESSION['user_id']}, 'rejection', '$message', 'sent')");
            
            $_SESSION['success'] = "Recovery request rejected.";
        }
    }
    $conn->close();
}

// Get pending recovery requests
$conn = getDBConnection();
$recovery_requests = $conn->query("
    SELECT ra.*, u.username, u.email, u.created_at as account_created 
    FROM recovery_attempts ra 
    JOIN users u ON ra.user_id = u.id 
    WHERE ra.status IN ('pending', 'awaiting_response') 
    ORDER BY ra.created_at DESC
");

// Get recovery messages
$messages_result = $conn->query("
    SELECT rm.*, u.username as admin_name 
    FROM recovery_messages rm 
    LEFT JOIN users u ON rm.admin_id = u.id 
    ORDER BY rm.created_at DESC
");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Communication - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-comments"></i> Recovery Communication</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo $_SESSION['username']; ?></strong>!</span>
                    <a href="admin.php" class="admin-btn"><i class="fas fa-tools"></i> Admin Panel</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="status-message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
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
                                <span class="request-status <?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                                <span class="request-date"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></span>
                            </div>
                            
                            <div class="request-details">
                                <p><strong>Original Email:</strong> <?php echo htmlspecialchars($request['email']); ?></p>
                                <p><strong>Account Created:</strong> <?php echo date('M j, Y', strtotime($request['account_created'])); ?></p>
                                <p><strong>Issue Details:</strong> <?php echo htmlspecialchars($request['details']); ?></p>
                            </div>
                            
                            <div class="request-actions">
                                <button onclick="openMessageModal(<?php echo $request['id']; ?>, 'request_info')" class="btn info">
                                    <i class="fas fa-question-circle"></i> Request Info
                                </button>
                                <button onclick="openMessageModal(<?php echo $request['id']; ?>, 'verify_identity')" class="btn success">
                                    <i class="fas fa-check-circle"></i> Verify Identity
                                </button>
                                <button onclick="openMessageModal(<?php echo $request['id']; ?>, 'reject')" class="btn danger">
                                    <i class="fas fa-times-circle"></i> Reject
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No pending recovery requests.</p>
            <?php endif; ?>
        </div>

        <!-- Recovery Messages -->
        <div class="admin-section">
            <h2><i class="fas fa-envelope"></i> Recovery Communications</h2>
            
            <?php if ($messages_result->num_rows > 0): ?>
                <div class="messages-list">
                    <?php while ($message = $messages_result->fetch_assoc()): ?>
                        <div class="message-card <?php echo $message['message_type']; ?>">
                            <div class="message-header">
                                <strong>
                                    <?php if ($message['admin_id']): ?>
                                        Admin: <?php echo htmlspecialchars($message['admin_name']); ?>
                                    <?php else: ?>
                                        User Response
                                    <?php endif; ?>
                                </strong>
                                <span class="message-type"><?php echo ucfirst(str_replace('_', ' ', $message['message_type'])); ?></span>
                                <span class="message-date"><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></span>
                            </div>
                            <div class="message-content"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No messages yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal hidden">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modalTitle">Send Message</h3>
            <form method="POST" id="messageForm">
                <input type="hidden" id="recovery_id" name="recovery_id">
                <input type="hidden" id="action" name="action">
                
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea id="message" name="message" rows="6" required placeholder="Type your message here..."></textarea>
                </div>
                
                <button type="submit" name="send_message" class="login-btn">Send Message</button>
            </form>
        </div>
    </div>

    <script>
    function openMessageModal(recoveryId, action) {
        const modal = document.getElementById('messageModal');
        const modalTitle = document.getElementById('modalTitle');
        const recoveryIdInput = document.getElementById('recovery_id');
        const actionInput = document.getElementById('action');
        
        recoveryIdInput.value = recoveryId;
        actionInput.value = action;
        
        // Set modal title based on action
        const titles = {
            'request_info': 'Request Additional Information',
            'verify_identity': 'Verify User Identity',
            'reject': 'Reject Recovery Request'
        };
        modalTitle.textContent = titles[action] || 'Send Message';
        
        modal.classList.remove('hidden');
    }

    // Close modal
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('messageModal').classList.add('hidden');
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('messageModal');
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
    </script>
</body>
</html>