<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA;

/**
 * Operation esposte dal WSDL IUVOnlineService 1.4.
 */
enum Operation: string
{
    case CREATE = 'IUVOnlineCreate';
    case UPDATE = 'IUVOnlineUpdate';
    case DELETE = 'IUVOnlineDelete';
    case RT_LIST = 'getRTList';
    case RT_READ = 'getRTRead';
    case ACK_RT = 'sendAckRTUpdate';
    case AVVISO_PDF = 'getAvvisoPdfRead';
    case RICEVUTA_PDF = 'getRicevutaPagamentoPdfRead';

    public const SOAP_ACTION_BASE = 'http://scrittura.iuvonline.nodospcit.ws.popso.it/v1/';

    public function soapAction(): string
    {
        return self::SOAP_ACTION_BASE . $this->value;
    }

    public function requestElement(): string
    {
        return $this->value . 'Request';
    }

    public function requestDataElement(): string
    {
        return $this->value . 'RequestData';
    }

    public function responseElement(): string
    {
        return $this->value . 'Response';
    }

    public function responseDataElement(): string
    {
        return $this->value . 'ResponseData';
    }
}
