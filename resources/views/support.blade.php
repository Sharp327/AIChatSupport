<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>AI-Powered Customer Support</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            max-width: 600px;
            margin: auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input[type="file"] {
            display: block;
        }
        .form-group input[type="text"], .form-group select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled {
            background-color: #cccccc;
        }
        #response {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        #loading {
            display: none;
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f9f9f9;
            text-align: center;
            font-size: 16px;
            color: #333;
        }
    </style>
</head>
<body>

    <h1>AI-Powered Customer Support</h1>

    <!-- Loading Indicator -->
    <div id="loading">Loading, please wait...</div>

    <!-- Client Selection Form -->
    <div class="form-group">
        <label for="clientSelect">Select Client:</label>
        <select id="clientSelect">
            <option value="" selected disabled>Select a client</option>
            <!-- Example client options; these should be dynamically generated -->
            <option value="1">Client 1</option>
            <option value="2">Client 2</option>
            <option value="3">Client 3</option>
        </select>
    </div>

    <!-- Hidden Client ID Field -->
    <input type="hidden" id="clientId" />

    <!-- Guide Upload Form -->
    <div class="form-group">
        <label for="guide">Upload Website Guide:</label>
        <input type="file" id="guide" />
        <button id="uploadBtn" disabled>Upload Guide</button>
    </div>

    <!-- Query Form -->
    <div class="form-group">
        <label for="query">Enter your query:</label>
        <input type="text" id="query" placeholder="How can I reset my password?" disabled />
        <button id="queryBtn" disabled>Submit Query</button>
    </div>

    <!-- Response Display -->
    <div id="response"></div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;

            document.getElementById('clientSelect').addEventListener('change', function() {
                const selectedClientId = this.value;
                document.getElementById('clientId').value = selectedClientId;

                // Enable upload and query fields when a client is selected
                document.getElementById('uploadBtn').disabled = !selectedClientId;
                document.getElementById('query').disabled = !selectedClientId;
                document.getElementById('queryBtn').disabled = !selectedClientId;
            });

            document.getElementById('uploadBtn').addEventListener('click', uploadGuide);
            document.getElementById('queryBtn').addEventListener('click', submitQuery);
        });

        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        function uploadGuide() {
            const fileInput = document.getElementById('guide');
            const file = fileInput.files[0];
            const clientId = document.getElementById('clientId').value;

            if (!file) {
                alert('Please select a guide file to upload.');
                return;
            }

            const formData = new FormData();
            formData.append('guide', file);
            formData.append('clientId', clientId);

            showLoading();

            axios.post("{{ route('uploadGuide') }}", formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            })
            .then(response => {
                alert('Guide uploaded successfully.');
            })
            .catch(error => {
                console.error('Error uploading guide:', error);
                alert('Failed to upload guide.');
            })
            .finally(() => {
                hideLoading();
            });
        }

        function submitQuery() {
            const queryInput = document.getElementById('query');
            const query = queryInput.value.trim();
            const clientId = document.getElementById('clientId').value;

            if (!query) {
                alert('Please enter a query.');
                return;
            }

            showLoading();

            axios.post("{{ route('handleQuery') }}", { query, clientId })
                .then(response => {
                    const result = response.data.response;
                    document.getElementById('response').innerText = result;
                })
                .catch(error => {
                    console.error('Error submitting query:', error);
                    document.getElementById('response').innerText = 'Failed to get a response.';
                })
                .finally(() => {
                    hideLoading();
                });
        }
    </script>

</body>
</html>
