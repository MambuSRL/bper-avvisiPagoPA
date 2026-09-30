<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Enum;

enum TipoContabilita: string
{
    /** Capitolo e articolo di Entrata del Bilancio dello Stato */
    case CAPITOLO_BILANCIO_STATO = '0';
    /** Numero della contabilità speciale */
    case CONTABILITA_SPECIALE = '1';
    case CODICE_SIOPE = '2';
    /** Altro codice ad uso dell'amministrazione (es. tassonomia PagoPA) */
    case ALTRO = '9';
}
