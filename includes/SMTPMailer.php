<?php
class SMTPMailer
{
    private $host = 'smtp.gmail.com';
    private $port = 465;
    private $username;
    private $password;
    private $timeout = 30;
    private $socket;
    private $debug = false;
    private $logs = [];

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $body, $fromName = 'System Notification', $isHtml = false)
    {
        $this->logs = [];
        $this->log("Connecting to {$this->host}:{$this->port}...");

        $this->socket = fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            $this->log("Error: Could not connect ($errno: $errstr)");
            return false;
        }

        if (!$this->expect(220))
            return false;

        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->command("EHLO " . $serverName);
        if (!$this->expect(250))
            return false;

        $this->command("AUTH LOGIN");
        if (!$this->expect(334))
            return false;

        $this->command(base64_encode($this->username));
        if (!$this->expect(334))
            return false;

        $this->command(base64_encode($this->password));
        if (!$this->expect(235))
            return false;

        $this->command("MAIL FROM: <{$this->username}>");
        if (!$this->expect(250))
            return false;

        $this->command("RCPT TO: <$to>");
        if (!$this->expect(250))
            return false;

        $this->command("DATA");
        if (!$this->expect(354))
            return false;

        $headers = "MIME-Version: 1.0\r\n";
        $contentType = $isHtml ? "text/html" : "text/plain";
        $headers .= "Content-type: {$contentType}; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$this->username}>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date("r") . "\r\n";

        $content = "$headers\r\n$body\r\n.\r\n";
        $this->command($content, false); // Don't log full body
        if (!$this->expect(250))
            return false;

        $this->command("QUIT");
        fclose($this->socket);

        $this->log("Email sent successfully to $to");
        return true;
    }

    private function command($cmd, $log = true)
    {
        if ($log)
            $this->log("CLIENT: $cmd");
        fwrite($this->socket, "$cmd\r\n");
    }

    private function expect($code)
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ')
                break;
        }
        $this->log("SERVER: " . trim($response));

        if (substr($response, 0, 3) != $code) {
            $this->log("Error: Expected $code, got " . substr($response, 0, 3));
            return false;
        }
        return true;
    }

    private function log($msg)
    {
        $this->logs[] = "[" . date('H:i:s') . "] " . $msg;
        if ($this->debug)
            echo "$msg<br>";
    }

    public function getLogs()
    {
        return $this->logs;
    }
}
?>