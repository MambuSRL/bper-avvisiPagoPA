<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA;

use Mambu\BperPagoPA\Exception\InvalidArgumentException;

final class Config
{
    public const ENDPOINT_TEST = 'https://apigwinboundpagopa-coll.bper.it/IUVOnlineService/IUVOnlineService';

    /**
     * @param string                $codiceServizio      codice servizio (7 cifre) assegnato da BPS, usato in testata e informazioni_banca
     * @param string                $codiceSottoservizio codice sottoservizio (7 cifre) assegnato da BPS
     * @param string|null           $cliente             valore dell'header HTTP che identifica il cliente (errore 29 se assente)
     * @param string                $clienteHeader       nome dell'header HTTP del cliente (da concordare con BPER/BPS)
     * @param array<string, string> $headers             header HTTP aggiuntivi
     * @param bool                  $validateRequests    valida le richieste con lo schema XSD prima dell'invio
     * @param bool                  $validateResponses   valida gli esiti con lo schema XSD
     */
    public function __construct(
        public readonly string $codiceServizio,
        public readonly string $codiceSottoservizio,
        public readonly string $endpoint = self::ENDPOINT_TEST,
        public readonly ?string $cliente = null,
        public readonly string $clienteHeader = 'cliente',
        public readonly array $headers = [],
        public readonly bool $validateRequests = true,
        public readonly bool $validateResponses = true,
    ) {
        if (!preg_match('/^\d{7}$/', $codiceServizio)) {
            throw new InvalidArgumentException('codiceServizio deve essere composto da 7 cifre');
        }
        if (!preg_match('/^\d{7}$/', $codiceSottoservizio)) {
            throw new InvalidArgumentException('codiceSottoservizio deve essere composto da 7 cifre');
        }
        if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $endpoint)) {
            throw new InvalidArgumentException('endpoint non valido');
        }
        foreach ($headers as $name => $value) {
            if (!is_string($name) || preg_match('/[\r\n:]/', $name) || preg_match('/[\r\n]/', (string) $value)) {
                throw new InvalidArgumentException(sprintf('Header HTTP non valido: %s', (string) $name));
            }
        }
        if ($cliente !== null && preg_match('/[\r\n]/', $cliente . $clienteHeader)) {
            throw new InvalidArgumentException('Header cliente non valido');
        }
    }

    /**
     * @return array<string, string>
     */
    public function httpHeaders(): array
    {
        $headers = $this->headers;
        if ($this->cliente !== null) {
            $headers[$this->clienteHeader] = $this->cliente;
        }

        return $headers;
    }
}
