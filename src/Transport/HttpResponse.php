<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Transport;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
    }
}
