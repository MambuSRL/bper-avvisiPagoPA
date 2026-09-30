<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Exception\InvalidResponseException;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * PDF dell'avviso (getAvvisoPdfRead) o della ricevuta di pagamento (getRicevutaPagamentoPdfRead).
 */
final class PdfResponse extends AbstractResponse
{
    /**
     * @param string $pdf contenuto binario del PDF
     */
    public function __construct(
        Testata $testata,
        string $rawXml,
        public readonly string $pdf,
    ) {
        parent::__construct($testata, $rawXml);
    }

    public static function fromXml(Testata $testata, DOMElement $data, string $element, string $rawXml): self
    {
        return new self(
            $testata,
            $rawXml,
            Dom::base64($data, $element) ?? throw new InvalidResponseException(sprintf('%s vuoto', $element)),
        );
    }
}
