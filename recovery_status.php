<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: recovery.php');
    exit;
}

$recovery_id = intval($_GET['id']);
$conn = getDBConnection();

$recovery_result = $conn->query("
    SELECT ra.*, u.username, rm.message, rm.created_at as message_date 
    FROM recovery_attempts ra 
    JOIN users u ON ra.user_id = u.id 
    LEFT JOIN recovery_messages rm ON ra.id = rm.recovery_id 
    WHERE ra.id = $recovery_id 
    ORDER BY rm.created_at DESC
");

$recovery = $recovery_result->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Status - File Upload System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-form">
            <h1><i class="fas fa-search"></i> Recovery Status</h1>
            <p>Request ID: #<?php echo $recovery_id; ?></p>
            
            <?php if ($recovery): ?>
                <div class="status-card">
                    <div class="status-header">
                        <h3>User: <?php echo htmlspecialchars($recovery['username']); ?></h3>
                        <span class="status-badge <?php echo $recovery['status']; ?>">
                            <?php echo ucfirst($recovery['status']); ?>
                        </span>
                    </div>
                    
                    <div class="status-details">
                        <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($recovery['created_at'])); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($recovery['updated_at'])); ?></p>
                    </div>
                    
                    <?php if ($recovery['message']): ?>
                        <div class="admin-message">
                            <h4>Latest Admin Message:</h4>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($recovery['message'])); ?>
                            </div>
                            <small>Sent: <?php echo date('M j, Y g:i A', strtotime($recovery['message_date'])); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="status-message error">
                    Recovery request not found. Please check the request ID.
                </div>
            <?php endif; ?>
            
            <div class="recovery-actions">
                <a href="recovery.php" class="login-btn">Back to Recovery</a>
                <a href="contact.php" class="login-btn secondary">Contact Support</a>
            </div>
        </div>
    </div>
</body>
</html>