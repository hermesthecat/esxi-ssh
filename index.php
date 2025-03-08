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
            
            <div class="connection-status" id="connectionStatus">
                <div class="status-info">
                    <span class="status-text">Not Connected</span>
                    <span class="host-info" id="hostInfo"></span>
                </div>
                <div class="status-actions">
                    <button type="button" id="disconnectBtn" class="disconnect-btn" style="display: none;">
                        <span class="disconnect-icon">⏻</span>
                        Disconnect
                    </button>
                </div>
            </div>

            <div id="connectionFields">
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
                    <label for="timeout">Timeout (seconds):</label>
                    <input type="number" id="timeout" name="timeout" min="10" max="300" value="30" required>
                    <span class="timeout-info">Range: 10-300 seconds</span>
                </div>
            </div>

            <div class="form-group command-group">
                <label for="command">Command:</label>
                <div class="command-input-group">
                    <input type="text" id="command" name="command" pattern="^[a-zA-Z0-9\s._/-]+$" title="Command can only contain letters, numbers, spaces and basic symbols" required>
                    <button type="button" class="preset-btn" id="togglePresets" title="Show Command Presets">📋</button>
                    <button type="button" class="history-btn" id="toggleHistory" title="Show Command History">▼</button>
                </div>
                <div id="commandPresets" class="command-presets">
                    <div class="presets-header">
                        <h4>Command Presets</h4>
                        <input type="text" id="presetSearch" placeholder="Search presets..." class="preset-search">
                    </div>
                    <div id="presetsList" class="presets-list"></div>
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

    <div id="disconnectModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Disconnect</h3>
            <p>Are you sure you want to disconnect from the server?</p>
            <div class="modal-actions">
                <button id="confirmDisconnect" class="confirm-btn">Disconnect</button>
                <button id="cancelDisconnect" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Session and Connection Management
        let currentSession = null;
        let isConnected = false;
        let commandTimeout = null;
        let disconnectInProgress = false;

        function updateConnectionStatus(connected, sessionId = null, hostInfo = '') {
            isConnected = connected;
            currentSession = sessionId;
            
            const statusText = document.querySelector('.status-text');
            const hostInfoEl = document.getElementById('hostInfo');
            const disconnectBtn = document.getElementById('disconnectBtn');
            const connectionFields = document.getElementById('connectionFields');
            const submitBtn = document.querySelector('.submit-btn');

            if (connected) {
                statusText.textContent = 'Connected';
                statusText.classList.add('connected');
                hostInfoEl.textContent = hostInfo;
                disconnectBtn.style.display = 'inline-flex';
                connectionFields.style.display = 'none';
                submitBtn.textContent = 'Execute Command';
            } else {
                statusText.textContent = 'Not Connected';
                statusText.classList.remove('connected');
                hostInfoEl.textContent = '';
                disconnectBtn.style.display = 'none';
                connectionFields.style.display = 'block';
                submitBtn.textContent = 'Connect & Execute';
                clearTimeout(commandTimeout);
            }
        }

        async function disconnectFromServer() {
            if (!currentSession || disconnectInProgress) return;

            disconnectInProgress = true;
            const logContent = document.getElementById('logContent');
            const disconnectBtn = document.getElementById('disconnectBtn');
            disconnectBtn.disabled = true;

            try {
                const response = await fetch('ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'disconnect',
                        session_id: currentSession
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    updateConnectionStatus(false);
                    logContent.textContent = 'Disconnected from server.';
                    logContent.classList.remove('error');
                    hideDisconnectModal();
                } else {
                    logContent.textContent = 'Error: ' + result.message;
                    logContent.classList.add('error');
                }
            } catch (error) {
                logContent.textContent = 'Error: Failed to disconnect - ' + error.message;
                logContent.classList.add('error');
            } finally {
                disconnectBtn.disabled = false;
                disconnectInProgress = false;
            }
        }

        // Command History Management
        let commandHistory = JSON.parse(localStorage.getItem('commandHistory') || '[]');
        const maxHistoryItems = 10;

        function addToHistory(command) {
            commandHistory = commandHistory.filter(cmd => cmd !== command);
            commandHistory.unshift(command);
            commandHistory = commandHistory.slice(0, maxHistoryItems);
            localStorage.setItem('commandHistory', JSON.stringify(commandHistory));
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

        // Command Presets
        let presets = [];
        fetch('presets.json')
            .then(response => response.json())
            .then(data => {
                presets = data.presets;
                updatePresetsDisplay();
            })
            .catch(error => console.error('Error loading presets:', error));

        function updatePresetsDisplay(searchTerm = '') {
            const presetsList = document.getElementById('presetsList');
            const filteredPresets = searchTerm 
                ? presets.filter(preset => 
                    preset.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    preset.description.toLowerCase().includes(searchTerm.toLowerCase()))
                : presets;

            presetsList.innerHTML = filteredPresets.map(preset => `
                <div class="preset-item" onclick="usePresetCommand('${preset.command}')">
                    <div class="preset-name">${preset.name}</div>
                    <div class="preset-description">${preset.description}</div>
                    <div class="preset-command">${preset.command}</div>
                </div>
            `).join('');
        }

        function usePresetCommand(command) {
            document.getElementById('command').value = command;
            document.getElementById('commandPresets').classList.remove('show');
        }

        // Modal Management
        const modal = document.getElementById('disconnectModal');
        
        function showDisconnectModal() {
            modal.classList.add('show');
        }
        
        function hideDisconnectModal() {
            modal.classList.remove('show');
        }

        // Event Listeners
        document.getElementById('presetSearch').addEventListener('input', function(e) {
            updatePresetsDisplay(e.target.value);
        });

        document.getElementById('togglePresets').addEventListener('click', function(e) {
            const presets = document.getElementById('commandPresets');
            const history = document.getElementById('commandHistory');
            history.classList.remove('show');
            presets.classList.toggle('show');
        });

        document.getElementById('toggleHistory').addEventListener('click', function(e) {
            const presets = document.getElementById('commandPresets');
            const history = document.getElementById('commandHistory');
            presets.classList.remove('show');
            history.classList.toggle('show');
        });

        document.getElementById('clearHistory').addEventListener('click', function() {
            commandHistory = [];
            localStorage.removeItem('commandHistory');
            updateHistoryDisplay();
        });

        document.getElementById('disconnectBtn').addEventListener('click', showDisconnectModal);
        document.getElementById('confirmDisconnect').addEventListener('click', disconnectFromServer);
        document.getElementById('cancelDisconnect').addEventListener('click', hideDisconnectModal);

        // Form submission
        document.getElementById('sshForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            clearTimeout(commandTimeout);
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

            const command = sanitizeInput(document.getElementById('command').value.trim());
            const timeout = parseInt(document.getElementById('timeout').value);
            
            if (!validateCommand(command)) {
                document.getElementById('commandError').textContent = 'Invalid command format';
                return;
            }

            let requestData = { 
                command,
                timeout
            };

            if (!isConnected) {
                const host = sanitizeInput(document.getElementById('host').value.trim());
                const username = sanitizeInput(document.getElementById('username').value.trim());
                const password = document.getElementById('password').value;

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

                if (hasError) return;

                requestData = { ...requestData, host, username, password };
            } else {
                requestData.session_id = currentSession;
            }

            addToHistory(command);

            const logContent = document.getElementById('logContent');
            const submitBtn = document.querySelector('.submit-btn');

            try {
                submitBtn.disabled = true;
                submitBtn.textContent = isConnected ? 'Executing...' : 'Connecting...';
                logContent.textContent = isConnected ? 'Executing command...' : 'Connecting to host...';

                const timeoutPromise = new Promise((_, reject) => {
                    commandTimeout = setTimeout(() => {
                        reject(new Error(`Operation timed out after ${timeout} seconds`));
                    }, timeout * 1000);
                });

                const response = await Promise.race([
                    fetch('ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(requestData)
                    }),
                    timeoutPromise
                ]);

                clearTimeout(commandTimeout);
                const result = await response.json();

                if (result.success) {
                    if (!isConnected && result.session_id) {
                        updateConnectionStatus(true, result.session_id, `${requestData.host} (${requestData.username})`);
                    }
                    
                    if (result.output) {
                        logContent.textContent = result.output;
                    } else {
                        logContent.textContent = result.message;
                    }
                    logContent.classList.remove('error');
                } else {
                    logContent.textContent = 'Error: ' + result.message;
                    logContent.classList.add('error');
                    if (!isConnected) {
                        updateConnectionStatus(false);
                    }
                }
            } catch (error) {
                clearTimeout(commandTimeout);
                logContent.textContent = 'Error: ' + error.message;
                logContent.classList.add('error');
                updateConnectionStatus(false);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = isConnected ? 'Execute Command' : 'Connect & Execute';
            }
        });

        // Input validation functions
        function sanitizeInput(input) {
            return input.replace(/[<>]/g, '');
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

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const presets = document.getElementById('commandPresets');
            const history = document.getElementById('commandHistory');
            const togglePresets = document.getElementById('togglePresets');
            const toggleHistory = document.getElementById('toggleHistory');

            if (!presets.contains(e.target) && e.target !== togglePresets) {
                presets.classList.remove('show');
            }
            if (!history.contains(e.target) && e.target !== toggleHistory) {
                history.classList.remove('show');
            }
            if (e.target === modal) {
                hideDisconnectModal();
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', function(e) {
            if (isConnected) {
                disconnectFromServer();
            }
        });
    </script>
</body>
</html>