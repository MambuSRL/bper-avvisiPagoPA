<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Xml\Amount;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Voce di dettaglio dell'importo (max 10 per pagamento).
 */
final class Voce
{
    public readonly string $importo;

    public function __construct(
        public readonly string $codice,
        string|int|float $importo,
    ) {
        $this->importo = Amount::normalize($importo);
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'codice_voce' => $this->codice,
            'importo_voce' => $this->importo,
        ]);
    }
}
