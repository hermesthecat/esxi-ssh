<?php
session_start();
header('Content-Type: application/json');

class SSHConnection
{
    private $connection;
    private $host;
    private $username;
    private $password;
    private $sessionId;

    public function __construct($host, $username, $password)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->sessionId = null;
    }

    public function connect()
    {
        if (!function_exists('ssh2_connect')) {
            return ['success' => false, 'message' => 'SSH2 extension is not installed'];
        }

        $this->connection = ssh2_connect($this->host, 22);
        if (!$this->connection) {
            return ['success' => false, 'message' => 'Failed to connect to ' . $this->host];
        }

        if (!ssh2_auth_password($this->connection, $this->username, $this->password)) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        // Generate unique session ID
        $this->sessionId = md5(uniqid($this->host . $this->username, true));

        // Store connection info in session
        $_SESSION['ssh_connections'][$this->sessionId] = [
            'host' => $this->host,
            'username' => $this->username,
            'connected_at' => time(),
            'last_used' => time()
        ];

        return [
            'success' => true, 
            'message' => 'Connected successfully',
            'session_id' => $this->sessionId
        ];
    }

    public function executeCommand($command)
    {
        if (!$this->connection) {
            return ['success' => false, 'message' => 'Not connected'];
        }

        // Update last used timestamp
        if ($this->sessionId && isset($_SESSION['ssh_connections'][$this->sessionId])) {
            $_SESSION['ssh_connections'][$this->sessionId]['last_used'] = time();
        }

        $stream = ssh2_exec($this->connection, $command);
        if (!$stream) {
            return ['success' => false, 'message' => 'Failed to execute command'];
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);

        return ['success' => true, 'output' => $output];
    }

    public function disconnect()
    {
        if ($this->sessionId && isset($_SESSION['ssh_connections'][$this->sessionId])) {
            unset($_SESSION['ssh_connections'][$this->sessionId]);
        }
        $this->connection = null;
        $this->sessionId = null;
        return ['success' => true, 'message' => 'Disconnected successfully'];
    }

    public static function cleanOldSessions($maxAge = 3600) // Clean sessions older than 1 hour
    {
        if (!isset($_SESSION['ssh_connections'])) {
            return;
        }

        $now = time();
        foreach ($_SESSION['ssh_connections'] as $sessionId => $info) {
            if ($now - $info['last_used'] > $maxAge) {
                unset($_SESSION['ssh_connections'][$sessionId]);
            }
        }
    }
}

// Clean old sessions
SSHConnection::cleanOldSessions();

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['command'])) {
        echo json_encode(['success' => false, 'message' => 'Missing command parameter']);
        exit;
    }

    // Check if we have an existing session
    if (isset($data['session_id']) && isset($_SESSION['ssh_connections'][$data['session_id']])) {
        $sessionInfo = $_SESSION['ssh_connections'][$data['session_id']];
        $ssh = new SSHConnection($sessionInfo['host'], $sessionInfo['username'], '');
        $ssh->connect(); // Reconnect using existing session info
    } else {
        // New connection
        if (!isset($data['host']) || !isset($data['username']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Missing connection parameters']);
            exit;
        }

        $ssh = new SSHConnection($data['host'], $data['username'], $data['password']);
        $connection = $ssh->connect();
        
        if (!$connection['success']) {
            echo json_encode($connection);
            exit;
        }
    }

    $result = $ssh->executeCommand($data['command']);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
