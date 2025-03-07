<?php
// Configuration settings
$auth_token = 'admin';            // Access passcode
$max_file_size = 500;             // Maximum file size allowed (in MB)
$upload_folder = 'uploads/';      // Upload folder
$enable_cleanup = true;           // Whether cleanup is enabled
$cleanup_duration = 3;            // Cleanup duration (in days)

// If the upload folder doesn't exist, attempt to create it.
if (!file_exists($upload_folder)) {
    if (!mkdir($upload_folder, 0755, true)) {  // 0755 is a common permission
        // JSON response for critical configuration errors could be implemented if needed.
        die(json_encode([
            'status'  => 'error',
            'message' => 'Error: Unable to create upload folder'
        ]));
    }
}

// Verify that the upload folder exists and is writable.
if (!is_dir($upload_folder) || !is_writable($upload_folder)) {
    die(json_encode([
        'status'  => 'error',
        'message' => 'Error: Upload folder must exist and be writable'
    ]));
}
?>
