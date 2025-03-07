<!DOCTYPE html>
<html>
<head>
  <title>File-Fly</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
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
    <h1><u>File-Fly</u></h1>
    <br><br>
    <input type="file" id="fileInput">
    <br><br>
    <input type="password" id="authToken" placeholder="Enter authentication token">
    <br><br>
    <button onclick="uploadFile()">Upload</button>
    <br><br>
    <div id="responseArea"></div>
    <br>
    <button id="copyButton" style="display:none" onclick="copyUrl()">Copy URL</button>
  </center>

  <script>
    let downloadUrl = '';

    // Function to update or replace messages in a particular "message" element within response area.
    // In this case, we keep a dedicated progress message element.
    function updateProgressMessage(message, isError = false) {
      let progressElem = document.getElementById('progressMessage');
      if (!progressElem) {
        progressElem = document.createElement('div');
        progressElem.id = 'progressMessage';
        // Insert the progress element at the top of the response area.
        const responseArea = document.getElementById('responseArea');
        responseArea.append(progressElem);
      }
      progressElem.textContent = message;
      progressElem.className = isError ? 'error' : 'success';
    }

    // Function to append non-progress messages elsewhere.
    function displayMessage(message, isError = false) {
      // Avoid appending progress messages multiple times.
      const msgDiv = document.createElement('div');
      msgDiv.textContent = message;
      msgDiv.className = isError ? 'error' : 'success';
      document.getElementById('responseArea').appendChild(msgDiv);
    }

    function copyUrl() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(downloadUrl)
          .then(() => displayMessage('URL copied to clipboard!'))
          .catch(err => {
            console.error('Failed to copy: ', err);
            displayMessage('Failed to copy URL. Please try again.', true);
          });
      } else {
        // Fallback method if Clipboard API is unavailable.
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

    async function uploadFile() {
      const fileInput = document.getElementById('fileInput');
      const authToken = document.getElementById('authToken').value;
      const responseArea = document.getElementById('responseArea');
      responseArea.innerHTML = ''; // Clear previous messages

      // Hide copy button from previous uploads
      document.getElementById('copyButton').style.display = 'none';

      if (!fileInput.files[0]) {
        displayMessage('No file selected', true);
        return;
      }
      if (!authToken) {
        displayMessage('Please enter auth_token', true);
        return;
      }

      const file = fileInput.files[0];

      // 1. "Check" phase: Validate using metadata (using fetch API)
      let metadataFormData = new FormData();
      metadataFormData.append('file_name', file.name);
      metadataFormData.append('file_size', file.size);
      metadataFormData.append('auth_token', authToken);

      try {
        const checkResponse = await fetch('process.php', {
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

      // 2. "Upload" phase using XMLHttpRequest
      let uploadFormData = new FormData();
      uploadFormData.append('file', file);
      uploadFormData.append('auth_token', authToken);

      const xhr = new XMLHttpRequest();

      // Listen to progress events and update a single element with a numeric percentage.
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percentComplete = Math.round((e.loaded / e.total) * 100);
          updateProgressMessage('Upload progress: ' + percentComplete + '%');
        }
      });

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
                  displayMessage('Expiry: ' + uploadJson.expiration + ' Days');
                }
                displayMessage('URL: ' + downloadUrl);
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

      xhr.open('POST', 'process.php', true);
      xhr.send(uploadFormData);
    }
  </script>
</body>
</html>
