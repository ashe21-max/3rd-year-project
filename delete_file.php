<?php
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $file_id = $data['file_id'];
    
    // First get the file info
    $sql = "SELECT * FROM files WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $file_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $file = mysqli_fetch_assoc($result);
        $filepath = 'uploads/' . $file['name'];
        
        // Delete from database
        $delete_sql = "DELETE FROM files WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $file_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Delete the actual file
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            echo json_encode(['status' => 'success', 'message' => 'File deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete file from database']);
        }
        
        mysqli_stmt_close($delete_stmt);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found in database']);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

mysqli_close($conn);
?>