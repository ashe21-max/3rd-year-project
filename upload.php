<?php
// Include the database connection
include 'db_connect.php';

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

if (isset($_POST['save']) && isset($_FILES['myfile'])) {
    $filename = $_FILES['myfile']['name'];
    $destination = 'uploads/' . $filename;
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $file = $_FILES['myfile']['tmp_name'];
    $size = $_FILES['myfile']['size'];
    
    $allowed_extensions = ['zip', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'mp3', 'mp4', 'doc', 'docx', 'txt'];
    
    if (!in_array($extension, $allowed_extensions)) {
        header("Location: index.php?status=error&message=File extension must be: " . implode(', ', $allowed_extensions));
        exit();
    } elseif ($_FILES['myfile']['size'] > 10000000) { // 10MB
        header("Location: index.php?status=error&message=File size too large (max 10MB)");
        exit();
    } else {
        // Check if file already exists and rename if necessary
        $counter = 1;
        $original_filename = $filename;
        while (file_exists($destination)) {
            $file_info = pathinfo($original_filename);
            $filename = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
            $destination = 'uploads/' . $filename;
            $counter++;
        }
        
        if (move_uploaded_file($file, $destination)) {
            // Use prepared statement to prevent SQL injection
            $sql = "INSERT INTO files (name, size, downloads) VALUES (?, ?, 0)";
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $filename, $size);
                
                if (mysqli_stmt_execute($stmt)) {
                    header("Location: index.php?status=success&message=File uploaded successfully");
                    exit();
                } else {
                    // Delete the file if database insertion fails
                    unlink($destination);
                    header("Location: index.php?status=error&message=Failed to upload file: " . mysqli_error($conn));
                    exit();
                }
                
                mysqli_stmt_close($stmt);
            } else {
                // Delete the file if prepared statement fails
                unlink($destination);
                header("Location: index.php?status=error&message=Failed to prepare database statement");
                exit();
            }
        } else {
            header("Location: index.php?status=error&message=Failed to move uploaded file");
            exit();
        }
    }
} else {
    header("Location: index.php?status=error&message=No file selected");
    exit();
}

// Close connection
mysqli_close($conn);
?>