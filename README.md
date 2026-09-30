# bper-avvisiPagoPA

Libreria PHP (>= 8.3) per il WS **IUVOnlineService 1.4** di BPER Banca / BPS: generazione, variazione, annullo e recupero PDF degli avvisi PagoPA e acquisizione delle ricevute telematiche (RT).

Le richieste e gli esiti vengono validati con gli schemi XSD ufficiali inclusi in [resources/schema](resources/schema) (WSDL in [resources/interfaces](resources/interfaces)).

## Installazione

```bash
composer require mambusrl/bper-avvisi-pagopa
```

Richiede le estensioni `dom`, `libxml`, `curl`.

## Configurazione

```php
use Mambu\BperPagoPA\Config;
use Mambu\BperPagoPA\IUVOnlineClient;
use Mambu\BperPagoPA\Transport\CurlTransport;

$client = new IUVOnlineClient(
    new Config(
        codiceServizio: '1234567',
        codiceSottoservizio: '7654321',
        endpoint: Config::ENDPOINT_TEST,   // https://apigwinboundpagopa-coll.bper.it/IUVOnlineService/IUVOnlineService
        cliente: 'CODICE_CLIENTE',         // header richiesto dal WS (errore 29 se assente)
        clienteHeader: 'cliente',          // nome header da concordare con BPER/BPS
    ),
    new CurlTransport([
        'timeout' => 60,
        // mTLS / Basic auth se richiesti dal gateway:
        // 'ssl_cert' => '/path/cert.pem', 'ssl_key' => '/path/key.pem', 'ssl_key_password' => '...',
        // 'username' => '...', 'password' => '...',
    ]),
);
```

`Config` accetta anche `headers` (header HTTP aggiuntivi) e i flag `validateRequests` / `validateResponses` (default `true`).

## Avvisi di pagamento

```php
use Mambu\BperPagoPA\Enum\{LinguaAvviso, TipoContabilita, TipoIdentificativoUnivoco};
use Mambu\BperPagoPA\Model\{CausaleRPT, DatiSpecificiRiscossione, InformazioniDebitore, InformazioniPagamento, InformazioniPagamentoModifica, Voce};

// Generazione (1..24 rate; il progressivo è assegnato automaticamente)
$esito = $client->create(
    new InformazioniDebitore(TipoIdentificativoUnivoco::PERSONA_FISICA, 'RSSMRA80A01H501U', 'Mario Rossi',
        codiceFiscale: 'RSSMRA80A01H501U', email: 'mario.rossi@example.com'),
    [
        new InformazioniPagamento(
            importo: '100.00',
            scadenza: new DateTimeImmutable('2026-12-31'),
            causaleBollettino: 'Retta scolastica dicembre',
            causaleRPT: CausaleRPT::testo('Retta scolastica dicembre'),
            dettaglioVoci: [new Voce('RETTA', '60.00'), new Voce('MENSA', '40.00')],
            datiSpecificiRiscossione: new DatiSpecificiRiscossione(TipoContabilita::ALTRO, '9/0101100IM/'),
            linguaAvviso: LinguaAvviso::IT,
        ),
    ],
);
$codiceAvviso = $esito->esiti[0]->codiceIdentificativoBollettino;
file_put_contents('avviso.pdf', $esito->pdfBollettino);

// Variazione (solo i campi valorizzati)
$client->update(new InformazioniPagamentoModifica($codiceAvviso, importo: '80.00'));

// PDF avviso / annullo
$pdf = $client->getAvvisoPdf($codiceAvviso)->pdf;
$client->delete($codiceAvviso);
```

### Variazione di un avviso

`update()` invia solo i campi valorizzati di `InformazioniPagamentoModifica` e (opzionalmente) di `InformazioniDebitoreModifica`.
Può essere applicata solo agli avvisi non pagati (errore 23) e non associati a più rate (errore 26).

```php
use Mambu\BperPagoPA\Enum\{LinguaAvviso, TipoIdentificativoUnivoco};
use Mambu\BperPagoPA\ErrorCode;
use Mambu\BperPagoPA\Exception\ServiceFaultException;
use Mambu\BperPagoPA\Model\{AltroRecapitoDebitore, CausaleRPT, InformazioniDebitoreModifica, InformazioniPagamentoModifica, SpezzoneCausale, Voce};

// 1. Nuovo importo e nuova scadenza, con salvataggio del PDF aggiornato
$risposta = $client->update(new InformazioniPagamentoModifica(
    codiceIdentificativoBollettino: $codiceAvviso,
    importo: '120.00',
    scadenza: new DateTimeImmutable('2027-01-31'),
));
file_put_contents('avviso-aggiornato.pdf', $risposta->pdfBollettino);
$qrCode = $risposta->esito->valoreQRCode;

// 2. Importo ripartito in voci (la somma delle voci deve coincidere con l'importo)
$client->update(new InformazioniPagamentoModifica(
    codiceIdentificativoBollettino: $codiceAvviso,
    importo: '150.00',
    dettaglioVoci: [new Voce('RETTA', '100.00'), new Voce('MENSA', '50.00')],
));

// 3. Causali, periodo di validità e lingua dell'avviso
$client->update(new InformazioniPagamentoModifica(
    codiceIdentificativoBollettino: $codiceAvviso,
    causaleBollettino: 'Retta scolastica gennaio',
    causaleRPT: CausaleRPT::spezzoni(
        SpezzoneCausale::strutturato('Retta gennaio', '100.00'),
        SpezzoneCausale::strutturato('Mensa gennaio', '50.00'),
    ),
    dataInizioValidita: new DateTimeImmutable('2027-01-01'),
    dataFineValidita: new DateTimeImmutable('2027-02-28'),
    linguaAvviso: LinguaAvviso::EN,
));

// 4. Solo i dati del debitore (dati del pagamento invariati)
$client->update(
    new InformazioniPagamentoModifica($codiceAvviso),
    new InformazioniDebitoreModifica(
        indirizzo: 'Via Roma',
        civico: '10',
        cap: '41121',
        localita: 'Modena',
        provincia: 'MO',
        nazione: 'IT',
        email: 'mario.rossi@example.com',
        altriRecapiti: [new AltroRecapitoDebitore('CELLULARE', '+393331234567')],
    ),
);

// 5. Intestazione dell'avviso a un altro soggetto
$client->update(
    new InformazioniPagamentoModifica($codiceAvviso),
    new InformazioniDebitoreModifica(
        tipoIdentificativoUnivoco: TipoIdentificativoUnivoco::PERSONA_GIURIDICA,
        codiceDebitore: '01234567890',
        anagrafica: 'Rossi S.r.l.',
        codiceFiscale: '01234567890',
        pec: 'rossi.srl@pec.example.com',
    ),
);

// 6. Gestione degli errori tipici e ritrasmissione idempotente
$idTransazione = IUVOnlineClient::generateIdTransazione();
$modifica = new InformazioniPagamentoModifica($codiceAvviso, importo: '90.00');
try {
    $client->update($modifica, idTransazione: $idTransazione);
} catch (ServiceFaultException $e) {
    match ($e->errorCode) {
        ErrorCode::AVVISO_NON_TROVATO => /* codice avviso inesistente per il sottoservizio */ null,
        ErrorCode::AVVISO_CON_PAGAMENTO => /* pagamento in corso o eseguito: non modificabile */ null,
        ErrorCode::AVVISO_ASSOCIATO_PIU_RATE => /* avviso rateizzato: non modificabile */ null,
        ErrorCode::DATA_SCADENZA_NON_VALIDA => /* scadenza nel passato o fuori dal periodo di validità */ null,
        default => throw $e,
    };
} catch (\Mambu\BperPagoPA\Exception\TransportException) {
    // riusare lo stesso id_transazione solo con la stessa identica richiesta (errore 1 altrimenti)
    $client->update($modifica, idTransazione: $idTransazione);
}
```

## Ricevute telematiche (RT)

Le RT sono disponibili per 30 giorni: schedulare l'acquisizione almeno una volta al giorno. L'ACK è obbligatorio, altrimenti la RT continua a essere restituita.

```php
use Mambu\BperPagoPA\Enum\StatoRT;
use Mambu\BperPagoPA\Response\RTReadResponse;

// Ciclo completo: getRTList -> getRTRead -> handler -> sendAckRTUpdate (a pagine)
$n = $client->processRT(function (RTReadResponse $rt): void {
    // salvare $rt->xmlRT; se viene lanciata un'eccezione l'ACK non è inviato
}, StatoRT::ESEGUITO, pageSize: 50);

// Oppure le singole operation
$lista = $client->getRTList(StatoRT::ESEGUITO, 50);
foreach ($lista->ricevute as $id) {
    $xml = $client->getRTRead($id)->xmlRT;
    $client->sendAckRT($id);
}

// PDF della ricevuta di pagamento
$client->getRicevutaPagamentoPdfByCodiceAvviso($codiceAvviso);
$client->getRicevutaPagamentoPdfByIuv($iuv);
```

## Gestione errori

Tutte le eccezioni implementano `Mambu\BperPagoPA\Exception\BperPagoPAException`.

| Eccezione | Causa |
| :--- | :--- |
| `InvalidArgumentException` | controlli preventivi (importo/voci, date validità, lingua e identificativo debito non omogenei, id transazione > 25 caratteri, ...) |
| `XmlValidationException` | richiesta o esito non conforme agli XSD (`$e->errors`) |
| `ApplicationFaultException`, `InputFaultException`, `SystemFaultException`, `DatiTestataFaultException`, `ServizioNonDisponibileFaultException` | fault del WS (estendono `ServiceFaultException`) |
| `TransportException` | errore di rete o HTTP senza busta SOAP |
| `InvalidResponseException` | risposta non interpretabile |

Il codice del fault è mappato sull'enum `ErrorCode` (1..33, IUVOnline 1.4 ver. 04/10/23):

```php
use Mambu\BperPagoPA\ErrorCode;
use Mambu\BperPagoPA\Exception\ServiceFaultException;

try {
    $client->delete($codiceAvviso);
} catch (ServiceFaultException $e) {
    if ($e->is(ErrorCode::AVVISO_GIA_ANNULLATO)) { /* ... */ }
    echo $e->errorCode?->description();
}
```

Per il debug sono disponibili `$client->getLastRequest()` / `getLastResponse()` e la proprietà `rawXml` di ogni risposta.

## Test

```bash
composer install && vendor/bin/phpunit
```
