<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Enum;

enum StatoRT: string
{
    case TUTTI = 'TUTTI';
    /** Unico stato che certifica l'avvenuto pagamento. */
    case ESEGUITO = 'ESEGUITO';
    case NON_ESEGUITO = 'NON_ESEGUITO';
}
