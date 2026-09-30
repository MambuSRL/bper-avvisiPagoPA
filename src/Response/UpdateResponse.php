<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Xml\Dom;

final class UpdateResponse extends AbstractResponse
{
    public function __construct(
        Testata $testata,
        string $rawXml,
        public readonly IUVAcquisito $esito,
        public readonly ?string $pdfBollettino,
    ) {
        parent::__construct($testata, $rawXml);
    }

    public static function fromXml(Testata $testata, DOMElement $data, string $rawXml): self
    {
        return new self(
            $testata,
            $rawXml,
            IUVAcquisito::fromXml(Dom::requiredChild(Dom::requiredChild($data, 'esito'), 'acquisito')),
            Dom::base64($data, 'PDF_bollettino'),
        );
    }
}
