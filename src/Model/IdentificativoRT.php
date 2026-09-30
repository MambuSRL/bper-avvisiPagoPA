<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Chiave di una Ricevuta Telematica: IUV + codice contesto pagamento (CCP).
 */
final class IdentificativoRT
{
    public function __construct(
        public readonly string $iuv,
        public readonly string $ccp,
    ) {
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'identificativo_univoco_versamento' => $this->iuv,
            'codice_contesto_pagamento' => $this->ccp,
        ]);
    }

    public static function fromXml(DOMElement $element): self
    {
        return new self(
            Dom::requiredText($element, 'identificativo_univoco_versamento'),
            Dom::requiredText($element, 'codice_contesto_pagamento'),
        );
    }
}
