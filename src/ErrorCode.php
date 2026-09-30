<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA;

/**
 * Codici eccezione restituiti dal WS (IUVOnline 1.4 - ver. 04/10/23).
 */
enum ErrorCode: int
{
    case TESTATA_GIA_UTILIZZATA = 1;
    case CODICE_SERVIZIO_NON_PRESENTE = 2;
    case CODICE_SOTTOSERVIZIO_NON_PRESENTE = 3;
    case NUMERO_DISPOSIZIONI_NON_CONGRUENTE = 4;
    case IUV_GENERATO_DA_BPS = 5;
    case IUV_GENERATO_DA_ENTE = 6;
    case SOTTOSERVIZIO_NON_ABILITATO_CARICO = 7;
    case SOTTOSERVIZIO_NON_ABILITATO_CARICO_WS = 8;
    case CODICE_BOLLETTINO_DUPLICATO_RICHIESTA = 9;
    case CODICE_BOLLETTINO_GIA_UTILIZZATO = 10;
    case DATA_FINE_VALIDITA_ERRATA = 11;
    case DATA_SCADENZA_NON_VALIDA = 12;
    case PROGRESSIVO_NON_CORRETTO = 13;
    case IMPORTO_VOCI_NON_CONGRUENTE = 14;
    case IDENTIFICATIVO_DEBITO_NON_OMOGENEO = 15;
    case CODICE_BOLLETTINO_LUNGHEZZA_ERRATA = 16;
    case CODICE_BOLLETTINO_AUX_DIGIT_STAZIONE_ERRATI = 17;
    case ID_TRANSAZIONE_NON_VALIDO = 18;
    case TESTATA_INFORMAZIONI_BANCA_NON_CONGRUENTI = 19;
    case ANNO_RIFERIMENTO_NON_OMOGENEO = 20;
    case IMPORTO_SPEZZONE_CAUSALE_NON_CORRETTO = 21;
    case AVVISO_NON_TROVATO = 22;
    case AVVISO_CON_PAGAMENTO = 23;
    case AVVISO_GIA_ANNULLATO = 24;
    case RICEVUTA_NON_PRESENTE = 25;
    case AVVISO_ASSOCIATO_PIU_RATE = 26;
    case LINGUA_AVVISO_NON_VALIDA = 27;
    case LINGUA_AVVISO_NON_UNIFORME = 28;
    case CLIENTE_NON_VALIDO = 29;
    case CODICE_TASSONOMIA_ERRATO = 30;
    case RT_NON_PRESENTE = 31;
    case ACK_GIA_CONFERMATO = 32;
    case ERRORE_GENERAZIONE_PDF = 33;

    /**
     * Interpreta il campo "codice" del fault (es. "22", "022", "IUV-22").
     */
    public static function fromFaultCode(?string $codice): ?self
    {
        if ($codice === null || !preg_match('/(\d+)\s*$/', $codice, $m)) {
            return null;
        }

        return self::tryFrom((int) $m[1]);
    }

    public function description(): string
    {
        return match ($this) {
            self::TESTATA_GIA_UTILIZZATA => 'Testata già utilizzata',
            self::CODICE_SERVIZIO_NON_PRESENTE => 'Codice Servizio non presente',
            self::CODICE_SOTTOSERVIZIO_NON_PRESENTE => 'Codice Sottoservizio non presente',
            self::NUMERO_DISPOSIZIONI_NON_CONGRUENTE => 'Numero disposizioni non congruente',
            self::IUV_GENERATO_DA_BPS => 'Il codice identificativo bollettino viene generato da BPS. Non deve essere valorizzato in input',
            self::IUV_GENERATO_DA_ENTE => "Il codice identificativo bollettino deve essere generato dall'Ente. Deve essere valorizzato in input",
            self::SOTTOSERVIZIO_NON_ABILITATO_CARICO => 'Sottoservizio non abilitato al carico',
            self::SOTTOSERVIZIO_NON_ABILITATO_CARICO_WS => 'Sottoservizio non abilitato al carico mediante WS IUVOnline',
            self::CODICE_BOLLETTINO_DUPLICATO_RICHIESTA => 'codice_identificativo_bollettino duplicato nella richiesta',
            self::CODICE_BOLLETTINO_GIA_UTILIZZATO => 'codice_identificativo_bollettino già utilizzato',
            self::DATA_FINE_VALIDITA_ERRATA => 'Data fine validità errata',
            self::DATA_SCADENZA_NON_VALIDA => 'Data scadenza non valida',
            self::PROGRESSIVO_NON_CORRETTO => 'Il campo progressivo non è corretto, deve essere sequenziale da 1 a 24',
            self::IMPORTO_VOCI_NON_CONGRUENTE => 'Il campo importo e il totale degli importi delle voci non sono congruenti',
            self::IDENTIFICATIVO_DEBITO_NON_OMOGENEO => "Il campo identificativo_debito, se indicato, deve essere presente per tutti i pagamenti della richiesta ed essere omogeneo",
            self::CODICE_BOLLETTINO_LUNGHEZZA_ERRATA => 'codice_identificativo_bollettino non corretto deve essere formato da 18 cifre',
            self::CODICE_BOLLETTINO_AUX_DIGIT_STAZIONE_ERRATI => 'codice_identificativo_bollettino non corretto, aux digit e cod_stazione PA non corrispondenti alla configurazione',
            self::ID_TRANSAZIONE_NON_VALIDO => 'Id transazione non valido, deve essere al massimo 25 caratteri',
            self::TESTATA_INFORMAZIONI_BANCA_NON_CONGRUENTI => "Gli identificativi 'codice_servizio', 'codice_sottoservizio' nella sezione 'testata' devono essere uguali a quelli indicati nella sezione 'informazioni_banca'",
            self::ANNO_RIFERIMENTO_NON_OMOGENEO => 'Anno di riferimento non omogeneo',
            self::IMPORTO_SPEZZONE_CAUSALE_NON_CORRETTO => 'Importo dello spezzone causale strutturata non corretto',
            self::AVVISO_NON_TROVATO => 'Avviso di pagamento non trovato',
            self::AVVISO_CON_PAGAMENTO => 'Avviso di pagamento non modificabile/annullabile. È presente un pagamento',
            self::AVVISO_GIA_ANNULLATO => 'Avviso di pagamento annullato in precedenza',
            self::RICEVUTA_NON_PRESENTE => 'Ricevuta di pagamento non presente',
            self::AVVISO_ASSOCIATO_PIU_RATE => 'Avviso di pagamento associato a più rate. Non è possibile procedere con modifica/cancellazione',
            self::LINGUA_AVVISO_NON_VALIDA => 'Il campo lingua avviso non è valorizzato correttamente',
            self::LINGUA_AVVISO_NON_UNIFORME => 'Il campo lingua avviso deve essere uniforme in tutte le rate',
            self::CLIENTE_NON_VALIDO => 'Cliente non valido, deve essere valorizzato',
            self::CODICE_TASSONOMIA_ERRATO => 'Codice Tassonomia errato',
            self::RT_NON_PRESENTE => 'RT non presente',
            self::ACK_GIA_CONFERMATO => 'Ack download già confermato',
            self::ERRORE_GENERAZIONE_PDF => 'Errore generazione PDF',
        };
    }

    /**
     * @return list<Operation> operation per cui il WS può restituire l'eccezione
     */
    public function operations(): array
    {
        $all = Operation::cases();

        return match ($this) {
            self::TESTATA_GIA_UTILIZZATA => [Operation::CREATE, Operation::UPDATE, Operation::DELETE, Operation::ACK_RT],
            self::CODICE_SERVIZIO_NON_PRESENTE,
            self::CODICE_SOTTOSERVIZIO_NON_PRESENTE,
            self::ID_TRANSAZIONE_NON_VALIDO,
            self::CLIENTE_NON_VALIDO => $all,
            self::NUMERO_DISPOSIZIONI_NON_CONGRUENTE,
            self::IUV_GENERATO_DA_BPS,
            self::IUV_GENERATO_DA_ENTE,
            self::CODICE_BOLLETTINO_DUPLICATO_RICHIESTA,
            self::CODICE_BOLLETTINO_GIA_UTILIZZATO,
            self::PROGRESSIVO_NON_CORRETTO,
            self::IDENTIFICATIVO_DEBITO_NON_OMOGENEO,
            self::ANNO_RIFERIMENTO_NON_OMOGENEO,
            self::IMPORTO_SPEZZONE_CAUSALE_NON_CORRETTO,
            self::LINGUA_AVVISO_NON_UNIFORME,
            self::CODICE_TASSONOMIA_ERRATO => [Operation::CREATE],
            self::SOTTOSERVIZIO_NON_ABILITATO_CARICO,
            self::SOTTOSERVIZIO_NON_ABILITATO_CARICO_WS,
            self::CODICE_BOLLETTINO_LUNGHEZZA_ERRATA,
            self::CODICE_BOLLETTINO_AUX_DIGIT_STAZIONE_ERRATI => [Operation::CREATE, Operation::UPDATE, Operation::DELETE],
            self::DATA_FINE_VALIDITA_ERRATA,
            self::DATA_SCADENZA_NON_VALIDA,
            self::IMPORTO_VOCI_NON_CONGRUENTE,
            self::TESTATA_INFORMAZIONI_BANCA_NON_CONGRUENTI,
            self::LINGUA_AVVISO_NON_VALIDA => [Operation::CREATE, Operation::UPDATE],
            self::AVVISO_NON_TROVATO => [Operation::UPDATE, Operation::DELETE, Operation::AVVISO_PDF],
            self::AVVISO_CON_PAGAMENTO,
            self::AVVISO_ASSOCIATO_PIU_RATE => [Operation::UPDATE, Operation::DELETE],
            self::AVVISO_GIA_ANNULLATO => [Operation::DELETE],
            self::RICEVUTA_NON_PRESENTE => [Operation::RICEVUTA_PDF],
            self::RT_NON_PRESENTE => [Operation::RT_READ, Operation::ACK_RT],
            self::ACK_GIA_CONFERMATO => [Operation::ACK_RT],
            self::ERRORE_GENERAZIONE_PDF => [Operation::CREATE, Operation::UPDATE, Operation::AVVISO_PDF],
        };
    }
}
