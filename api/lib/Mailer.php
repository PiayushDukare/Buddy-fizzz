<?php

class Mailer
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $secure;
    private int $timeout = 25;

    public bool $verifyPeer = true;
    public string $fromEmail;
    public string $fromName;

    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private array $attachments = [];
    private array $inline = [];

    public function __construct(string $host, int $port, string $user, string $pass, string $secure = 'tls')
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = strtolower($secure);
    }

    public function setFrom(string $email, string $name = ''): void
    {
        $this->fromEmail = $email;
        $this->fromName = $name;
    }

    public function addTo(string $email, string $name = ''): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->to[] = [$email, $name];
        }
    }

    public function addCc(string $email, string $name = ''): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->cc[] = [$email, $name];
        }
    }

    public function addBcc(string $email, string $name = ''): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bcc[] = [$email, $name];
        }
    }

    public function addReplyTo(string $email, string $name = ''): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->replyTo[] = [$email, $name];
        }
    }

    public function attach(string $path, string $filename = '', string $mime = 'application/octet-stream'): void
    {
        if (is_file($path) && is_readable($path)) {
            $this->attachments[] = [
                'data' => file_get_contents($path),
                'name' => $filename !== '' ? $filename : basename($path),
                'mime' => $mime,
            ];
        }
    }

    public function attachData(string $data, string $filename, string $mime = 'application/octet-stream'): void
    {
        $this->attachments[] = ['data' => $data, 'name' => $filename, 'mime' => $mime];
    }

    public function attachInline(string $data, string $cid, string $mime = 'image/png'): void
    {
        $this->inline[] = ['data' => $data, 'cid' => $cid, 'mime' => $mime];
    }

    public function send(string $subject, string $html): bool
    {
        if (empty($this->to) && empty($this->cc) && empty($this->bcc)) {
            throw new RuntimeException('No recipients');
        }

        $transport = $this->secure === 'ssl' ? 'ssl://' : 'tcp://';
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => $this->verifyPeer,
                'verify_peer_name' => $this->verifyPeer,
                'allow_self_signed' => !$this->verifyPeer,
                'SNI_enabled' => true,
                'peer_name' => $this->host,
            ],
        ]);
        $fp = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$fp) {
            throw new RuntimeException('SMTP connect failed: ' . $errstr);
        }
        stream_set_timeout($fp, $this->timeout);

        try {
            $this->expect($fp, 220);
            $this->cmd($fp, 'EHLO ' . $this->ehloName(), 250);

            if ($this->secure === 'tls') {
                $this->cmd($fp, 'STARTTLS', 220);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!stream_socket_enable_crypto($fp, true, $crypto)) {
                    throw new RuntimeException('Unable to start TLS encryption');
                }
                $this->cmd($fp, 'EHLO ' . $this->ehloName(), 250);
            }

            $this->cmd($fp, 'AUTH LOGIN', 334);
            $this->cmd($fp, base64_encode($this->user), 334);
            $this->cmd($fp, base64_encode($this->pass), 235);

            $this->cmd($fp, 'MAIL FROM:<' . $this->fromEmail . '>', 250);
            foreach (array_merge($this->to, $this->cc, $this->bcc) as $r) {
                $this->cmd($fp, 'RCPT TO:<' . $r[0] . '>', [250, 251]);
            }

            $this->cmd($fp, 'DATA', 354);
            $message = $this->buildMessage($subject, $html);
            fwrite($fp, $message . "\r\n.\r\n");
            $this->expect($fp, 250);
            $this->cmd($fp, 'QUIT', 221);
        } finally {
            fclose($fp);
        }

        return true;
    }

    private function ehloName(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        return preg_replace('/[^a-zA-Z0-9.\-]/', '', $host) ?: 'localhost';
    }

    private function cmd($fp, string $command, $expected): void
    {
        fwrite($fp, $command . "\r\n");
        $this->expect($fp, $expected);
    }

    private function expect($fp, $expected): void
    {
        $codes = (array) $expected;
        $line = '';
        do {
            $line = fgets($fp, 512);
            if ($line === false) {
                throw new RuntimeException('SMTP timeout / no response');
            }
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($line));
        }
    }

    private function buildMessage(string $subject, string $html): string
    {
        $eol = "\r\n";
        $boundary = 'bf_' . bin2hex(random_bytes(12));

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->addr($this->fromEmail, $this->fromName);
        $headers[] = 'To: ' . $this->addrList($this->to ?: [[$this->fromEmail, $this->fromName]]);
        if ($this->cc) {
            $headers[] = 'Cc: ' . $this->addrList($this->cc);
        }
        if ($this->replyTo) {
            $headers[] = 'Reply-To: ' . $this->addrList($this->replyTo);
        }
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->ehloName() . '>';
        $headers[] = 'MIME-Version: 1.0';

        // Bare text/html MIME part (used directly, or wrapped by related/mixed below).
        $htmlPart = 'Content-Type: text/html; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol . $eol
            . chunk_split(base64_encode($html)) . $eol;

        // When inline images are present, the HTML lives inside a multipart/related body.
        $relBoundary = 'bfr_' . bin2hex(random_bytes(12));
        $relatedCt = 'multipart/related; boundary="' . $relBoundary . '"';
        $relatedBody = '';
        if ($this->inline) {
            $relatedBody = '--' . $relBoundary . $eol . $htmlPart;
            foreach ($this->inline as $img) {
                $relatedBody .= '--' . $relBoundary . $eol;
                $relatedBody .= 'Content-Type: ' . $img['mime'] . $eol;
                $relatedBody .= 'Content-Transfer-Encoding: base64' . $eol;
                $relatedBody .= 'Content-ID: <' . $img['cid'] . '>' . $eol;
                $relatedBody .= 'Content-Disposition: inline' . $eol . $eol;
                $relatedBody .= chunk_split(base64_encode($img['data'])) . $eol;
            }
            $relatedBody .= '--' . $relBoundary . '--' . $eol;
        }

        if ($this->attachments) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $body = '--' . $boundary . $eol;
            if ($this->inline) {
                $body .= 'Content-Type: ' . $relatedCt . $eol . $eol . $relatedBody;
            } else {
                $body .= $htmlPart;
            }
            foreach ($this->attachments as $a) {
                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Type: ' . $a['mime'] . '; name="' . $a['name'] . '"' . $eol;
                $body .= 'Content-Transfer-Encoding: base64' . $eol;
                $body .= 'Content-Disposition: attachment; filename="' . $a['name'] . '"' . $eol . $eol;
                $body .= chunk_split(base64_encode($a['data'])) . $eol;
            }
            $body .= '--' . $boundary . '--' . $eol;
        } elseif ($this->inline) {
            $headers[] = 'Content-Type: ' . $relatedCt;
            $body = $relatedBody;
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($html));
        }

        $raw = implode($eol, $headers) . $eol . $eol . $body;
        return str_replace("\r\n.", "\r\n..", $raw);
    }

    private function addr(string $email, string $name): string
    {
        return $name !== '' ? $this->encodeHeader($name) . ' <' . $email . '>' : '<' . $email . '>';
    }

    private function addrList(array $list): string
    {
        return implode(', ', array_map(fn ($r) => $this->addr($r[0], $r[1]), $list));
    }

    private function encodeHeader(string $text): string
    {
        if (preg_match('/[^\x20-\x7e]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }
}
