<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Exception;

/**
 * Errore di rete / HTTP (nessuna risposta SOAP interpretabile).
 */
class TransportException extends \RuntimeException implements BperPagoPAException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
