<?php
// filehandler.php - Handles file upload, validation, cleanup, and download

require_once 'config.php'; // Include configuration settings

header('Content-Type: application/json'); // Default response type

// Sends a JSON response and exits
function sendResponse($status, $message, $downloadUrl = null, $expiration = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($downloadUrl !== null) {
        $response['downloadUrl'] = $downloadUrl;
    }
    if ($expiration !== null) {
        $response['expiration'] = $expiration;
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

// Deletes files older than the configured cleanup duration
function cleanupFiles($upload_folder, $cleanup_duration) {
    if (!is_dir($upload_folder) || !is_writable($upload_folder)) {
        sendResponse('error', 'Error: Unable to access upload folder for cleanup');
    }

    $files = scandir($upload_folder);
    $current_time = time();
    $expirationSeconds = $cleanup_duration * 86400; // Convert days to seconds

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || strpos($file, '.') === 0) continue;
        $file_path = $upload_folder . $file;
        if (!is_file($file_path)) continue;
        if (($current_time - filemtime($file_path)) > $expirationSeconds) {
            if (!unlink($file_path)) {
                sendResponse('error', "Error: Cleanup failed on file $file");
            }
        }
    }
}

// Handle POST requests (upload/check)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($enable_cleanup) {
        cleanupFiles($upload_folder, $cleanup_duration); // Run cleanup if enabled
    }

    $provided_token = $_POST['auth_token'] ?? '';
    // Check authentication only if enabled
    if ($enable_authentication && !in_array($provided_token, $auth_tokens, true)) {
		sendResponse('error', 'Error: Invalid authentication token');
	}

    // Check phase: Validate metadata
    if (!isset($_FILES['file'])) {
        $file_name = $_POST['file_name'] ?? '';
        $file_size = $_POST['file_size'] ?? 0;

        if (preg_match('/^[^a-zA-Z0-9]/', $file_name)) {
            sendResponse('error', 'Error: File name must start with a letter or number');
        }
        if (file_exists($upload_folder . $file_name)) {
            sendResponse('error', 'Error: File already exists');
        }
        if ($file_size > ($max_file_size * 1048576)) {
            sendResponse('error', "Error: File size exceeds limit {$max_file_size} MB");
        }

        sendResponse('ok', 'Validation passed');
    } 
    // Upload phase: Process file
    else {
        $file = $_FILES['file'];
        $file_name = $file['name'] ?? '';
        $file_size = $file['size'] ?? 0;

        if (preg_match('/^[^a-zA-Z0-9]/', $file_name)) {
            sendResponse('error', 'Error: File name must start with a letter or number');
        }
        if (file_exists($upload_folder . $file_name)) {
            sendResponse('error', 'Error: File already exists');
        }
        if ($file_size > ($max_file_size * 1048576)) {
            sendResponse('error', "Error: File size exceeds limit {$max_file_size} MB");
        }
        if (!is_writable($upload_folder)) {
            sendResponse('error', 'Error: Folder not writable');
        }

        $target_path = $upload_folder . basename($file_name);
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $downloadUrl = $protocol . $_SERVER['HTTP_HOST'] . "/filehandler.php?file=" . urlencode(basename($file_name));

            if ($enable_cleanup) {
                sendResponse('ok', 'Upload successful!', $downloadUrl, $cleanup_duration . ' days');
            } else {
                sendResponse('ok', 'Upload successful!', $downloadUrl);
            }
        } else {
            sendResponse('error', 'Error: File upload failed');
        }
    }
}
// Handle GET requests (download)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file'])) {
    if ($enable_cleanup) {
        cleanupFiles($upload_folder, $cleanup_duration); // Run cleanup if enabled
    }

    $requestedFile = basename($_GET['file']); // Prevent directory traversal
    $filePath = $upload_folder . $requestedFile;

    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'File not found.';
        exit;
    }

    // Determine MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
    finfo_close($finfo);

    // Set download headers
    header('Content-Type: ' . $mimeType);
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));

    // Send file
    ob_clean();
    flush();
    readfile($filePath);
    exit;
}
// Invalid request
else {
    http_response_code(400);
    sendResponse('error', 'Error: Invalid request');
}
?>