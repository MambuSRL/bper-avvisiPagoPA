<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Tests;

use Mambu\BperPagoPA\Transport\HttpResponse;
use Mambu\BperPagoPA\Transport\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var list<array{endpoint: string, body: string, headers: array<string, string>}> */
    public array $requests = [];

    /** @var list<HttpResponse> */
    private array $queue = [];

    public function push(string $body, int $status = 200): self
    {
        $this->queue[] = new HttpResponse($status, $body);

        return $this;
    }

    public function send(string $endpoint, string $body, array $headers): HttpResponse
    {
        $this->requests[] = ['endpoint' => $endpoint, 'body' => $body, 'headers' => $headers];

        return array_shift($this->queue) ?? throw new \LogicException('Nessuna risposta in coda');
    }

    public static function envelope(string $payload): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>'
            . $payload
            . '</soapenv:Body></soapenv:Envelope>';
    }

    public static function testata(string $id = 'TX1'): string
    {
        return "<testata><id_transazione>{$id}</id_transazione><codice_servizio>1234567</codice_servizio>"
            . '<codice_sottoservizio>7654321</codice_sottoservizio></testata>';
    }
}
