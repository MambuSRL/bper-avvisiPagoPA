<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Xml\Dom;

final class CreateResponse extends AbstractResponse
{
    /**
     * @param list<IUVAcquisito> $esiti
     * @param string|null        $pdfBollettino PDF dell'avviso (binario)
     */
    public function __construct(
        Testata $testata,
        string $rawXml,
        public readonly int $numeroDisposizioni,
        public readonly array $esiti,
        public readonly ?string $pdfBollettino,
    ) {
        parent::__construct($testata, $rawXml);
    }

    public static function fromXml(Testata $testata, DOMElement $data, string $rawXml): self
    {
        return new self(
            $testata,
            $rawXml,
            (int) Dom::requiredText($data, 'numero_disposizioni'),
            array_map(
                static fn (DOMElement $esito): IUVAcquisito => IUVAcquisito::fromXml(Dom::requiredChild($esito, 'acquisito')),
                Dom::children($data, 'esito'),
            ),
            Dom::base64($data, 'PDF_bollettino'),
        );
    }

    public function esito(int $progressivo): ?IUVAcquisito
    {
        foreach ($this->esiti as $esito) {
            if ($esito->progressivoRichiesta === $progressivo) {
                return $esito;
            }
        }

        return null;
    }
}
