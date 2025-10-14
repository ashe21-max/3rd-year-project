<?php
require_once 'config.php';
requireLogin();

// Initialize variables
$upload_status = null;
$delete_status = null;
$files = [];
$search_term = '';

// Handle search
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = sanitizeInput($_GET['search']);
}

// Handle file upload with tags
if (isset($_POST['save']) && isset($_FILES['myfile'])) {
    $filename = $_FILES['myfile']['name'];
    $file_tmp = $_FILES['myfile']['tmp_name'];
    $size = $_FILES['myfile']['size'];
    $error = $_FILES['myfile']['error'];
    $custom_tags = isset($_POST['tags']) ? sanitizeInput($_POST['tags']) : '';

    // Check for basic upload errors
    if ($error !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file selected',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped'
        ];
        $upload_status = ['status' => 'error', 'message' => $error_messages[$error] ?? 'Upload error: ' . $error];
    } 
    elseif (!is_uploaded_file($file_tmp)) {
        $upload_status = ['status' => 'error', 'message' => 'Invalid file upload.'];
    }
    else {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $category = getFileCategory($extension);
        
        // Check if extension is allowed
        $allowed_all = [];
        foreach (ALLOWED_EXTENSIONS as $extensions) {
            $allowed_all = array_merge($allowed_all, $extensions);
        }
        
        if (!in_array($extension, $allowed_all)) {
            $upload_status = ['status' => 'error', 'message' => 'File type .' . $extension . ' is not allowed.'];
        } 
        elseif ($size > MAX_FILE_SIZE) {
            $upload_status = ['status' => 'error', 'message' => 'File too large. Maximum size: ' . formatFileSize(MAX_FILE_SIZE)];
        } 
        else {
            // Ensure upload directory exists
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0777, true);
            }
            
            // Generate unique filename
            $unique_name = uniqid() . '_' . time() . '.' . $extension;
            $destination = UPLOAD_DIR . $unique_name;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $destination)) {
                // Extract tags from filename and combine with custom tags
                $auto_tags = extractTagsFromFilename($filename);
                $all_tags = $auto_tags;
                
                if (!empty($custom_tags)) {
                    $custom_tags_array = array_map('trim', explode(',', $custom_tags));
                    $all_tags = array_merge($all_tags, $custom_tags_array);
                }
                
                $tags_string = implode(', ', array_unique($all_tags));
                
                // Insert into database with tags
                $conn = getDBConnection();
                $sql = "INSERT INTO files (user_id, name, original_name, size, category, tags) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("ississ", $_SESSION['user_id'], $unique_name, $filename, $size, $category, $tags_string);
                    
                    if ($stmt->execute()) {
                        $upload_status = ['status' => 'success', 'message' => 'File "' . $filename . '" uploaded successfully!'];
                        if (!empty($tags_string)) {
                            $upload_status['message'] .= ' Tags: ' . $tags_string;
                        }
                        
                        // Reset form
                        echo '<script>
                            document.getElementById("uploadForm").reset();
                            document.getElementById("file-info-container").classList.add("hidden");
                            document.getElementById("upload-btn").disabled = true;
                            document.getElementById("upload-btn").classList.add("disabled");
                        </script>';
                    } else {
                        unlink($destination);
                        $upload_status = ['status' => 'error', 'message' => 'Database error: ' . $stmt->error];
                    }
                    $stmt->close();
                } else {
                    unlink($destination);
                    $upload_status = ['status' => 'error', 'message' => 'Database preparation failed: ' . $conn->error];
                }
                $conn->close();
            } else {
                $upload_status = ['status' => 'error', 'message' => 'Failed to move uploaded file. Check directory permissions.'];
            }
        }
    }
}

// Handle file download with authorization
if (isset($_GET['download'])) {
    $id = intval($_GET['download']);
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id WHERE f.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        
        if (canViewFile($file['user_id'])) {
            $filepath = UPLOAD_DIR . $file['name'];
            
            if (file_exists($filepath)) {
                // Update download count
                $update_stmt = $conn->prepare("UPDATE files SET downloads = downloads + 1 WHERE id = ?");
                $update_stmt->bind_param("i", $id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Download file
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                
                readfile($filepath);
                exit;
            }
        }
    }
    $stmt->close();
    $conn->close();
}

// Handle file deletion with authorization
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id WHERE f.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        
        if (canModifyFile($file['user_id'])) {
            $filepath = UPLOAD_DIR . $file['name'];
            
            $delete_stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            
            if ($delete_stmt->execute()) {
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                $delete_status = ['status' => 'success', 'message' => 'File deleted successfully!'];
            }
            $delete_stmt->close();
        }
    }
    $stmt->close();
    $conn->close();
}

// Fetch files based on user role and search term
$conn = getDBConnection();

// SIMPLIFIED SEARCH FOR NOW - Using LIKE instead of full-text
if ($is_admin = isAdmin()) {
    if (!empty($search_term)) {
        $sql = "SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id 
                WHERE f.original_name LIKE ? OR f.tags LIKE ? 
                ORDER BY f.upload_date DESC";
        $stmt = $conn->prepare($sql);
        $search_like = "%$search_term%";
        $stmt->bind_param("ss", $search_like, $search_like);
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
        $stmt = $conn->prepare($sql);
        $search_like = "%$search_term%";
        $stmt->bind_param("iss", $_SESSION['user_id'], $search_like, $search_like);
    } else {
        $sql = "SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id 
                WHERE f.user_id = ? ORDER BY f.upload_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
    }
}

$stmt->execute();
$result = $stmt->get_result();
$files = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Sort files alphabetically if no search term
if (empty($search_term)) {
    usort($files, function($a, $b) {
        return strcasecmp($a['original_name'], $b['original_name']);
    });
}

// Categorize files properly
$categories = [
    'image' => ['icon' => 'fa-image', 'color' => '#3498db', 'title' => 'Images', 'files' => []],
    'document' => ['icon' => 'fa-file-alt', 'color' => '#e74c3c', 'title' => 'Documents', 'files' => []],
    'media' => ['icon' => 'fa-film', 'color' => '#9b59b6', 'title' => 'Media Files', 'files' => []],
    'archive' => ['icon' => 'fa-file-archive', 'color' => '#f39c12', 'title' => 'Archives', 'files' => []],
    'other' => ['icon' => 'fa-file', 'color' => '#95a5a6', 'title' => 'Other Files', 'files' => []]
];

// Categorize each file
foreach ($files as $file) {
    $category = $file['category'];
    if (isset($categories[$category])) {
        $categories[$category]['files'][] = $file;
    } else {
        $categories['other']['files'][] = $file;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced File Upload System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-cloud-upload-alt"></i> Advanced File Uploader</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo $_SESSION['username']; ?></strong>!</span>
                    <?php if (isAdmin()): ?>
                        <span class="admin-badge"><i class="fas fa-crown"></i> Administrator</span>
                    <?php else: ?>
                        <span class="user-badge"><i class="fas fa-user"></i> User</span>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): ?>
                        <a href="admin.php" class="admin-btn"><i class="fas fa-tools"></i> Admin Panel</a>
                    <?php endif; ?>
                    
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </header>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search files by name or tags..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search_term)): ?>
                        <a href="index.php" class="clear-search">Clear Search</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if (!empty($search_term)): ?>
                <div class="search-results-info">
                    <i class="fas fa-search"></i>
                    Search results for: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                    <span class="result-count">(<?php echo count($files); ?> files found)</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upload Section -->
        <form class="upload-section" id="uploadForm" method="post" enctype="multipart/form-data" onsubmit="return validateFile()">
            <div class="drop-zone" id="drop-zone">
                <div class="drop-zone-content">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <p class="upload-text">Drag & drop your file here</p>
                    <p class="upload-subtext">or</p>
                    <button type="button" class="browse-btn"><i class="fas fa-search"></i> Browse Files</button>
                    <p class="upload-subtext">Max size: <?php echo formatFileSize(MAX_FILE_SIZE); ?></p>
                </div>
                <input type="file" id="file-input" name="myfile" class="hidden-input" required>
            </div>

            <div class="file-info-container hidden" id="file-info-container">
                <div class="file-details">
                    <p>File: <span id="file-name"></span></p>
                    <p>Size: <span id="file-size"></span></p>
                    <p>Type: <span id="file-type"></span></p>
                    <p>Category: <span id="file-category"></span></p>
                </div>
                
                <!-- Tags Input -->
                <div class="tags-input-container">
                    <label for="tags-input">Add tags (comma separated):</label>
                    <input type="text" id="tags-input" name="tags" placeholder="e.g., document, work, important">
                    <small>Tags help in searching files later. Auto-tags will be generated from filename.</small>
                </div>
            </div>

            <button class="upload-btn disabled" id="upload-btn" type="submit" name="save" disabled>
                <i class="fas fa-upload"></i> Upload File
            </button>
        </form>

        <?php if (isset($upload_status)): ?>
            <div class="status-message <?php echo $upload_status['status']; ?>">
                <i class="fas <?php echo $upload_status['status'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $upload_status['message']; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($delete_status)): ?>
            <div class="status-message <?php echo $delete_status['status']; ?>">
                <i class="fas <?php echo $delete_status['status'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $delete_status['message']; ?>
            </div>
        <?php endif; ?>

        <div class="files-list">
            <!-- File Statistics -->
            <div class="file-statistics">
                <div class="stat-card">
                    <i class="fas fa-file-upload"></i>
                    <div class="stat-info">
                        <h3><?php echo count($files); ?></h3>
                        <p>Total Files</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-sort-alpha-down"></i>
                    <div class="stat-info">
                        <h3><?php echo empty($search_term) ? 'Alphabetical' : 'Relevance'; ?></h3>
                        <p>Sort Order</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-search"></i>
                    <div class="stat-info">
                        <h3><?php echo !empty($search_term) ? 'Search Mode' : 'Browse Mode'; ?></h3>
                        <p>View Mode</p>
                    </div>
                </div>
            </div>

            <?php 
            $total_files = 0;
            foreach ($categories as $cat => $data): 
                $total_files += count($data['files']);
            endforeach; 
            ?>
            
            <?php if ($total_files > 0): ?>
                <?php foreach ($categories as $cat => $data): ?>
                    <?php if (count($data['files']) > 0): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <i class="fas <?php echo $data['icon']; ?>" style="color: <?php echo $data['color']; ?>;"></i>
                                <h2><?php echo $data['title']; ?> (<?php echo count($data['files']); ?>)</h2>
                            </div>
                            <div class="files-grid">
                                <?php foreach ($data['files'] as $file): ?>
                                    <div class="file-card">
                                        <div class="file-icon-box">
                                            <i class="fas <?php echo getFileIcon(pathinfo($file['original_name'], PATHINFO_EXTENSION)); ?>"></i>
                                        </div>
                                        <div class="file-details-box">
                                            <div class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                            <div class="file-meta">
                                                <span><?php echo formatFileSize($file['size']); ?></span> | 
                                                <span><i class="fas fa-download"></i> <?php echo $file['downloads']; ?></span> |
                                                <span><i class="fas fa-user"></i> <?php echo $file['username']; ?></span> |
                                                <span><i class="fas fa-folder"></i> <?php echo ucfirst($file['category']); ?></span>
                                            </div>
                                            <?php if (!empty($file['tags'])): ?>
                                                <div class="file-tags">
                                                    <i class="fas fa-tags"></i>
                                                    <?php 
                                                    $tags = explode(',', $file['tags']);
                                                    foreach (array_slice($tags, 0, 5) as $tag): ?>
                                                        <span class="tag"><?php echo trim($tag); ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($tags) > 5): ?>
                                                        <span class="tag-more">+<?php echo count($tags) - 5; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="upload-date"><?php echo date('M j, Y g:i A', strtotime($file['upload_date'])); ?></div>
                                            
                                            <!-- Ownership indicator -->
                                            <?php if ($file['user_id'] == $_SESSION['user_id']): ?>
                                                <div class="ownership-badge">Your File</div>
                                            <?php elseif (isAdmin()): ?>
                                                <div class="ownership-badge admin-file">User: <?php echo $file['username']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="file-actions-box">
                                            <?php if (canViewFile($file['user_id'])): ?>
                                                <a href="?download=<?php echo $file['id']; ?>" class="action-link download-link">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (canModifyFile($file['user_id'])): ?>
                                                <a href="?delete=<?php echo $file['id']; ?>" class="action-link delete-link" 
                                                   onclick="return confirm('Are you sure you want to delete this file?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <?php if (!empty($search_term)): ?>
                        <i class="fas fa-search empty-icon"></i>
                        <h3>No files found</h3>
                        <p>No files match your search criteria. Try different keywords.</p>
                        <a href="index.php" class="browse-btn">Browse All Files</a>
                    <?php else: ?>
                        <i class="fas fa-cloud-upload-alt empty-icon"></i>
                        <h3>No files found</h3>
                        <p>
                            <?php if (isAdmin()): ?>
                                No files have been uploaded to the system yet.
                            <?php else: ?>
                                You haven't uploaded any files yet. Upload your first file to get started!
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function validateFile() {
        const fileInput = document.getElementById('file-input');
        if (!fileInput.files.length) {
            alert('Please select a file to upload.');
            return false;
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const fileInfo = document.getElementById('file-info-container');
        const uploadBtn = document.getElementById('upload-btn');

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                document.getElementById('file-name').textContent = file.name;
                document.getElementById('file-size').textContent = formatFileSize(file.size);
                document.getElementById('file-type').textContent = file.type || 'Unknown';
                
                const ext = file.name.split('.').pop().toLowerCase();
                const categories = {
                    'image': ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'],
                    'document': ['pdf', 'doc', 'docx', 'txt', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx', 'csv'],
                    'media': ['mp3', 'wav', 'ogg', 'mp4', 'avi', 'mov', 'mkv', 'webm', 'flv'],
                    'archive': ['zip', 'rar', '7z', 'tar', 'gz']
                };
                
                let category = 'other';
                for (const [cat, exts] of Object.entries(categories)) {
                    if (exts.includes(ext)) {
                        category = cat;
                        break;
                    }
                }
                document.getElementById('file-category').textContent = category;
                
                // Auto-generate tags suggestion from filename
                const filename = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
                const tags = filename.split(/[\s_\-\.]+/).filter(tag => tag.length >= 3);
                const tagsInput = document.getElementById('tags-input');
                if (tags.length > 0 && !tagsInput.value) {
                    tagsInput.placeholder = "Suggested: " + tags.slice(0, 3).join(', ');
                }
                
                fileInfo.classList.remove('hidden');
                uploadBtn.disabled = false;
                uploadBtn.classList.remove('disabled');
            }
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
            dropZone.addEventListener(event, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(event => {
            dropZone.addEventListener(event, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(event => {
            dropZone.addEventListener(event, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        document.querySelector('.browse-btn').addEventListener('click', () => {
            fileInput.click();
        });

        // Quick search functionality
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        }
    });

    function formatFileSize(bytes) {
        if (bytes === 0) return "0 B";
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Auto-focus search input
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && searchInput.value === '') {
            searchInput.focus();
        }
    });
    </script>
</body>
</html>