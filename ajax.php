<?php
header('Content-Type: application/json');

class SSHConnection
{
    private $connection;
    private $host;
    private $username;
    private $password;

    public function __construct($host, $username, $password)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
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

        return ['success' => true, 'message' => 'Connected successfully'];
    }

    public function executeCommand($command)
    {
        if (!$this->connection) {
            return ['success' => false, 'message' => 'Not connected'];
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
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['host']) || !isset($data['username']) || !isset($data['password']) || !isset($data['command'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    $ssh = new SSHConnection($data['host'], $data['username'], $data['password']);
    $connection = $ssh->connect();

    if (!$connection['success']) {
        echo json_encode($connection);
        exit;
    }

    $result = $ssh->executeCommand($data['command']);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
