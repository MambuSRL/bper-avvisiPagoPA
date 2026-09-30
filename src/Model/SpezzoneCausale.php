<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Xml\Amount;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Spezzone della causale RPT: testuale (max 35) o strutturato (causale max 25 + importo).
 */
final class SpezzoneCausale
{
    private function __construct(
        public readonly string $causale,
        public readonly ?string $importo,
    ) {
    }

    public static function testo(string $causale): self
    {
        return new self($causale, null);
    }

    public static function strutturato(string $causale, string|int|float $importo): self
    {
        return new self($causale, Amount::normalize($importo));
    }

    public function appendTo(DOMElement $element): void
    {
        if ($this->importo === null) {
            Dom::append($element, 'spezzoneCausaleVersamento', $this->causale);

            return;
        }

        Dom::appendAll(Dom::append($element, 'spezzoneStrutturatoCausaleVersamento'), [
            'causaleSpezzone' => $this->causale,
            'importoSpezzone' => $this->importo,
        ]);
    }
}
