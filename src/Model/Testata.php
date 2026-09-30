<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Xml\Dom;

final class Testata
{
    public function __construct(
        public readonly string $idTransazione,
        public readonly string $codiceServizio,
        public readonly string $codiceSottoservizio,
    ) {
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'id_transazione' => $this->idTransazione,
            'codice_servizio' => $this->codiceServizio,
            'codice_sottoservizio' => $this->codiceSottoservizio,
        ]);
    }

    public static function fromXml(DOMElement $element): self
    {
        return new self(
            Dom::requiredText($element, 'id_transazione'),
            Dom::requiredText($element, 'codice_servizio'),
            Dom::requiredText($element, 'codice_sottoservizio'),
        );
    }
}
