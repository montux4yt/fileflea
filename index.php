<!DOCTYPE html>
<html>
<head>
  <title>File-Flea</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Basic page styling */
    body {
      background-color: gray;
    }
    #responseArea {
      background-color: black;
      margin-top: 20px;
      min-height: 100px;
      border: 1px solid #ccc;
      padding: 10px;
      color: white;
    }
    .error {
      color: red;
    }
    .success {
      color: green;
    }
  </style>
</head>
<body>
  <center>
    <!-- Main heading -->
    <h1><u>File-Flea</u></h1>
    <br><br>

    <!-- File input -->
    <input type="file" id="fileInput">
    <br><br>

    <!-- Authentication token field (shown only if enabled) -->
    <?php
    require_once 'config.php'; // Include config to access $enable_authentication
    if ($enable_authentication) {
      echo '<input type="password" id="authToken" placeholder="Enter authentication token">';
      echo '<br><br>';
    }
    ?>

    <!-- Upload button -->
    <button onclick="uploadFile()">Upload</button>
    <br><br>

    <!-- Response display area -->
    <div id="responseArea"></div>
    <br>

    <!-- Copy URL button, hidden by default -->
    <button id="copyButton" style="display:none" onclick="copyUrl()">Copy URL</button>
  </center>

  <script>
    let downloadUrl = ''; // Stores the URL for copying

    // Updates or creates a single progress message element
    function updateProgressMessage(message, isError = false) {
      let progressElem = document.getElementById('progressMessage');
      if (!progressElem) {
        progressElem = document.createElement('div');
        progressElem.id = 'progressMessage';
        document.getElementById('responseArea').append(progressElem); // Add at top
      }
      progressElem.textContent = message;
      progressElem.className = isError ? 'error' : 'success';
    }

    // Appends a new message to the response area
    function displayMessage(message, isError = false) {
      const msgDiv = document.createElement('div');
      msgDiv.textContent = message;
      msgDiv.className = isError ? 'error' : 'success';
      document.getElementById('responseArea').appendChild(msgDiv);
    }

    // Copies the download URL to clipboard
    function copyUrl() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(downloadUrl)
          .then(() => displayMessage('URL copied to clipboard!'))
          .catch(err => {
            console.error('Failed to copy: ', err);
            displayMessage('Failed to copy URL. Please try again.', true);
          });
      } else {
        // Fallback for older browsers
        const tempInput = document.createElement('input');
        tempInput.value = downloadUrl;
        document.body.appendChild(tempInput);
        tempInput.select();
        try {
          document.execCommand('copy');
          displayMessage('URL copied to clipboard!');
        } catch (err) {
          console.error('Fallback: Failed to copy: ', err);
          displayMessage('Failed to copy URL. Please try again.', true);
        }
        document.body.removeChild(tempInput);
      }
    }

    // Handles file upload process
    async function uploadFile() {
      const fileInput = document.getElementById('fileInput');
      const authTokenInput = document.getElementById('authToken');
      const authToken = authTokenInput ? authTokenInput.value : ''; // Use empty string if auth is disabled
      const responseArea = document.getElementById('responseArea');

      // Clear previous content and hide copy button
      responseArea.innerHTML = '';
      document.getElementById('copyButton').style.display = 'none';

      // Validate inputs
      if (!fileInput.files[0]) {
        displayMessage('No file selected', true);
        return;
      }
      <?php if ($enable_authentication) { ?>
      if (!authToken) {
        displayMessage('Please enter auth_token', true);
        return;
      }
      <?php } ?>

      const file = fileInput.files[0];

      // Phase 1: Validate file metadata
      const metadataFormData = new FormData();
      metadataFormData.append('file_name', file.name);
      metadataFormData.append('file_size', file.size);
      metadataFormData.append('auth_token', authToken);

      try {
        const checkResponse = await fetch('filehandler.php', {
          method: 'POST',
          body: metadataFormData
        });
        const checkJson = await checkResponse.json();

        if (checkJson.status !== 'ok') {
          displayMessage(checkJson.message, true);
          return;
        }
        displayMessage('Validation passed!');
      } catch (error) {
        displayMessage('Network error during validation', true);
        return;
      }

      // Phase 2: Upload the file
      const uploadFormData = new FormData();
      uploadFormData.append('file', file);
      uploadFormData.append('auth_token', authToken);

      const xhr = new XMLHttpRequest();

      // Track upload progress
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percentComplete = Math.round((e.loaded / e.total) * 100);
          updateProgressMessage(`Upload progress: ${percentComplete}%`);
        }
      });

      // Handle upload completion
      xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          if (xhr.status === 200) {
            try {
              const uploadJson = JSON.parse(xhr.responseText);
              if (uploadJson.status !== 'ok') {
                updateProgressMessage(uploadJson.message, true);
              } else {
                downloadUrl = uploadJson.downloadUrl;
                updateProgressMessage('Upload successful!');
                if (uploadJson.expiration) {
                  displayMessage(`Expiration: ${uploadJson.expiration}`);
                }
                displayMessage(`URL: ${downloadUrl}`);
                document.getElementById('copyButton').style.display = 'block';
              }
            } catch (e) {
              updateProgressMessage('Error parsing server response', true);
            }
          } else {
            updateProgressMessage('Network error during upload', true);
          }
        }
      };

      xhr.open('POST', 'filehandler.php', true);
      xhr.send(uploadFormData);
    }
  </script>
</body>
</html>
