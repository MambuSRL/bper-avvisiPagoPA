<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Soap;

use DOMDocument;
use DOMElement;
use Mambu\BperPagoPA\Exception\InvalidResponseException;
use Mambu\BperPagoPA\Exception\ServiceFaultException;
use Mambu\BperPagoPA\Exception\SoapFaultException;
use Mambu\BperPagoPA\Exception\TransportException;
use Mambu\BperPagoPA\Transport\HttpResponse;
use Mambu\BperPagoPA\Xml\Dom;

/**
 * Busta SOAP 1.1 (document/literal).
 *
 * @internal
 */
final class SoapEnvelope
{
    public const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';

    public static function wrap(DOMDocument $payload): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $envelope = $doc->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $doc->appendChild($envelope);
        $envelope->appendChild($doc->createElementNS(self::NS_SOAP, 'soapenv:Header'));
        $body = $doc->createElementNS(self::NS_SOAP, 'soapenv:Body');
        $envelope->appendChild($body);
        $body->appendChild($doc->importNode($payload->documentElement, true));

        return (string) $doc->saveXML();
    }

    /**
     * Restituisce il primo elemento del SOAP Body, lanciando l'eccezione opportuna in caso di Fault.
     *
     * @throws SoapFaultException|TransportException|InvalidResponseException
     */
    public static function extractPayload(HttpResponse $response): DOMElement
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = trim($response->body) !== '' && $doc->loadXML($response->body, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $loaded ? $doc->documentElement : null;
        $body = $root !== null && $root->localName === 'Envelope' ? Dom::child($root, 'Body') : null;

        if ($body === null) {
            if ($response->statusCode >= 400) {
                throw new TransportException(
                    sprintf('HTTP %d: %s', $response->statusCode, substr(trim(strip_tags($response->body)), 0, 500)),
                    $response->statusCode,
                );
            }
            throw new InvalidResponseException($loaded ? 'Busta SOAP non valida: Body mancante' : 'Risposta non in formato XML');
        }

        $payload = null;
        foreach ($body->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $payload = $node;
                break;
            }
        }

        if ($payload === null) {
            throw new InvalidResponseException('SOAP Body vuoto');
        }
        if ($payload->localName === 'Fault') {
            throw self::fault($payload);
        }
        if ($response->statusCode >= 400) {
            throw new TransportException(sprintf('HTTP %d', $response->statusCode), $response->statusCode);
        }

        return $payload;
    }

    private static function fault(DOMElement $fault): SoapFaultException
    {
        $faultCode = Dom::text($fault, 'faultcode') ?? '';
        $faultString = Dom::text($fault, 'faultstring') ?? '';

        $detail = Dom::child($fault, 'detail');
        $detailElement = null;
        if ($detail !== null) {
            foreach ($detail->childNodes as $node) {
                if ($node instanceof DOMElement) {
                    $detailElement = $node;
                    break;
                }
            }
        }

        if ($detailElement === null || Dom::child($detailElement, 'codice') === null) {
            return new SoapFaultException($faultCode, $faultString);
        }

        $testataTecnica = Dom::child($detailElement, 'testata');
        $testataTecnica = $testataTecnica !== null ? Dom::child($testataTecnica, 'testataTecnica') : null;

        return ServiceFaultException::create(
            $detailElement->localName,
            $faultCode,
            $faultString,
            Dom::text($detailElement, 'codice'),
            Dom::text($detailElement, 'messaggio'),
            Dom::text($detailElement, 'layer'),
            $testataTecnica !== null ? Dom::text($testataTecnica, 'idConversazione') : null,
        );
    }
}
