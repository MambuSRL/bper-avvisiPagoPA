<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Transport;

use Mambu\BperPagoPA\Exception\TransportException;

interface TransportInterface
{
    /**
     * Esegue una POST HTTP e restituisce la risposta (anche in caso di status >= 400, es. SOAP Fault).
     *
     * @param array<string, string> $headers
     *
     * @throws TransportException in caso di errore di rete
     */
    public function send(string $endpoint, string $body, array $headers): HttpResponse;
}
