<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Xml;

use DateTimeImmutable;
use DOMElement;
use Mambu\BperPagoPA\Exception\InvalidResponseException;

/**
 * Helper DOM per scrittura/lettura degli elementi (non qualificati) dello schema IUVOnline.
 *
 * @internal
 */
final class Dom
{
    public const NS_IUV = 'http://schema.iuvonline.nodospcit.ws.popso.it/v1';

    public static function append(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $doc = $parent->ownerDocument;
        $element = $doc->createElement($name);
        if ($value !== null) {
            $element->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Appende nell'ordine dato gli elementi valorizzati, ignorando i null.
     *
     * @param array<string, string|null> $values
     */
    public static function appendAll(DOMElement $parent, array $values): void
    {
        foreach ($values as $name => $value) {
            if ($value !== null) {
                self::append($parent, $name, $value);
            }
        }
    }

    public static function child(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                return $node;
            }
        }

        return null;
    }

    public static function requiredChild(DOMElement $parent, string $localName): DOMElement
    {
        return self::child($parent, $localName)
            ?? throw new InvalidResponseException(sprintf('Elemento <%s> mancante in <%s>', $localName, $parent->localName));
    }

    /**
     * @return list<DOMElement>
     */
    public static function children(DOMElement $parent, string $localName): array
    {
        $result = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                $result[] = $node;
            }
        }

        return $result;
    }

    public static function text(DOMElement $parent, string $localName): ?string
    {
        $child = self::child($parent, $localName);

        return $child === null ? null : trim($child->textContent);
    }

    public static function requiredText(DOMElement $parent, string $localName): string
    {
        return trim(self::requiredChild($parent, $localName)->textContent);
    }

    /**
     * Decodifica un elemento xsd:base64Binary restituendo il contenuto binario.
     */
    public static function base64(DOMElement $parent, string $localName): ?string
    {
        $value = self::text($parent, $localName);
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = base64_decode((string) preg_replace('/\s+/', '', $value), true);
        if ($decoded === false) {
            throw new InvalidResponseException(sprintf('Contenuto base64 non valido in <%s>', $localName));
        }

        return $decoded;
    }

    public static function dateTime(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new InvalidResponseException(sprintf('Data/ora non valida: "%s"', $value), 0, $e);
        }
    }
}
