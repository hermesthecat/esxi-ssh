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
                <input type="text" id="host" name="host" required>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="command">Command:</label>
                <input type="text" id="command" name="command" required>
            </div>

            <button type="submit" class="submit-btn">Connect & Execute</button>
        </form>

        <div id="connectionLog" class="connection-log">
            <h3>Connection Log</h3>
            <pre id="logContent"></pre>
        </div>
    </div>

    <script>
        document.getElementById('sshForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                host: document.getElementById('host').value,
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
                command: document.getElementById('command').value
            };

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