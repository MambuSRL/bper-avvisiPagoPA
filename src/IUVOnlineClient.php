<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA;

use DOMDocument;
use DOMElement;
use Mambu\BperPagoPA\Enum\StatoRT;
use Mambu\BperPagoPA\Exception\InvalidArgumentException;
use Mambu\BperPagoPA\Exception\InvalidResponseException;
use Mambu\BperPagoPA\Exception\ServiceFaultException;
use Mambu\BperPagoPA\Model\IdentificativoRT;
use Mambu\BperPagoPA\Model\InformazioniDebitore;
use Mambu\BperPagoPA\Model\InformazioniDebitoreModifica;
use Mambu\BperPagoPA\Model\InformazioniPagamento;
use Mambu\BperPagoPA\Model\InformazioniPagamentoModifica;
use Mambu\BperPagoPA\Model\Testata;
use Mambu\BperPagoPA\Response\AckRTResponse;
use Mambu\BperPagoPA\Response\CreateResponse;
use Mambu\BperPagoPA\Response\DeleteResponse;
use Mambu\BperPagoPA\Response\PdfResponse;
use Mambu\BperPagoPA\Response\RTListResponse;
use Mambu\BperPagoPA\Response\RTReadResponse;
use Mambu\BperPagoPA\Response\UpdateResponse;
use Mambu\BperPagoPA\Soap\SoapEnvelope;
use Mambu\BperPagoPA\Transport\CurlTransport;
use Mambu\BperPagoPA\Transport\TransportInterface;
use Mambu\BperPagoPA\Xml\Dom;
use Mambu\BperPagoPA\Xml\XsdValidator;

/**
 * Client del WS IUVOnlineService 1.4 (BPER Banca / BPS) per avvisi PagoPA e ricevute telematiche.
 *
 * Ogni metodo accetta un $idTransazione opzionale (max 25 caratteri): se omesso ne viene generato uno univoco.
 * Riutilizzare lo stesso id solo per ritrasmettere la medesima richiesta (errore 1 in caso contrario).
 */
final class IUVOnlineClient
{
    private const MAX_ID_TRANSAZIONE = 25;

    private readonly TransportInterface $transport;
    private readonly XsdValidator $validator;
    private ?string $lastRequest = null;
    private ?string $lastResponse = null;

    public function __construct(
        private readonly Config $config,
        ?TransportInterface $transport = null,
        ?XsdValidator $validator = null,
    ) {
        $this->transport = $transport ?? new CurlTransport();
        $this->validator = $validator ?? new XsdValidator();
    }

    /**
     * Generazione di uno o più avvisi (max 24 rate) per lo stesso debitore.
     *
     * @param list<InformazioniPagamento> $pagamenti il progressivo viene assegnato in base all'ordine (1..n)
     * @param string|null                 $numeroLista numero lista (13 cifre), opzionale
     */
    public function create(
        InformazioniDebitore $debitore,
        array $pagamenti,
        ?string $numeroLista = null,
        ?string $idTransazione = null,
    ): CreateResponse {
        $pagamenti = array_values($pagamenti);
        $this->assertPagamenti($pagamenti);

        [$testata, $data] = $this->call(
            Operation::CREATE,
            $idTransazione,
            function (DOMElement $data) use ($debitore, $pagamenti, $numeroLista): void {
                Dom::append($data, 'numero_disposizioni', (string) count($pagamenti));
                $this->appendInformazioniBanca($data, $numeroLista);
                $debitore->appendTo(Dom::append($data, 'informazioni_debitore'));
                foreach ($pagamenti as $i => $pagamento) {
                    $pagamento->appendTo(Dom::append($data, 'informazioni_pagamento'), $i + 1);
                }
            },
        );

        return CreateResponse::fromXml($testata, $data, (string) $this->lastResponse);
    }

    /**
     * Variazione di un avviso non ancora pagato e non rateizzato.
     */
    public function update(
        InformazioniPagamentoModifica $pagamento,
        ?InformazioniDebitoreModifica $debitore = null,
        ?string $numeroLista = null,
        ?string $idTransazione = null,
    ): UpdateResponse {
        [$testata, $data] = $this->call(
            Operation::UPDATE,
            $idTransazione,
            function (DOMElement $data) use ($pagamento, $debitore, $numeroLista): void {
                $this->appendInformazioniBanca($data, $numeroLista);
                $debitoreElement = Dom::append($data, 'informazioni_debitore_modifica');
                $debitore?->appendTo($debitoreElement);
                $pagamento->appendTo(Dom::append($data, 'informazioni_pagamento_modifica'));
            },
        );

        return UpdateResponse::fromXml($testata, $data, (string) $this->lastResponse);
    }

    /**
     * Annullo di un avviso a partire dal codice avviso (18 cifre).
     */
    public function delete(string $codiceAvviso, ?string $idTransazione = null): DeleteResponse
    {
        [$testata, $data] = $this->call(
            Operation::DELETE,
            $idTransazione,
            static fn (DOMElement $data) => Dom::append($data, 'codice_identificativo_bollettino', $codiceAvviso),
        );

        return DeleteResponse::fromXml($testata, $data, (string) $this->lastResponse);
    }

    /**
     * Elenco delle RT non ancora confermate (ultimi 30 giorni).
     *
     * @param int $numeroElementi numero massimo di RT in risposta: preferire valori contenuti e chiamate cicliche
     */
    public function getRTList(StatoRT $stato = StatoRT::ESEGUITO, int $numeroElementi = 50, ?string $idTransazione = null): RTListResponse
    {
        if ($numeroElementi < 1) {
            throw new InvalidArgumentException('numeroElementi deve essere maggiore di zero');
        }

        [$testata, $data] = $this->call(
            Operation::RT_LIST,
            $idTransazione,
            static function (DOMElement $data) use ($stato, $numeroElementi): void {
                Dom::appendAll($data, [
                    'stato' => $stato->value,
                    'numero_elementi_output' => (string) $numeroElementi,
                ]);
            },
        );

        return RTListResponse::fromXml($testata, $data, (string) $this->lastResponse);
    }

    /**
     * Recupero dell'XML della RT (IUV + CCP restituiti da getRTList).
     */
    public function getRTRead(IdentificativoRT $rt, ?string $idTransazione = null): RTReadResponse
    {
        [$testata, $data] = $this->call(
            Operation::RT_READ,
            $idTransazione,
            static fn (DOMElement $data) => $rt->appendTo(Dom::append($data, 'Identificativo_RT')),
        );

        return RTReadResponse::fromXml($testata, $data, (string) $this->lastResponse);
    }

    /**
     * Conferma di acquisizione della RT: non verrà più restituita da getRTList.
     */
    public function sendAckRT(IdentificativoRT $rt, ?string $idTransazione = null): AckRTResponse
    {
        [$testata, $data] = $this->call(
            Operation::ACK_RT,
            $idTransazione,
            static fn (DOMElement $data) => $rt->appendTo(Dom::append($data, 'Identificativo_RT')),
        );

        return AckRTResponse::fromXml($testata, $data, (string) $this->lastResponse);
    }

    /**
     * PDF dell'avviso di pagamento.
     */
    public function getAvvisoPdf(string $codiceAvviso, ?string $idTransazione = null): PdfResponse
    {
        [$testata, $data] = $this->call(
            Operation::AVVISO_PDF,
            $idTransazione,
            static fn (DOMElement $data) => Dom::append($data, 'codice_identificativo_bollettino', $codiceAvviso),
        );

        return PdfResponse::fromXml($testata, $data, 'PDF_bollettino', (string) $this->lastResponse);
    }

    /**
     * PDF della ricevuta di pagamento a partire dal codice avviso (18 cifre).
     */
    public function getRicevutaPagamentoPdfByCodiceAvviso(string $codiceAvviso, ?string $idTransazione = null): PdfResponse
    {
        return $this->ricevutaPdf('codice_identificativo_bollettino', $codiceAvviso, $idTransazione);
    }

    /**
     * PDF della ricevuta di pagamento a partire dallo IUV.
     */
    public function getRicevutaPagamentoPdfByIuv(string $iuv, ?string $idTransazione = null): PdfResponse
    {
        return $this->ricevutaPdf('codice_iuv', $iuv, $idTransazione);
    }

    /**
     * Ciclo completo di acquisizione RT: getRTList -> getRTRead -> $handler -> sendAckRT, a pagine
     * di $pageSize elementi finché la lista non è esaurita. Da schedulare almeno giornalmente.
     *
     * L'ACK viene inviato solo se $handler termina senza eccezioni; un'eccezione interrompe il ciclo
     * e la RT resterà disponibile alla chiamata successiva.
     *
     * @param callable(RTReadResponse): void $handler
     *
     * @return int numero di RT elaborate e confermate
     */
    public function processRT(
        callable $handler,
        StatoRT $stato = StatoRT::ESEGUITO,
        int $pageSize = 50,
        int $maxPages = 100,
    ): int {
        $processed = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $list = $this->getRTList($stato, $pageSize);

            foreach ($list->ricevute as $identificativo) {
                $handler($this->getRTRead($identificativo));

                try {
                    $this->sendAckRT($identificativo);
                } catch (ServiceFaultException $e) {
                    if (!$e->is(ErrorCode::ACK_GIA_CONFERMATO)) {
                        throw $e;
                    }
                }
                $processed++;
            }

            if (count($list->ricevute) < $pageSize) {
                break;
            }
        }

        return $processed;
    }

    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    private function ricevutaPdf(string $element, string $value, ?string $idTransazione): PdfResponse
    {
        [$testata, $data] = $this->call(
            Operation::RICEVUTA_PDF,
            $idTransazione,
            static fn (DOMElement $data) => Dom::append($data, $element, $value),
        );

        return PdfResponse::fromXml($testata, $data, 'avviso_pdf', (string) $this->lastResponse);
    }

    /**
     * @param callable(DOMElement): mixed $buildData
     *
     * @return array{0: Testata, 1: DOMElement}
     */
    private function call(Operation $operation, ?string $idTransazione, callable $buildData): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS(Dom::NS_IUV, 'iuv:' . $operation->requestElement());
        $document->appendChild($root);

        $this->testata($idTransazione)->appendTo(Dom::append($root, 'testata'));
        $buildData(Dom::append($root, $operation->requestDataElement()));

        if ($this->config->validateRequests) {
            $this->validator->validate($document);
        }

        $this->lastRequest = SoapEnvelope::wrap($document);
        $this->lastResponse = null;

        $headers = $this->config->httpHeaders() + [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '"' . $operation->soapAction() . '"',
        ];
        $response = $this->transport->send($this->config->endpoint, $this->lastRequest, $headers);
        $this->lastResponse = $response->body;

        $payload = SoapEnvelope::extractPayload($response);
        if ($payload->localName !== $operation->responseElement()) {
            throw new InvalidResponseException(sprintf(
                'Attesa risposta <%s>, ricevuto <%s>',
                $operation->responseElement(),
                $payload->localName,
            ));
        }

        if ($this->config->validateResponses) {
            $this->validator->validateElement($payload);
        }

        return [
            Testata::fromXml(Dom::requiredChild($payload, 'testata')),
            Dom::requiredChild($payload, $operation->responseDataElement()),
        ];
    }

    private function testata(?string $idTransazione): Testata
    {
        $idTransazione ??= self::generateIdTransazione();
        if ($idTransazione === '' || strlen($idTransazione) > self::MAX_ID_TRANSAZIONE) {
            throw new InvalidArgumentException(sprintf(
                'id_transazione non valido: deve essere valorizzato e lungo al massimo %d caratteri',
                self::MAX_ID_TRANSAZIONE,
            ));
        }

        return new Testata($idTransazione, $this->config->codiceServizio, $this->config->codiceSottoservizio);
    }

    /**
     * Id univoco di 24 caratteri: timestamp (yymmddHHiiss) + 12 caratteri esadecimali casuali.
     */
    public static function generateIdTransazione(): string
    {
        return date('ymdHis') . bin2hex(random_bytes(6));
    }

    private function appendInformazioniBanca(DOMElement $data, ?string $numeroLista): void
    {
        Dom::appendAll(Dom::append($data, 'informazioni_banca'), [
            'codice_servizio' => $this->config->codiceServizio,
            'codice_sottoservizio' => $this->config->codiceSottoservizio,
            'numero_lista' => $numeroLista,
        ]);
    }

    /**
     * Controlli preventivi sugli errori 9, 15 e 28.
     *
     * @param list<mixed> $pagamenti
     */
    private function assertPagamenti(array $pagamenti): void
    {
        $count = count($pagamenti);
        if ($count < 1 || $count > 24) {
            throw new InvalidArgumentException('Il numero di pagamenti deve essere compreso tra 1 e 24');
        }

        $codici = [];
        foreach ($pagamenti as $pagamento) {
            if (!$pagamento instanceof InformazioniPagamento) {
                throw new InvalidArgumentException('I pagamenti devono essere istanze di ' . InformazioniPagamento::class);
            }
            if ($pagamento->codiceIdentificativoBollettino !== null) {
                if (isset($codici[$pagamento->codiceIdentificativoBollettino])) {
                    throw new InvalidArgumentException(sprintf(
                        'codice_identificativo_bollettino duplicato nella richiesta: %s',
                        $pagamento->codiceIdentificativoBollettino,
                    ));
                }
                $codici[$pagamento->codiceIdentificativoBollettino] = true;
            }
        }

        $identificativiDebito = array_unique(array_map(
            static fn (InformazioniPagamento $p): string => (string) $p->identificativoDebito,
            $pagamenti,
        ));
        if (count($identificativiDebito) > 1) {
            throw new InvalidArgumentException(
                'identificativo_debito, se indicato, deve essere presente e uguale per tutti i pagamenti della richiesta',
            );
        }

        $lingue = array_unique(array_map(
            static fn (InformazioniPagamento $p): string => (string) $p->linguaAvviso?->value,
            $pagamenti,
        ));
        if (count($lingue) > 1) {
            throw new InvalidArgumentException('lingua_avviso deve essere uniforme in tutte le rate');
        }
    }
}
