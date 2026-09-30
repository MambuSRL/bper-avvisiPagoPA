<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Xml;

use Mambu\BperPagoPA\Exception\InvalidArgumentException;

/**
 * Normalizzazione importi (in euro) nel formato xsd:decimal con 2 decimali.
 *
 * @internal
 */
final class Amount
{
    public static function normalize(string|int|float $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('Importo negativo non ammesso');
            }

            return $value . '.00';
        }

        if (is_float($value)) {
            if (!is_finite($value) || $value < 0) {
                throw new InvalidArgumentException(sprintf('Importo non valido: %s', $value));
            }
            if (abs(round($value * 100) - $value * 100) > 1e-6) {
                throw new InvalidArgumentException(sprintf('Importo con più di 2 decimali: %s', $value));
            }

            return number_format($value, 2, '.', '');
        }

        $value = trim($value);
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException(sprintf('Importo non valido (atteso formato 123.45): "%s"', $value));
        }
        [$int, $dec] = array_pad(explode('.', $value, 2), 2, '');

        return (ltrim($int, '0') ?: '0') . '.' . str_pad($dec, 2, '0');
    }

    public static function toCents(string $normalized): int
    {
        return (int) str_replace('.', '', $normalized);
    }
}
