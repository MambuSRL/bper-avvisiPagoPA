<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Exception;

/**
 * Risposta SOAP non conforme a quanto atteso.
 */
class InvalidResponseException extends \RuntimeException implements BperPagoPAException
{
}
