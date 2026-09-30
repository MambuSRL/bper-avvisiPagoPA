<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DOMElement;
use Mambu\BperPagoPA\Enum\TipoIdentificativoUnivoco;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Debitore dell'avviso (IUVOnlineCreate).
 */
final class InformazioniDebitore
{
    /**
     * @param string                      $codiceDebitore codice identificativo univoco del debitore (CF / P.IVA)
     * @param string                      $anagrafica     nome e cognome o ragione sociale (max 70)
     * @param list<AltroRecapitoDebitore> $altriRecapiti
     */
    public function __construct(
        public readonly TipoIdentificativoUnivoco $tipoIdentificativoUnivoco,
        public readonly string $codiceDebitore,
        public readonly string $anagrafica,
        public readonly ?string $codiceFiscale = null,
        public readonly ?string $indirizzo = null,
        public readonly ?string $civico = null,
        public readonly ?string $cap = null,
        public readonly ?string $localita = null,
        public readonly ?string $provincia = null,
        public readonly ?string $nazione = null,
        public readonly ?string $email = null,
        public readonly ?string $pec = null,
        public readonly array $altriRecapiti = [],
    ) {
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'tipo_identificativo_univoco' => $this->tipoIdentificativoUnivoco->value,
            'codice_fiscale_debitore' => $this->codiceFiscale,
            'codice_debitore' => $this->codiceDebitore,
            'anagrafica_debitore' => $this->anagrafica,
            'indirizzo_debitore' => $this->indirizzo,
            'civico_debitore' => $this->civico,
            'cap_debitore' => $this->cap,
            'localita_debitore' => $this->localita,
            'provincia_debitore' => $this->provincia,
            'nazione_debitore' => $this->nazione,
            'email_debitore' => $this->email,
            'pec_debitore' => $this->pec,
        ]);
        foreach ($this->altriRecapiti as $recapito) {
            $recapito->appendTo(Dom::append($element, 'altro_recapito_debitore'));
        }
    }
}
