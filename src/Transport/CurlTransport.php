<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Transport;

use Mambu\BperPagoPA\Exception\InvalidArgumentException;
use Mambu\BperPagoPA\Exception\TransportException;

/**
 * Transport HTTP basato su ext-curl.
 *
 * Opzioni supportate:
 *  - timeout (int, secondi, default 60)
 *  - connect_timeout (int, secondi, default 10)
 *  - verify (bool, default true): verifica certificato server
 *  - ca_info (string): path bundle CA
 *  - ssl_cert (string), ssl_cert_type (string, default PEM), ssl_cert_password (string): certificato client (mTLS)
 *  - ssl_key (string), ssl_key_password (string): chiave privata client
 *  - username / password (string): HTTP Basic auth
 *  - proxy (string)
 *  - curl (array<int, mixed>): opzioni CURLOPT_* aggiuntive
 */
final class CurlTransport implements TransportInterface
{
    private const KNOWN_OPTIONS = [
        'timeout', 'connect_timeout', 'verify', 'ca_info', 'ssl_cert', 'ssl_cert_type', 'ssl_cert_password',
        'ssl_key', 'ssl_key_password', 'username', 'password', 'proxy', 'curl',
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
        $unknown = array_diff(array_keys($options), self::KNOWN_OPTIONS);
        if ($unknown) {
            throw new InvalidArgumentException('Opzioni CurlTransport non riconosciute: ' . implode(', ', $unknown));
        }
    }

    public function send(string $endpoint, string $body, array $headers): HttpResponse
    {
        $handle = curl_init($endpoint);
        if ($handle === false) {
            throw new TransportException('Impossibile inizializzare cURL');
        }

        $httpHeaders = [];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = $name . ': ' . $value;
        }

        $verify = (bool) ($this->options['verify'] ?? true);
        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($this->options['timeout'] ?? 60),
            CURLOPT_CONNECTTIMEOUT => (int) ($this->options['connect_timeout'] ?? 10),
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ];

        if (isset($this->options['ca_info'])) {
            $curlOptions[CURLOPT_CAINFO] = $this->options['ca_info'];
        }
        if (isset($this->options['ssl_cert'])) {
            $curlOptions[CURLOPT_SSLCERT] = $this->options['ssl_cert'];
            $curlOptions[CURLOPT_SSLCERTTYPE] = $this->options['ssl_cert_type'] ?? 'PEM';
        }
        if (isset($this->options['ssl_cert_password'])) {
            $curlOptions[CURLOPT_SSLCERTPASSWD] = $this->options['ssl_cert_password'];
        }
        if (isset($this->options['ssl_key'])) {
            $curlOptions[CURLOPT_SSLKEY] = $this->options['ssl_key'];
        }
        if (isset($this->options['ssl_key_password'])) {
            $curlOptions[CURLOPT_SSLKEYPASSWD] = $this->options['ssl_key_password'];
        }
        if (isset($this->options['username'])) {
            $curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $curlOptions[CURLOPT_USERPWD] = $this->options['username'] . ':' . ($this->options['password'] ?? '');
        }
        if (isset($this->options['proxy'])) {
            $curlOptions[CURLOPT_PROXY] = $this->options['proxy'];
        }

        curl_setopt_array($handle, ($this->options['curl'] ?? []) + $curlOptions);

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            $errno = curl_errno($handle);

            throw new TransportException(sprintf('Errore cURL (%d): %s', $errno, $error));
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, (string) $responseBody);
    }
}
