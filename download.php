<?php
// Include the database connection
include 'db_connect.php';

if (isset($_GET['file_id'])) {
    $id = $_GET['file_id'];
    
    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM files WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $file = mysqli_fetch_assoc($result);
            $filepath = 'uploads/' . $file['name'];
            
            if (file_exists($filepath)) {
                // Update download count
                $newCount = $file['downloads'] + 1;
                $updateQuery = "UPDATE files SET downloads = ? WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateQuery);
                
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "ii", $newCount, $id);
                    mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);
                }
                
                // Download the file
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                
                // Clear output buffer
                ob_clean();
                flush();
                
                readfile($filepath);
                exit;
            } else {
                header("Location: index.php?status=error&message=File not found on server");
                exit();
            }
        } else {
            header("Location: index.php?status=error&message=File not found in database");
            exit();
        }
        
        mysqli_stmt_close($stmt);
    } else {
        header("Location: index.php?status=error&message=Database error");
        exit();
    }
} else {
    header("Location: index.php?status=error&message=No file specified");
    exit();
}

// Close connection
mysqli_close($conn);
?>