<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use DOMElement;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Esito positivo di una disposizione: codice avviso generato e dati per la stampa.
 * I campi "immagine*" contengono il binario già decodificato.
 */
final class IUVAcquisito
{
    public function __construct(
        public readonly int $progressivoRichiesta,
        public readonly string $codiceIdentificativoBollettino,
        public readonly string $valoreQRCode,
        public readonly ?string $immagineQRCode = null,
        public readonly ?string $valoreCodiceBarre = null,
        public readonly ?string $immagineCodiceBarre = null,
        public readonly ?AvvisoPostale $avvisoPostale = null,
    ) {
    }

    public static function fromXml(DOMElement $acquisito): self
    {
        $avvisoPostale = Dom::child($acquisito, 'avviso_postale');

        return new self(
            (int) Dom::requiredText($acquisito, 'progressivo_richiesta'),
            Dom::requiredText($acquisito, 'codice_identificativo_bollettino'),
            Dom::requiredText($acquisito, 'valore_QR_code'),
            Dom::base64($acquisito, 'immagine_QR_code'),
            Dom::text($acquisito, 'valore_codice_barre'),
            Dom::base64($acquisito, 'immagine_codice_barre'),
            $avvisoPostale !== null ? AvvisoPostale::fromXml($avvisoPostale) : null,
        );
    }
}
