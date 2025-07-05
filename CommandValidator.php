<?php

class CommandValidator
{
    private static $allowedCommands = [
        // System Information
        'vmware -v',
        'esxcli system version get',
        'esxcli hardware platform get',
        'uptime',

        // CPU & Memory
        'esxcli hardware cpu list',
        'esxcli hardware memory get',
        'esxtop -b',

        // Storage
        'esxcli storage core device list',
        'esxcli storage vmfs extent list',
        'esxcli storage filesystem list',
        'df -h',

        // Network
        'esxcli network ip interface list',
        'esxcli network nic list',
        'esxcli network vm list',
        'esxcli network ip connection list',

        // Virtual Machines
        'vim-cmd vmsvc/getallvms',
        'vim-cmd vmsvc/power.getstate',
        'esxcli vm process list',
        'vim-cmd vmsvc/get.summary',

        // Services
        'esxcli system service list',
        'chkconfig --list',
        'service --status-all',

        // Logs
        'tail -f /var/log/vmkernel.log',
        'tail -f /var/log/hostd.log',
        'tail -f /var/log/auth.log',

        // Performance
        'esxtop',
        'resxtop',
        'vsish',
        'vscsiStats'
    ];

    private static $allowedCommandPrefixes = [
        'vim-cmd vmsvc/',
        'esxcli',
        'tail -f /var/log/',
        'cat /var/log/',
        'ls',
        'df',
        'ps',
        'top',
        'uname',
        'hostname'
    ];

    private static $deniedCommands = [
        'rm',
        'mv',
        'cp',
        'chmod',
        'chown',
        'mkfs',
        'fdisk',
        'dd',
        'wget',
        'curl',
        'ssh',
        'telnet',
        'ftp',
        'nc',
        'reboot',
        'shutdown',
        'poweroff',
        'init',
        'kill',
        'killall'
    ];

    public static function validate($command)
    {
        // Trim whitespace
        $command = trim($command);

        // Check if command is empty
        if (empty($command)) {
            return [
                'valid' => false,
                'message' => 'Command cannot be empty'
            ];
        }

        // Check against denied commands
        foreach (self::$deniedCommands as $denied) {
            if (preg_match('/^' . preg_quote($denied, '/') . '\b/', $command)) {
                return [
                    'valid' => false,
                    'message' => 'Command is not allowed for security reasons'
                ];
            }
        }

        // Check if command is in allowed list
        if (in_array($command, self::$allowedCommands)) {
            return [
                'valid' => true,
                'message' => 'Command is allowed'
            ];
        }

        // Check if command starts with allowed prefix
        foreach (self::$allowedCommandPrefixes as $prefix) {
            if (strpos($command, $prefix) === 0) {
                return [
                    'valid' => true,
                    'message' => 'Command prefix is allowed'
                ];
            }
        }

        // Validate command format
        if (!preg_match('/^[a-zA-Z0-9\s._-]+$/', $command)) {
            return [
                'valid' => false,
                'message' => 'Command contains invalid characters'
            ];
        }

        // Check for dangerous characters or patterns
        $dangerousPatterns = [
            '/[&;|]/',           // Command chaining
            '/[<>]/',            // Redirection
            '/\$/',              // Variable substitution
            '/`/',               // Command substitution
            '/\(\)/',            // Subshell execution
            '/\${.*}/',          // Variable expansion
            '/#/',               // Comments
            '/\\\\./',           // Escaped characters
            '/sudo/',            // Privilege escalation
            '/\bbash\b/',        // Shell execution
            '/\bsh\b/',          // Shell execution
            '/\bexec\b/'         // Direct execution
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return [
                    'valid' => false,
                    'message' => 'Command contains dangerous patterns'
                ];
            }
        }

        return [
            'valid' => false,
            'message' => 'Command is not in the allowed list'
        ];
    }

    public static function getAllowedCommands()
    {
        return self::$allowedCommands;
    }

    public static function getAllowedPrefixes()
    {
        return self::$allowedCommandPrefixes;
    }
}
