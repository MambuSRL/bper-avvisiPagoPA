<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DateTimeImmutable;
use DOMElement;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Xml\Dom;

final class DeleteResponse extends AbstractResponse
{
    public function __construct(
        Testata $testata,
        string $rawXml,
        public readonly DateTimeImmutable $timestampAnnullamento,
    ) {
        parent::__construct($testata, $rawXml);
    }

    public static function fromXml(Testata $testata, DOMElement $data, string $rawXml): self
    {
        return new self($testata, $rawXml, Dom::dateTime(Dom::requiredText($data, 'timestamp_annullamento')));
    }
}
