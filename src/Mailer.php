<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free SMTP client for shared hosting.
 *
 * Supports implicit TLS (smtps / port 465), STARTTLS (port 587) and plain
 * connections, plus AUTH LOGIN. It sends a single UTF-8 plain-text message and
 * is intentionally small — enough to deliver transactional auth emails far more
 * reliably than PHP's mail() on hosts like IONOS, without pulling in a library.
 *
 * Usage:
 *   $mailer = new Mailer($smtpConfig);
 *   $mailer->send($to, $subject, $textBody, $fromEmail, $fromName, $replyTo);
 *
 * Throws RuntimeException on any failure so callers can log and fall back.
 */
final class Mailer
{
    private string $host;
    private int $port;
    private string $security; // 'tls', 'ssl', or 'none'
    private string $username;
    private string $password;
    private int $timeout;
    private bool $allowSelfSigned;

    /** @var resource|null */
    private $socket = null;

    /**
     * @param array<string, mixed> $config Application config (config.local.php)
     */
    public function __construct(array $config)
    {
        $this->host = trim((string) ($config['smtp_host'] ?? ''));
        $this->port = (int) ($config['smtp_port'] ?? 587);
        $this->username = (string) ($config['smtp_username'] ?? '');
        $this->password = (string) ($config['smtp_password'] ?? '');
        $this->timeout = max(5, (int) ($config['smtp_timeout'] ?? 20));
        $this->allowSelfSigned = (bool) ($config['smtp_allow_self_signed'] ?? false);

        $security = strtolower(trim((string) ($config['smtp_security'] ?? '')));
        if ($security === '') {
            // Sensible default based on the port: 465 = implicit SSL, else TLS.
            $security = $this->port === 465 ? 'ssl' : 'tls';
        }
        $this->security = in_array($security, ['tls', 'ssl', 'none'], true) ? $security : 'tls';

        if ($this->host === '') {
            throw new RuntimeException('SMTP host is not configured.');
        }
    }

    public function send(
        string $to,
        string $subject,
        string $textBody,
        string $fromEmail,
        string $fromName = '',
        string $replyTo = ''
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient address.');
        }

        $transport = $this->security === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => !$this->allowSelfSigned,
                'verify_peer_name' => !$this->allowSelfSigned,
                'allow_self_signed' => $this->allowSelfSigned,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection failed: ' . ($errstr !== '' ? $errstr : 'unknown error'));
        }
        stream_set_timeout($this->socket, $this->timeout);

        try {
            $this->expect(220);

            $ehloHost = $this->ehloHostname();
            $this->command('EHLO ' . $ehloHost, 250);

            if ($this->security === 'tls') {
                $this->command('STARTTLS', 220);
                $crypto = @stream_socket_enable_crypto(
                    $this->socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                        | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                        | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                );
                if ($crypto !== true) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                // RFC 3207: re-issue EHLO over the encrypted channel.
                $this->command('EHLO ' . $ehloHost, 250);
            }

            if ($this->username !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->username), 334);
                $this->command(base64_encode($this->password), 235);
            }

            $fromEmail = trim($fromEmail);
            $this->command('MAIL FROM:<' . $fromEmail . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', 354);

            $message = $this->buildMessage($to, $subject, $textBody, $fromEmail, $fromName, $replyTo);
            // End-of-data terminator must be on its own line.
            $this->write($message . "\r\n.");
            $this->expect(250);

            $this->command('QUIT', [221], false);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
                $this->socket = null;
            }
        }

        return true;
    }

    private function buildMessage(
        string $to,
        string $subject,
        string $textBody,
        string $fromEmail,
        string $fromName,
        string $replyTo
    ): string {
        $from = $fromName !== ''
            ? $this->encodeHeader($fromName) . ' <' . $fromEmail . '>'
            : '<' . $fromEmail . '>';

        $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $from,
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: <' . trim($replyTo) . '>';
        }

        // Normalise newlines, then base64-encode in 76-char lines so no SMTP
        // line-length or dot-stuffing edge cases can corrupt the body.
        $normalised = str_replace(["\r\n", "\r"], "\n", $textBody);
        $body = rtrim(chunk_split(base64_encode($normalised), 76, "\r\n"));

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function ehloHostname(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        if ($host === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $host)) {
            $host = gethostname() ?: 'localhost';
        }
        return $host;
    }

    /**
     * @param int|array<int,int> $expected
     */
    private function command(string $line, int|array $expected, bool $check = true): string
    {
        $this->write($line);
        if (!$check) {
            return '';
        }
        return $this->expect($expected);
    }

    private function write(string $line): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP socket is not open.');
        }
        if (fwrite($this->socket, $line . "\r\n") === false) {
            throw new RuntimeException('Failed writing to the SMTP server.');
        }
    }

    /**
     * Reads a (possibly multi-line) SMTP reply and checks its status code.
     *
     * @param int|array<int,int> $expected
     */
    private function expect(int|array $expected): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP socket is not open.');
        }

        $expected = (array) $expected;
        $response = '';
        $code = 0;

        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            $code = (int) substr($line, 0, 3);
            // A hyphen after the code (e.g. "250-") means more lines follow.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException('Timed out waiting for the SMTP server.');
            }
        }

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException(
                'Unexpected SMTP reply (expected ' . implode('/', $expected) . '): ' . trim($response)
            );
        }

        return $response;
    }
}
