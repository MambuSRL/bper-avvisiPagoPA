<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Exception\InvalidArgumentException;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Causale della RPT: testo libero (max 140) oppure da 1 a 6 spezzoni.
 */
final class CausaleRPT
{
    /**
     * @param list<SpezzoneCausale> $spezzoni
     */
    private function __construct(
        public readonly ?string $causaleVersamento,
        public readonly array $spezzoni,
    ) {
    }

    public static function testo(string $causaleVersamento): self
    {
        return new self($causaleVersamento, []);
    }

    public static function spezzoni(SpezzoneCausale ...$spezzoni): self
    {
        if (count($spezzoni) < 1 || count($spezzoni) > 6) {
            throw new InvalidArgumentException('La causale RPT prevede da 1 a 6 spezzoni');
        }

        return new self(null, array_values($spezzoni));
    }

    public function appendTo(DOMElement $element): void
    {
        if ($this->causaleVersamento !== null) {
            Dom::append($element, 'causaleVersamento', $this->causaleVersamento);

            return;
        }

        foreach ($this->spezzoni as $spezzone) {
            $spezzone->appendTo(Dom::append($element, 'spezzoniCausaleVersamento'));
        }
    }
}
