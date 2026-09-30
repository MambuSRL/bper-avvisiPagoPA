<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Xml\Dom;

final class AvvisoPostale
{
    public function __construct(
        public readonly string $ccpNumero,
        public readonly string $ccpIntestazione,
        public readonly string $ccpAutorizzazione,
        public readonly string $ccpCausale,
        public readonly string $valoreDataMatrix,
        public readonly ?string $immagineDataMatrix,
    ) {
    }

    public static function fromXml(DOMElement $element): self
    {
        return new self(
            Dom::requiredText($element, 'CCP_numero'),
            Dom::requiredText($element, 'CCP_intestazione'),
            Dom::requiredText($element, 'CCP_autorizzazione'),
            Dom::requiredText($element, 'CCP_causale'),
            Dom::requiredText($element, 'valore_data_matrix'),
            Dom::base64($element, 'immagine_data_matrix'),
        );
    }
}
