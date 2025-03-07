<?php
// process.php - Combined check, upload, and cleanup
// Validations use metadata from POST-only requests, and use file properties from the uploaded file for upload requests.

require_once 'config.php';

// Set header for JSON response.
header('Content-Type: application/json');

// Helper function to send a JSON response and exit.
function sendResponse($status, $message, $downloadUrl = null, $expiration = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($downloadUrl !== null) {
        $response['downloadUrl'] = $downloadUrl;
    }
    if ($expiration !== null) {
        $response['expiration'] = $expiration;
    }
    echo json_encode($response);
    exit;
}

// Cleanup function: deletes files older than the configured cleanup duration.
function cleanupFiles() {
    global $upload_folder, $cleanup_duration;
    
    if (!is_dir($upload_folder) || !is_writable($upload_folder)) {
        sendResponse('error', 'Error: Unable to access upload folder for cleanup');
    }

    $files = scandir($upload_folder);
    $current_time = time();

    // Calculate expiration time in seconds (days * seconds per day)
    $expirationSeconds = $cleanup_duration * 86400;

    foreach ($files as $file) {
        // Skip current, parent directory and files that start with a dot
        if ($file === '.' || $file === '..' || strpos($file, '.') === 0) continue;
        $file_path = $upload_folder . $file;
        if (!is_file($file_path)) continue;
        $file_time = filemtime($file_path);

        if (($current_time - $file_time) > $expirationSeconds) {
            if (!unlink($file_path)) {
                sendResponse('error', "Error: Cleanup process failed on file $file");
            }
        }
    }
}

// Run cleanup if enabled.
if ($enable_cleanup) {
    cleanupFiles();
}

// Retrieve authentication token from POST data (for both check and upload phases).
$provided_token = $_POST['auth_token'] ?? '';
if ($provided_token !== $auth_token) {
    sendResponse('error', 'Error: Invalid authentication token');
}

// Determine operation mode.
if (!isset($_FILES['file'])) {
    // -----------------------------
    // "Check" Phase: Validate using metadata from POST parameters.
    // Expected POST parameters: file_name, file_size, auth_token.
    // -----------------------------
    $file_name = $_POST['file_name'] ?? '';
    $file_size = $_POST['file_size'] ?? 0;

    // Prevent file names starting with a dot or slash to avoid hidden/invalid names.
    if (preg_match('/^[\.\/]/', $file_name)) {
        sendResponse('error', 'Error: File name cannot start with a symbol (., /)');
    }

    // Check if a file with the same name already exists.
    if (file_exists($upload_folder . $file_name)) {
        sendResponse('error', 'Error: File already exists');
    }

    // Validate file size.
    if ($file_size > ($max_file_size * 1048576)) {
        sendResponse('error', "Error: File size exceeds limit {$max_file_size} MB");
    }

    sendResponse('ok', 'Validation passed');
} else {
    // -----------------------------
    // Upload Phase: Process the file upload.
    // -----------------------------
    $file = $_FILES['file'];
    $file_name = $file['name'] ?? '';
    $file_size = $file['size'] ?? 0;

    // Validate file name.
    if (preg_match('/^[\.\/]/', $file_name)) {
        sendResponse('error', 'Error: File name cannot start with a symbol (., /)');
    }

    // Prevent duplicate file names.
    if (file_exists($upload_folder . $file_name)) {
        sendResponse('error', 'Error: File already exists');
    }

    // Validate the file size.
    if ($file_size > ($max_file_size * 1048576)) {
        sendResponse('error', "Error: File size exceeds limit {$max_file_size} MB");
    }

    if (!is_writable($upload_folder)) {
        sendResponse('error', 'Error: Folder not writable');
    }

    $target_path = $upload_folder . basename($file_name);

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Construct the file's URL.
        $url = "http://" . $_SERVER['HTTP_HOST'] . "/" . $upload_folder . basename($file_name);
        if ($enable_cleanup){
            sendResponse('ok', 'Upload successful!', $url, $cleanup_duration);
        } else {
            sendResponse('ok', 'Upload successful!', $url);
        }
    } else {
        sendResponse('error', 'Error: File upload failed');
    }
}
?>
