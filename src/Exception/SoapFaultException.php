<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Exception;

/**
 * SOAP Fault generico (senza detail riconosciuto).
 */
class SoapFaultException extends \RuntimeException implements BperPagoPAException
{
    public function __construct(
        public readonly string $faultCode,
        public readonly string $faultString,
        string $message = '',
        int $code = 0,
    ) {
        parent::__construct($message !== '' ? $message : $faultString, $code);
    }
}
