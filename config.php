<?php
// Configuration settings
$max_file_size = 500;             // Maximum file size allowed (in MB)
$upload_folder = 'uploads/';      // Upload folder
$enable_cleanup = true;           // Whether cleanup is enabled
$cleanup_duration = 3;            // Cleanup duration (in days)
$enable_authentication = true;    // Enable or disable authentication (true/false)
// Define an array with allowed authentication tokens
$auth_tokens = [
    'admin',        // Primary admin token
    'user1token'   // Additional user token
];

// If the upload folder doesn't exist, attempt to create it.
if (!file_exists($upload_folder)) {
    if (!mkdir($upload_folder, 0755, true)) {  // 0755 is a common permission
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