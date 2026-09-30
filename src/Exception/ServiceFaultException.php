<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Exception;

use Mambu\BperPagoPA\ErrorCode;

/**
 * Fault applicativo del WS (FaultSchema v8): applicationFault, systemFault, inputFault,
 * datiTestataFault, servizioNonDisponibileFault.
 */
class ServiceFaultException extends SoapFaultException
{
    public readonly ?ErrorCode $errorCode;

    public function __construct(
        string $faultCode,
        string $faultString,
        public readonly string $faultType,
        public readonly ?string $codice,
        public readonly ?string $messaggio,
        public readonly ?string $layer,
        public readonly ?string $idConversazione = null,
    ) {
        $this->errorCode = ErrorCode::fromFaultCode($codice);
        $text = $messaggio ?? ($this->errorCode?->description() ?? $faultString);

        parent::__construct(
            $faultCode,
            $faultString,
            sprintf('[%s%s] %s', $faultType, $codice !== null ? ' ' . $codice : '', $text),
            $this->errorCode?->value ?? 0,
        );
    }

    public static function create(
        string $faultType,
        string $faultCode,
        string $faultString,
        ?string $codice,
        ?string $messaggio,
        ?string $layer,
        ?string $idConversazione,
    ): self {
        $class = match ($faultType) {
            'applicationFault' => ApplicationFaultException::class,
            'systemFault' => SystemFaultException::class,
            'inputFault' => InputFaultException::class,
            'datiTestataFault' => DatiTestataFaultException::class,
            'servizioNonDisponibileFault' => ServizioNonDisponibileFaultException::class,
            default => self::class,
        };

        return new $class($faultCode, $faultString, $faultType, $codice, $messaggio, $layer, $idConversazione);
    }

    public function is(ErrorCode $code): bool
    {
        return $this->errorCode === $code;
    }
}
