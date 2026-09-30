<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Model\IdentificativoRT;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Xml\Dom;

final class RTListResponse extends AbstractResponse
{
    /**
     * @param list<IdentificativoRT> $ricevute
     */
    public function __construct(
        Testata $testata,
        string $rawXml,
        public readonly int $totaleRT,
        public readonly array $ricevute,
    ) {
        parent::__construct($testata, $rawXml);
    }

    public static function fromXml(Testata $testata, DOMElement $data, string $rawXml): self
    {
        $lista = Dom::child($data, 'listaRT');

        return new self(
            $testata,
            $rawXml,
            (int) Dom::requiredText($data, 'totale_RT'),
            $lista === null ? [] : array_map(
                static fn (DOMElement $e): IdentificativoRT => IdentificativoRT::fromXml($e),
                Dom::children($lista, 'identificativoRT'),
            ),
        );
    }
}
