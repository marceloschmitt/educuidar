<?php
/**
 * Cliente SMTP mínimo (AUTH LOGIN, STARTTLS/SSL).
 * Adaptado do projeto MAPA — mesmos parâmetros de conexão/remetente.
 */
class SmtpMailer {
    /** @var array{host: string, port: int, encryption: string, username: string, password: string, from_address: string, from_name: string} */
    private $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @throws RuntimeException
     */
    public function send(array $to, $subject, $bodyText, array $cc = []) {
        $destinatarios = $this->normalizeEmails($to);
        if ($destinatarios === []) {
            throw new RuntimeException('Nenhum destinatário válido.');
        }
        $copias = $this->normalizeEmails($cc);
        // Evita repetir no Cc quem já está no To
        $copias = array_values(array_filter($copias, static function ($email) use ($destinatarios) {
            return !in_array($email, $destinatarios, true);
        }));

        $host = trim((string) ($this->config['host'] ?? ''));
        $port = (int) ($this->config['port'] ?? 0);
        $encryption = strtolower(trim((string) ($this->config['encryption'] ?? 'tls')));
        $username = trim((string) ($this->config['username'] ?? ''));
        $password = (string) ($this->config['password'] ?? '');
        $from = trim((string) ($this->config['from_address'] ?? ''));
        $fromName = trim((string) ($this->config['from_name'] ?? ''));

        if ($host === '' || $from === '') {
            throw new RuntimeException('Host SMTP ou remetente não configurado.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @stream_socket_client(
            $remote . ':' . $port,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            throw new RuntimeException('Falha ao conectar no SMTP: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, 30);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO educuidar.local', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                    throw new RuntimeException('Falha ao iniciar STARTTLS.');
                }
                $this->command($socket, 'EHLO educuidar.local', [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            foreach (array_merge($destinatarios, $copias) as $email) {
                $this->command($socket, 'RCPT TO:<' . $email . '>', [250, 251]);
            }
            $this->command($socket, 'DATA', [354]);

            $headers = [];
            $headers[] = 'From: ' . $this->formatAddress($from, $fromName);
            $headers[] = 'To: ' . implode(', ', $destinatarios);
            if ($copias !== []) {
                $headers[] = 'Cc: ' . implode(', ', $copias);
            }
            $headers[] = 'Subject: ' . $this->encodeHeader($subject);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $headers[] = 'Auto-Submitted: auto-generated';
            $headers[] = 'X-Auto-Response-Suppress: All';
            $headers[] = 'Date: ' . date('r');

            $mensagem = implode("\r\n", $headers)
                . "\r\n\r\n"
                . $this->normalizeBody($bodyText)
                . "\r\n.";
            $this->write($socket, $mensagem);
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param list<string> $emails @return list<string> */
    private function normalizeEmails(array $emails) {
        $destinatarios = [];
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $destinatarios[$email] = true;
            }
        }
        return array_keys($destinatarios);
    }

    private function command($socket, $command, array $okCodes) {
        $this->write($socket, $command);
        $this->expect($socket, $okCodes);
    }

    private function write($socket, $data) {
        $payload = $data . "\r\n";
        $escrito = fwrite($socket, $payload);
        if ($escrito === false || $escrito < strlen($payload)) {
            throw new RuntimeException('Falha ao escrever no socket SMTP.');
        }
    }

    private function expect($socket, array $okCodes) {
        $resposta = '';
        while (($linha = fgets($socket, 515)) !== false) {
            $resposta .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') {
                break;
            }
        }

        $codigo = (int) substr($resposta, 0, 3);
        if (!in_array($codigo, $okCodes, true)) {
            throw new RuntimeException(
                'Resposta SMTP inesperada (' . $codigo . '): ' . trim($resposta)
            );
        }
    }

    private function formatAddress($email, $name) {
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader($value) {
        if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function normalizeBody($body) {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        return (string) preg_replace('/^\./m', '..', $body);
    }
}
