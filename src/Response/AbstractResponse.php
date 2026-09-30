<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Response;

use Mambu\BperPagoPA\Model\Testata;

abstract class AbstractResponse
{
    /**
     * @param string $rawXml busta SOAP di risposta, utile per log/archiviazione
     */
    public function __construct(
        public readonly Testata $testata,
        public readonly string $rawXml,
    ) {
    }
}
