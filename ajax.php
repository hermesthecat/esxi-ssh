<?php
session_start();
header('Content-Type: application/json');
require_once 'CommandValidator.php';

class SSHConnection
{
    private $connection;
    private $host;
    private $username;
    private $password;
    private $sessionId;
    private $timeout = 30;
    private $lastActivity;

    public function __construct($host, $username, $password, $timeout = 30)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->sessionId = null;
        $this->timeout = $timeout;
        $this->lastActivity = time();
    }

    public function connect()
    {
        if (!function_exists('ssh2_connect')) {
            return ['success' => false, 'message' => 'SSH2 extension is not installed'];
        }

        $ctx = stream_context_create(['socket' => ['timeout' => $this->timeout]]);
        $this->connection = @ssh2_connect($this->host, 22, [], $ctx);

        if (!$this->connection) {
            return ['success' => false, 'message' => 'Failed to connect to ' . $this->host . ' (timeout: ' . $this->timeout . 's)'];
        }

        stream_set_timeout($this->connection, $this->timeout);

        if (!@ssh2_auth_password($this->connection, $this->username, $this->password)) {
            return ['success' => false, 'message' => 'Authentication failed or timed out'];
        }

        $this->sessionId = md5(uniqid($this->host . $this->username, true));
        $this->lastActivity = time();

        $_SESSION['ssh_connections'][$this->sessionId] = [
            'host' => $this->host,
            'username' => $this->username,
            'connected_at' => time(),
            'last_used' => time(),
            'timeout' => $this->timeout
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

        if ($this->hasTimedOut()) {
            $this->disconnect();
            return ['success' => false, 'message' => 'Connection timed out due to inactivity'];
        }

        $validation = CommandValidator::validate($command);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $this->updateLastActivity();

        $stream = @ssh2_exec($this->connection, $command);
        if (!$stream) {
            return ['success' => false, 'message' => 'Failed to execute command or connection timed out'];
        }

        stream_set_timeout($stream, $this->timeout);
        stream_set_blocking($stream, true);

        $output = '';
        try {
            while ($buffer = @fgets($stream)) {
                if ($buffer === false) {
                    if ($this->hasStreamTimedOut($stream)) {
                        throw new Exception('Command execution timed out');
                    }
                    break;
                }
                $output .= $buffer;
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        @fclose($stream);

        return ['success' => true, 'output' => $output];
    }

    public function disconnect()
    {
        if ($this->connection) {
            // Close all channels
            $channels = @ssh2_exec($this->connection, 'exit');
            if ($channels) {
                @fclose($channels);
            }

            // Remove from session
            if ($this->sessionId && isset($_SESSION['ssh_connections'][$this->sessionId])) {
                unset($_SESSION['ssh_connections'][$this->sessionId]);
            }

            // Reset connection properties
            $this->connection = null;
            $this->sessionId = null;
            $this->lastActivity = null;

            return ['success' => true, 'message' => 'Disconnected successfully'];
        }

        return ['success' => false, 'message' => 'Not connected'];
    }

    private function hasTimedOut()
    {
        return (time() - $this->lastActivity) > $this->timeout;
    }

    private function hasStreamTimedOut($stream)
    {
        $info = stream_get_meta_data($stream);
        return $info['timed_out'];
    }

    private function updateLastActivity()
    {
        $this->lastActivity = time();
        if ($this->sessionId && isset($_SESSION['ssh_connections'][$this->sessionId])) {
            $_SESSION['ssh_connections'][$this->sessionId]['last_used'] = time();
        }
    }

    public static function cleanOldSessions($maxAge = 3600)
    {
        if (!isset($_SESSION['ssh_connections'])) {
            return;
        }

        $now = time();
        foreach ($_SESSION['ssh_connections'] as $sessionId => $info) {
            $timeout = isset($info['timeout']) ? $info['timeout'] : 3600;
            if ($now - $info['last_used'] > $timeout) {
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
    
    // Handle disconnect request
    if (isset($data['action']) && $data['action'] === 'disconnect') {
        if (!isset($data['session_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing session ID']);
            exit;
        }

        if (!isset($_SESSION['ssh_connections'][$data['session_id']])) {
            echo json_encode(['success' => false, 'message' => 'Invalid session']);
            exit;
        }

        $sessionInfo = $_SESSION['ssh_connections'][$data['session_id']];
        $ssh = new SSHConnection($sessionInfo['host'], $sessionInfo['username'], '');
        $result = $ssh->disconnect();
        echo json_encode($result);
        exit;
    }

    // Handle command execution
    if (!isset($data['command'])) {
        echo json_encode(['success' => false, 'message' => 'Missing command parameter']);
        exit;
    }

    $validation = CommandValidator::validate($data['command']);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => $validation['message']]);
        exit;
    }

    $timeout = isset($data['timeout']) ? intval($data['timeout']) : 30;
    $timeout = max(10, min($timeout, 300));

    if (isset($data['session_id']) && isset($_SESSION['ssh_connections'][$data['session_id']])) {
        $sessionInfo = $_SESSION['ssh_connections'][$data['session_id']];
        $ssh = new SSHConnection($sessionInfo['host'], $sessionInfo['username'], '', $timeout);
        $connection = $ssh->connect();
        
        if (!$connection['success']) {
            echo json_encode($connection);
            exit;
        }
    } else {
        if (!isset($data['host']) || !isset($data['username']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Missing connection parameters']);
            exit;
        }

        $ssh = new SSHConnection($data['host'], $data['username'], $data['password'], $timeout);
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
