<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Model;

use DateTimeInterface;
use DOMElement;
use Mambu\BperPagoPA\Enum\LinguaAvviso;
use Mambu\BperPagoPA\Exception\InvalidArgumentException;
use Mambu\BperPagoPA\Xml\Amount;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Singola disposizione/rata da emettere (IUVOnlineCreate). Il progressivo è assegnato dal client
 * in base alla posizione nell'elenco (1..24).
 */
final class InformazioniPagamento
{
    public readonly string $importo;

    /**
     * @param string|int|float  $importo                        importo in euro (es. "123.45")
     * @param string|null       $codiceIdentificativoBollettino codice avviso/IUV: solo se generato dall'Ente
     * @param string|null       $identificativoDebito           raggruppa le rate: se usato, uguale per tutte le rate
     * @param list<Voce>        $dettaglioVoci                  max 10 voci, la somma deve corrispondere all'importo
     */
    public function __construct(
        string|int|float $importo,
        public readonly DateTimeInterface $scadenza,
        public readonly string $causaleBollettino,
        public readonly CausaleRPT $causaleRPT,
        public readonly ?string $codiceIdentificativoBollettino = null,
        public readonly ?string $identificativoDebito = null,
        public readonly array $dettaglioVoci = [],
        public readonly ?DateTimeInterface $dataInizioValidita = null,
        public readonly ?DateTimeInterface $dataFineValidita = null,
        public readonly ?string $annoRiferimento = null,
        public readonly ?string $identificativoDisposizione = null,
        public readonly ?DatiSpecificiRiscossione $datiSpecificiRiscossione = null,
        public readonly ?LinguaAvviso $linguaAvviso = null,
    ) {
        $this->importo = Amount::normalize($importo);
        self::assertCoerenza($this->importo, $dettaglioVoci, $dataInizioValidita, $dataFineValidita);
    }

    public function appendTo(DOMElement $element, int $progressivo): void
    {
        Dom::appendAll($element, [
            'progressivo' => (string) $progressivo,
            'codice_identificativo_bollettino' => $this->codiceIdentificativoBollettino,
            'identificativo_debito' => $this->identificativoDebito,
            'importo' => $this->importo,
        ]);
        foreach ($this->dettaglioVoci as $voce) {
            $voce->appendTo(Dom::append($element, 'dettaglio_voci'));
        }
        Dom::appendAll($element, [
            'scadenza' => $this->scadenza->format('Y-m-d'),
            'data_inizio_validita' => $this->dataInizioValidita?->format('Y-m-d'),
            'data_fine_validita' => $this->dataFineValidita?->format('Y-m-d'),
            'anno_riferimento' => $this->annoRiferimento,
            'identificativo_disposizione' => $this->identificativoDisposizione,
            'causale_bollettino' => $this->causaleBollettino,
        ]);
        $this->datiSpecificiRiscossione?->appendTo(Dom::append($element, 'dati_specifici_riscossione'));
        $this->causaleRPT->appendTo(Dom::append($element, 'causale_RPT'));
        Dom::appendAll($element, ['lingua_avviso' => $this->linguaAvviso?->value]);
    }

    /**
     * Controlli preventivi sugli errori 11 (date validità) e 14 (importo/voci).
     *
     * @internal
     *
     * @param list<Voce> $voci
     */
    public static function assertCoerenza(
        ?string $importo,
        array $voci,
        ?DateTimeInterface $dataInizio,
        ?DateTimeInterface $dataFine,
    ): void {
        if (count($voci) > 10) {
            throw new InvalidArgumentException('Sono ammesse al massimo 10 voci di dettaglio');
        }
        foreach ($voci as $voce) {
            if (!$voce instanceof Voce) {
                throw new InvalidArgumentException('dettaglioVoci deve contenere istanze di ' . Voce::class);
            }
        }
        if ($importo !== null && $voci !== []) {
            $totale = array_sum(array_map(static fn (Voce $v): int => Amount::toCents($v->importo), $voci));
            if ($totale !== Amount::toCents($importo)) {
                throw new InvalidArgumentException(sprintf(
                    'La somma delle voci (%s) non corrisponde all\'importo (%s)',
                    number_format($totale / 100, 2, '.', ''),
                    $importo,
                ));
            }
        }
        if ($dataInizio !== null && $dataFine !== null && $dataFine->format('Y-m-d') < $dataInizio->format('Y-m-d')) {
            throw new InvalidArgumentException('La data fine validità non può essere precedente alla data inizio validità');
        }
    }
}
