<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESXi SSH Connection</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <form id="sshForm" class="ssh-form">
            <h2>ESXi SSH Connection</h2>
            
            <div class="form-group">
                <label for="host">Host:</label>
                <input type="text" id="host" name="host" pattern="^[a-zA-Z0-9.-]+$" title="Enter a valid hostname or IP address" required>
                <span class="error-message" id="hostError"></span>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" pattern="^[a-zA-Z0-9_-]+$" title="Username can only contain letters, numbers, underscores and hyphens" required>
                <span class="error-message" id="usernameError"></span>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" minlength="1" required>
                <span class="error-message" id="passwordError"></span>
            </div>

            <div class="form-group">
                <label for="command">Command:</label>
                <input type="text" id="command" name="command" pattern="^[a-zA-Z0-9\s._/-]+$" title="Command can only contain letters, numbers, spaces and basic symbols" required>
                <span class="error-message" id="commandError"></span>
            </div>

            <button type="submit" class="submit-btn">Connect & Execute</button>
        </form>

        <div id="connectionLog" class="connection-log">
            <h3>Connection Log</h3>
            <pre id="logContent"></pre>
        </div>
    </div>

    <script>
        function sanitizeInput(input) {
            return input.replace(/[<>]/g, ''); // Basic XSS prevention
        }

        function validateHost(host) {
            // Allow hostnames and IP addresses
            const hostRegex = /^[a-zA-Z0-9.-]+$/;
            return hostRegex.test(host);
        }

        function validateUsername(username) {
            // Allow letters, numbers, underscores and hyphens
            const usernameRegex = /^[a-zA-Z0-9_-]+$/;
            return usernameRegex.test(username);
        }

        function validateCommand(command) {
            // Allow letters, numbers, spaces and basic symbols
            const commandRegex = /^[a-zA-Z0-9\s._/-]+$/;
            return commandRegex.test(command);
        }

        document.getElementById('sshForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Clear previous error messages
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

            // Get and sanitize form inputs
            const host = sanitizeInput(document.getElementById('host').value.trim());
            const username = sanitizeInput(document.getElementById('username').value.trim());
            const password = document.getElementById('password').value; // Don't trim password
            const command = sanitizeInput(document.getElementById('command').value.trim());

            // Validate inputs
            let hasError = false;

            if (!validateHost(host)) {
                document.getElementById('hostError').textContent = 'Invalid host format';
                hasError = true;
            }

            if (!validateUsername(username)) {
                document.getElementById('usernameError').textContent = 'Invalid username format';
                hasError = true;
            }

            if (!password) {
                document.getElementById('passwordError').textContent = 'Password is required';
                hasError = true;
            }

            if (!validateCommand(command)) {
                document.getElementById('commandError').textContent = 'Invalid command format';
                hasError = true;
            }

            if (hasError) {
                return;
            }

            const formData = { host, username, password, command };
            const logContent = document.getElementById('logContent');
            const submitBtn = document.querySelector('.submit-btn');

            try {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Connecting...';
                logContent.textContent = 'Connecting to host...';

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    if (result.output) {
                        logContent.textContent = result.output;
                    } else {
                        logContent.textContent = result.message;
                    }
                    logContent.classList.remove('error');
                } else {
                    logContent.textContent = 'Error: ' + result.message;
                    logContent.classList.add('error');
                }
            } catch (error) {
                logContent.textContent = 'Error: ' + error.message;
                logContent.classList.add('error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Connect & Execute';
            }
        });
    </script>
</body>
</html>