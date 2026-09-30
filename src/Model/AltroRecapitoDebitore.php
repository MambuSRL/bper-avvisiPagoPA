<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Xml\Dom;

final class AltroRecapitoDebitore
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $recapito,
    ) {
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'altro_tipo_recapito' => $this->tipo,
            'altro_recapito' => $this->recapito,
        ]);
    }
}
