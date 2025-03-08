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

            <div class="form-group command-group">
                <label for="command">Command:</label>
                <div class="command-input-group">
                    <input type="text" id="command" name="command" pattern="^[a-zA-Z0-9\s._/-]+$" title="Command can only contain letters, numbers, spaces and basic symbols" required>
                    <button type="button" class="history-btn" id="toggleHistory" title="Show Command History">▼</button>
                </div>
                <div id="commandHistory" class="command-history">
                    <div class="history-header">
                        <h4>Command History</h4>
                        <button type="button" class="clear-history-btn" id="clearHistory">Clear History</button>
                    </div>
                    <ul id="historyList"></ul>
                </div>
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
        // Command History Management
        let commandHistory = JSON.parse(localStorage.getItem('commandHistory') || '[]');
        const maxHistoryItems = 10;

        function addToHistory(command) {
            // Remove duplicate if exists
            commandHistory = commandHistory.filter(cmd => cmd !== command);
            // Add new command to the beginning
            commandHistory.unshift(command);
            // Keep only the last maxHistoryItems commands
            commandHistory = commandHistory.slice(0, maxHistoryItems);
            // Save to localStorage
            localStorage.setItem('commandHistory', JSON.stringify(commandHistory));
            // Update history display
            updateHistoryDisplay();
        }

        function updateHistoryDisplay() {
            const historyList = document.getElementById('historyList');
            historyList.innerHTML = commandHistory.map(cmd => 
                `<li><button type="button" class="history-item" onclick="useHistoryCommand('${cmd.replace(/'/g, "\\'")}')">${cmd}</button></li>`
            ).join('');
        }

        function useHistoryCommand(command) {
            document.getElementById('command').value = command;
            document.getElementById('commandHistory').classList.remove('show');
        }

        // Initialize history display
        updateHistoryDisplay();

        // Toggle history visibility
        document.getElementById('toggleHistory').addEventListener('click', function() {
            document.getElementById('commandHistory').classList.toggle('show');
        });

        // Clear history
        document.getElementById('clearHistory').addEventListener('click', function() {
            commandHistory = [];
            localStorage.removeItem('commandHistory');
            updateHistoryDisplay();
        });

        function sanitizeInput(input) {
            return input.replace(/[<>]/g, ''); // Basic XSS prevention
        }

        function validateHost(host) {
            const hostRegex = /^[a-zA-Z0-9.-]+$/;
            return hostRegex.test(host);
        }

        function validateUsername(username) {
            const usernameRegex = /^[a-zA-Z0-9_-]+$/;
            return usernameRegex.test(username);
        }

        function validateCommand(command) {
            const commandRegex = /^[a-zA-Z0-9\s._/-]+$/;
            return commandRegex.test(command);
        }

        document.getElementById('sshForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

            const host = sanitizeInput(document.getElementById('host').value.trim());
            const username = sanitizeInput(document.getElementById('username').value.trim());
            const password = document.getElementById('password').value;
            const command = sanitizeInput(document.getElementById('command').value.trim());

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

            // Add command to history before execution
            addToHistory(command);

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

        // Close command history when clicking outside
        document.addEventListener('click', function(e) {
            const commandHistory = document.getElementById('commandHistory');
            const toggleHistory = document.getElementById('toggleHistory');
            if (!commandHistory.contains(e.target) && e.target !== toggleHistory) {
                commandHistory.classList.remove('show');
            }
        });
    </script>
</body>
</html>