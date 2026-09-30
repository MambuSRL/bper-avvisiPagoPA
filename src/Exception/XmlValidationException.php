<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Exception;

/**
 * Documento XML non valido rispetto agli schemi XSD del servizio.
 */
class XmlValidationException extends \RuntimeException implements BperPagoPAException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message . ($errors ? ': ' . implode('; ', $errors) : ''));
    }
}
