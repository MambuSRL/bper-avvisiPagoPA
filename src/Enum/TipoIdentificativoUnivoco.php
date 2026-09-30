<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Enum;

enum TipoIdentificativoUnivoco: string
{
    case PERSONA_FISICA = 'F';
    case PERSONA_GIURIDICA = 'G';
}
