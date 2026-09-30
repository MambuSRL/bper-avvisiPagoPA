<?php

declare(strict_types=1);

namespace Mambu\BperPagoPA\Xml;

use DOMDocument;
use DOMElement;
use Mambu\BperPagoPA\Exception\XmlValidationException;

/**
 * Validazione di richieste ed esiti rispetto a IUVOnlineSchema_v1.4.xsd.
 */
final class XsdValidator
{
    private readonly string $schemaPath;

    public function __construct(?string $schemaPath = null)
    {
        $this->schemaPath = $schemaPath ?? self::defaultSchemaPath();
        if (!is_file($this->schemaPath)) {
            throw new \RuntimeException(sprintf('Schema XSD non trovato: %s', $this->schemaPath));
        }
    }

    public static function defaultSchemaPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/schema/IUVOnlineSchema_v1.4.xsd';
    }

    public function validate(DOMDocument $document): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $valid = $document->schemaValidate($this->schemaPath);
            $errors = array_map(
                static fn (\LibXMLError $e): string => sprintf('riga %d: %s', $e->line, trim($e->message)),
                libxml_get_errors(),
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$valid) {
            $root = $document->documentElement?->localName ?? '?';
            throw new XmlValidationException(sprintf('Documento <%s> non valido rispetto allo schema XSD', $root), $errors);
        }
    }

    /**
     * Valida un elemento (es. il payload estratto dal SOAP Body) come documento autonomo.
     */
    public function validateElement(DOMElement $element): void
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($element, true));

        $this->validate($document);
    }
}
