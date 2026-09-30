<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DateTimeInterface;
use DOMElement;
use Mambu\BperPagoPA\Enum\LinguaAvviso;
use Mambu\BperPagoPA\Xml\Amount;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Dati dell'avviso da variare (IUVOnlineUpdate): vengono inviati solo i campi valorizzati.
 */
final class InformazioniPagamentoModifica
{
    public readonly ?string $importo;

    /**
     * @param string     $codiceIdentificativoBollettino codice avviso da modificare
     * @param list<Voce> $dettaglioVoci
     */
    public function __construct(
        public readonly string $codiceIdentificativoBollettino,
        string|int|float|null $importo = null,
        public readonly ?DateTimeInterface $scadenza = null,
        public readonly ?string $causaleBollettino = null,
        public readonly ?CausaleRPT $causaleRPT = null,
        public readonly ?string $identificativoDebito = null,
        public readonly array $dettaglioVoci = [],
        public readonly ?DateTimeInterface $dataInizioValidita = null,
        public readonly ?DateTimeInterface $dataFineValidita = null,
        public readonly ?string $annoRiferimento = null,
        public readonly ?string $identificativoDisposizione = null,
        public readonly ?DatiSpecificiRiscossione $datiSpecificiRiscossione = null,
        public readonly ?LinguaAvviso $linguaAvviso = null,
    ) {
        $this->importo = $importo === null ? null : Amount::normalize($importo);
        InformazioniPagamento::assertCoerenza($this->importo, $dettaglioVoci, $dataInizioValidita, $dataFineValidita);
    }

    public function appendTo(DOMElement $element): void
    {
        Dom::appendAll($element, [
            'codice_identificativo_bollettino' => $this->codiceIdentificativoBollettino,
            'identificativo_debito' => $this->identificativoDebito,
            'importo' => $this->importo,
        ]);
        foreach ($this->dettaglioVoci as $voce) {
            $voce->appendTo(Dom::append($element, 'dettaglio_voci'));
        }
        Dom::appendAll($element, [
            'scadenza' => $this->scadenza?->format('Y-m-d'),
            'data_inizio_validita' => $this->dataInizioValidita?->format('Y-m-d'),
            'data_fine_validita' => $this->dataFineValidita?->format('Y-m-d'),
            'anno_riferimento' => $this->annoRiferimento,
            'identificativo_disposizione' => $this->identificativoDisposizione,
            'causale_bollettino' => $this->causaleBollettino,
        ]);
        $this->datiSpecificiRiscossione?->appendTo(Dom::append($element, 'dati_specifici_riscossione'));
        $this->causaleRPT?->appendTo(Dom::append($element, 'causale_RPT'));
        Dom::appendAll($element, ['lingua_avviso' => $this->linguaAvviso?->value]);
    }
}
