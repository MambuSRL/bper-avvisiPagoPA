<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Tests;

use DateTimeImmutable;
use DOMDocument;
use Mambu\BperPagoPA\Config;
use Mambu\BperPagoPA\Enum\LinguaAvviso;
use Mambu\BperPagoPA\Enum\StatoRT;
use Mambu\BperPagoPA\Enum\TipoContabilita;
use Mambu\BperPagoPA\Enum\TipoIdentificativoUnivoco;
use Mambu\BperPagoPA\ErrorCode;
use Mambu\BperPagoPA\Exception\ApplicationFaultException;
use Mambu\BperPagoPA\Exception\InvalidArgumentException;
use Mambu\BperPagoPA\Exception\TransportException;
use Mambu\BperPagoPA\Exception\XmlValidationException;
use Mambu\BperPagoPA\IUVOnlineClient;
use Mambu\BperPagoPA\Model\CausaleRPT;
use Mambu\BperPagoPA\Model\DatiSpecificiRiscossione;
use Mambu\BperPagoPA\Model\IdentificativoRT;
use Mambu\BperPagoPA\Model\InformazioniDebitore;
use Mambu\BperPagoPA\Model\InformazioniDebitoreModifica;
use Mambu\BperPagoPA\Model\InformazioniPagamento;
use Mambu\BperPagoPA\Model\InformazioniPagamentoModifica;
use Mambu\BperPagoPA\Model\SpezzoneCausale;
use Mambu\BperPagoPA\Model\Voce;
use Mambu\BperPagoPA\Response\RTReadResponse;
use PHPUnit\Framework\TestCase;

final class IUVOnlineClientTest extends TestCase
{
    private const NS = 'http://schema.iuvonline.nodospcit.ws.popso.it/v1';

    private FakeTransport $transport;
    private IUVOnlineClient $client;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->client = new IUVOnlineClient(
            new Config('1234567', '7654321', cliente: 'ENTE01', clienteHeader: 'X-Cliente'),
            $this->transport,
        );
    }

    public function testCreateBuildsValidRequestAndParsesEsiti(): void
    {
        $pdf = base64_encode('%PDF-1.4 avviso');
        $qr = base64_encode('png');
        $this->transport->push(FakeTransport::envelope(<<<XML
            <ns:IUVOnlineCreateResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
              {$this->testataXml()}
              <IUVOnlineCreateResponseData>
                <numero_disposizioni>1</numero_disposizioni>
                <informazioni_banca><codice_servizio>1234567</codice_servizio><codice_sottoservizio>7654321</codice_sottoservizio></informazioni_banca>
                <informazioni_debitore><tipo_identificativo_univoco>F</tipo_identificativo_univoco><codice_debitore>RSSMRA80A01H501U</codice_debitore><anagrafica_debitore>Mario Rossi</anagrafica_debitore></informazioni_debitore>
                <informazioni_pagamento><progressivo>1</progressivo><importo>100.00</importo><scadenza>2026-12-31</scadenza><causale_bollettino>Retta</causale_bollettino><causale_RPT><causaleVersamento>Retta</causaleVersamento></causale_RPT></informazioni_pagamento>
                <esito><acquisito><progressivo_richiesta>1</progressivo_richiesta><valore_QR_code>PAGOPA|002|301000000000000001|00000000000|10000</valore_QR_code><immagine_QR_code>{$qr}</immagine_QR_code><codice_identificativo_bollettino>301000000000000001</codice_identificativo_bollettino></acquisito></esito>
                <PDF_bollettino>{$pdf}</PDF_bollettino>
              </IUVOnlineCreateResponseData>
            </ns:IUVOnlineCreateResponse>
            XML));

        $response = $this->client->create(
            new InformazioniDebitore(
                TipoIdentificativoUnivoco::PERSONA_FISICA,
                'RSSMRA80A01H501U',
                'Mario Rossi & C.',
                codiceFiscale: 'RSSMRA80A01H501U',
                email: 'mario.rossi@example.com',
            ),
            [
                new InformazioniPagamento(
                    '100.00',
                    new DateTimeImmutable('2026-12-31'),
                    'Retta scolastica',
                    CausaleRPT::spezzoni(SpezzoneCausale::strutturato('Retta', 60), SpezzoneCausale::strutturato('Mensa', '40')),
                    dettaglioVoci: [new Voce('RETTA', 60), new Voce('MENSA', 40.0)],
                    datiSpecificiRiscossione: new DatiSpecificiRiscossione(TipoContabilita::ALTRO, '9/0101100IM/'),
                    linguaAvviso: LinguaAvviso::IT,
                ),
            ],
            idTransazione: 'TX1',
        );

        self::assertSame(1, $response->numeroDisposizioni);
        self::assertSame('301000000000000001', $response->esito(1)?->codiceIdentificativoBollettino);
        self::assertSame('png', $response->esiti[0]->immagineQRCode);
        self::assertSame('%PDF-1.4 avviso', $response->pdfBollettino);
        self::assertSame('TX1', $response->testata->idTransazione);

        $request = $this->transport->requests[0];
        self::assertSame(Config::ENDPOINT_TEST, $request['endpoint']);
        self::assertSame('ENTE01', $request['headers']['X-Cliente']);
        self::assertSame('"http://scrittura.iuvonline.nodospcit.ws.popso.it/v1/IUVOnlineCreate"', $request['headers']['SOAPAction']);

        $doc = new DOMDocument();
        $doc->loadXML($request['body']);
        $root = $doc->getElementsByTagNameNS(self::NS, 'IUVOnlineCreateRequest')->item(0);
        self::assertNotNull($root);
        self::assertSame('1', $doc->getElementsByTagName('numero_disposizioni')->item(0)?->textContent);
        self::assertSame('1', $doc->getElementsByTagName('progressivo')->item(0)?->textContent);
        self::assertSame('Mario Rossi & C.', $doc->getElementsByTagName('anagrafica_debitore')->item(0)?->textContent);
        self::assertSame('60.00', $doc->getElementsByTagName('importo_voce')->item(0)?->textContent);
    }

    public function testFaultIsMappedToErrorCode(): void
    {
        $this->transport->push(FakeTransport::envelope(<<<XML
            <soapenv:Fault>
              <faultcode>soapenv:Server</faultcode>
              <faultstring>Errore applicativo</faultstring>
              <detail>
                <f:applicationFault xmlns:f="http://schema.fault.testata.common.ws.popso.it/v8">
                  <testata><t:testataTecnica xmlns:t="http://schema.testata.common.ws.popso.it/v8"><t:idConversazione>CONV1</t:idConversazione></t:testataTecnica></testata>
                  <codice>22</codice>
                  <messaggio>Avviso di pagamento non trovato</messaggio>
                  <layer>IUVOnline</layer>
                </f:applicationFault>
              </detail>
            </soapenv:Fault>
            XML), 500);

        try {
            $this->client->delete('301000000000000001');
            self::fail('Eccezione attesa');
        } catch (ApplicationFaultException $e) {
            self::assertSame(ErrorCode::AVVISO_NON_TROVATO, $e->errorCode);
            self::assertTrue($e->is(ErrorCode::AVVISO_NON_TROVATO));
            self::assertSame(22, $e->getCode());
            self::assertSame('CONV1', $e->idConversazione);
            self::assertSame('IUVOnline', $e->layer);
        }
    }

    public function testHttpErrorWithoutSoapBody(): void
    {
        $this->transport->push('<html><body>Unauthorized</body></html>', 401);

        try {
            $this->client->getAvvisoPdf('301000000000000001');
            self::fail('Eccezione attesa');
        } catch (TransportException $e) {
            self::assertSame(401, $e->httpStatus);
        }

        $this->transport->push('Service Unavailable', 503);
        $this->expectException(TransportException::class);
        $this->client->getAvvisoPdf('301000000000000001');
    }

    public function testRequestXsdValidationFails(): void
    {
        $this->expectException(XmlValidationException::class);
        $this->client->delete('123');
    }

    public function testUpdateAndDelete(): void
    {
        $this->transport->push(FakeTransport::envelope(<<<XML
            <ns:IUVOnlineUpdateResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
              {$this->testataXml()}
              <IUVOnlineUpdateResponseData>
                <informazioni_banca><codice_servizio>1234567</codice_servizio><codice_sottoservizio>7654321</codice_sottoservizio></informazioni_banca>
                <informazioni_debitore><tipo_identificativo_univoco>F</tipo_identificativo_univoco><codice_debitore>RSSMRA80A01H501U</codice_debitore><anagrafica_debitore>Mario Rossi</anagrafica_debitore></informazioni_debitore>
                <informazioni_pagamento_modifica><codice_identificativo_bollettino>301000000000000001</codice_identificativo_bollettino><importo>80.00</importo></informazioni_pagamento_modifica>
                <esito><acquisito><progressivo_richiesta>1</progressivo_richiesta><valore_QR_code>QR</valore_QR_code><codice_identificativo_bollettino>301000000000000001</codice_identificativo_bollettino></acquisito></esito>
              </IUVOnlineUpdateResponseData>
            </ns:IUVOnlineUpdateResponse>
            XML));
        $this->transport->push(FakeTransport::envelope(<<<XML
            <ns:IUVOnlineDeleteResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
              {$this->testataXml()}
              <IUVOnlineDeleteResponseData><timestamp_annullamento>2026-09-30T10:15:00+02:00</timestamp_annullamento></IUVOnlineDeleteResponseData>
            </ns:IUVOnlineDeleteResponse>
            XML));

        $update = $this->client->update(
            new InformazioniPagamentoModifica('301000000000000001', 80, new DateTimeImmutable('2026-12-31')),
            new InformazioniDebitoreModifica(email: 'nuova@example.com'),
        );
        self::assertSame('301000000000000001', $update->esito->codiceIdentificativoBollettino);
        self::assertNull($update->pdfBollettino);

        $delete = $this->client->delete('301000000000000001');
        self::assertSame('2026-09-30 10:15:00', $delete->timestampAnnullamento->format('Y-m-d H:i:s'));
    }

    public function testProcessRT(): void
    {
        $rtXml = base64_encode('<RT>ok</RT>');
        $identificativo = '<identificativo_univoco_versamento>01000000000000001</identificativo_univoco_versamento>'
            . '<codice_contesto_pagamento>CCP1</codice_contesto_pagamento>';

        $this->transport
            ->push(FakeTransport::envelope(<<<XML
                <ns:getRTListResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
                  {$this->testataXml()}
                  <getRTListResponseData><totale_RT>1</totale_RT><listaRT><identificativoRT>{$identificativo}</identificativoRT></listaRT></getRTListResponseData>
                </ns:getRTListResponse>
                XML))
            ->push(FakeTransport::envelope(<<<XML
                <ns:getRTReadResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
                  {$this->testataXml()}
                  <getRTReadResponseData><Identificativo_RT>{$identificativo}</Identificativo_RT><xml_RT>{$rtXml}</xml_RT></getRTReadResponseData>
                </ns:getRTReadResponse>
                XML))
            ->push(FakeTransport::envelope(<<<XML
                <ns:sendAckRTUpdateResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
                  {$this->testataXml()}
                  <sendAckRTUpdateResponseData><timestamp_ack>2026-09-30T10:15:00</timestamp_ack></sendAckRTUpdateResponseData>
                </ns:sendAckRTUpdateResponse>
                XML));

        $received = [];
        $count = $this->client->processRT(function (RTReadResponse $rt) use (&$received): void {
            $received[] = $rt;
        }, StatoRT::ESEGUITO, 10);

        self::assertSame(1, $count);
        self::assertSame('<RT>ok</RT>', $received[0]->xmlRT);
        self::assertSame('CCP1', $received[0]->identificativoRT->ccp);
        self::assertCount(3, $this->transport->requests);
        self::assertStringContainsString('<stato>ESEGUITO</stato>', $this->transport->requests[0]['body']);
        self::assertStringContainsString('sendAckRTUpdateRequest', $this->transport->requests[2]['body']);
    }

    public function testRicevutaPdfByIuv(): void
    {
        $pdf = base64_encode('%PDF ricevuta');
        $this->transport->push(FakeTransport::envelope(<<<XML
            <ns:getRicevutaPagamentoPdfReadResponse xmlns:ns="http://schema.iuvonline.nodospcit.ws.popso.it/v1">
              {$this->testataXml()}
              <getRicevutaPagamentoPdfReadResponseData><avviso_pdf>{$pdf}</avviso_pdf></getRicevutaPagamentoPdfReadResponseData>
            </ns:getRicevutaPagamentoPdfReadResponse>
            XML));

        $response = $this->client->getRicevutaPagamentoPdfByIuv('01000000000000001');

        self::assertSame('%PDF ricevuta', $response->pdf);
        self::assertStringContainsString('<codice_iuv>01000000000000001</codice_iuv>', $this->transport->requests[0]['body']);
    }

    public function testPreValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InformazioniPagamento('100.00', new DateTimeImmutable(), 'x', CausaleRPT::testo('x'), dettaglioVoci: [new Voce('A', '50')]);
    }

    public function testLinguaNonUniforme(): void
    {
        $p = static fn (?LinguaAvviso $l) => new InformazioniPagamento(10, new DateTimeImmutable(), 'x', CausaleRPT::testo('x'), linguaAvviso: $l);

        $this->expectException(InvalidArgumentException::class);
        $this->client->create(
            new InformazioniDebitore(TipoIdentificativoUnivoco::PERSONA_FISICA, 'X', 'Y'),
            [$p(LinguaAvviso::IT), $p(LinguaAvviso::EN)],
        );
    }

    public function testIdTransazioneTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->getRTRead(new IdentificativoRT('1', '2'), str_repeat('A', 26));
    }

    public function testGeneratedIdTransazione(): void
    {
        self::assertMatchesRegularExpression('/^\d{12}[0-9a-f]{12}$/', IUVOnlineClient::generateIdTransazione());
    }

    private function testataXml(): string
    {
        return FakeTransport::testata();
    }
}
