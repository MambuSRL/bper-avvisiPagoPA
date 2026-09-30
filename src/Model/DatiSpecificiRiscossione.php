<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Enum\TipoContabilita;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Imputazione contabile dell'entrata ("tipo contabilità"/"codice contabilità", es. 9/codice tassonomico).
 */
final class DatiSpecificiRiscossione
{
    public function __construct(
        public readonly TipoContabilita $tipoContabilita,
        public readonly string $codiceContabilita,
    ) {
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'tipo_contabilita' => $this->tipoContabilita->value,
            'codice_contabilita' => $this->codiceContabilita,
        ]);
    }
}
