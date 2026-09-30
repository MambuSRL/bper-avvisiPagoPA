<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Exception\InvalidResponseException;
use Mambu\BperPagoPA\Model\IdentificativoRT;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Xml\Dom;

final class RTReadResponse extends AbstractResponse
{
    /**
     * @param string $xmlRT XML della ricevuta telematica (già decodificato da base64)
     */
    public function __construct(
        Testata $testata,
        string $rawXml,
        public readonly IdentificativoRT $identificativoRT,
        public readonly string $xmlRT,
    ) {
        parent::__construct($testata, $rawXml);
    }

    public static function fromXml(Testata $testata, DOMElement $data, string $rawXml): self
    {
        return new self(
            $testata,
            $rawXml,
            IdentificativoRT::fromXml(Dom::requiredChild($data, 'Identificativo_RT')),
            Dom::base64($data, 'xml_RT') ?? throw new InvalidResponseException('xml_RT vuoto'),
        );
    }
}
