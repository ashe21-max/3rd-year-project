// Drag and drop functionality
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const fileInfoContainer = document.getElementById('file-info-container');
const fileName = document.getElementById('file-name');
const fileSize = document.getElementById('file-size');
const fileType = document.getElementById('file-type');
const progressBar = document.getElementById('progress-bar');
const uploadBtn = document.getElementById('upload-btn');
const uploadForm = document.getElementById('uploadForm');

// Prevent default drag behaviors
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

// Highlight drop zone when item is dragged over it
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

// Handle dropped files
dropZone.addEventListener('drop', handleDrop, false);

// Browse button click
document.querySelector('.browse-btn').addEventListener('click', () => {
    fileInput.click();
});

// File input change
fileInput.addEventListener('change', handleFileSelect);

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight() {
    dropZone.classList.add('dragover');
}

function unhighlight() {
    dropZone.classList.remove('dragover');
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files);
}

function handleFileSelect(e) {
    const files = e.target.files;
    handleFiles(files);
}

function handleFiles(files) {
    if (files.length > 0) {
        const file = files[0];
        updateFileInfo(file);
        fileInfoContainer.classList.remove('hidden');
        uploadBtn.disabled = false;
        uploadBtn.classList.remove('disabled');
        
        // Show upload button immediately when file is selected
        uploadBtn.style.display = 'block';
    }
}

function updateFileInfo(file) {
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    fileType.textContent = file.type || getFileTypeFromExtension(file.name);
}

function getFileTypeFromExtension(filename) {
    const extension = filename.split('.').pop().toLowerCase();
    const typeMap = {
        'jpg': 'JPEG Image', 'jpeg': 'JPEG Image', 'png': 'PNG Image',
        'gif': 'GIF Image', 'pdf': 'PDF Document', 'doc': 'Word Document',
        'docx': 'Word Document', 'txt': 'Text File', 'zip': 'ZIP Archive',
        'mp3': 'Audio File', 'mp4': 'Video File'
    };
    return typeMap[extension] || 'File';
}

function formatFileSize(bytes) {
    if (bytes === 0) return "0 B";
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

// Remove the form submission interception - let PHP handle the actual upload
// Only show visual progress for better UX
uploadForm.addEventListener('submit', function(e) {
    const file = fileInput.files[0];
    if (file) {
        // Show uploading state
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        uploadBtn.disabled = true;
        
        // Simulate progress for better UX (but don't prevent form submission)
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 20;
            if (progress >= 90) { // Stop at 90% to let PHP handle the rest
                progress = 90;
                clearInterval(interval);
            }
            progressBar.style.width = progress + '%';
        }, 100);
    }
});

// Add click functionality to entire drop zone
dropZone.addEventListener('click', (e) => {
    if (e.target === dropZone || e.target.classList.contains('drop-zone-content')) {
        fileInput.click();
    }
});

// Show file info when page loads if there's already a file selected (after form submission)
document.addEventListener('DOMContentLoaded', function() {
    if (fileInput.files.length > 0) {
        handleFiles(fileInput.files);
    }
});