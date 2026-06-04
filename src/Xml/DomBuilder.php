<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use DOMDocument;
use DOMElement;
use Teran\Sri\Exceptions\ValidationException;

/**
 * Crea nodos XML con su valor como nodo de texto (escapando &, <, >, ").
 * Los nombres de elemento aquí son siempre literales del esquema SRI.
 */
final class DomBuilder
{
    public function __construct(private readonly DOMDocument $dom)
    {
    }

    public function child(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        if ($value === '') {
            throw new \InvalidArgumentException(
                "DomBuilder::child(): cadena vacía para el elemento '$name'. Use null para un elemento vacío intencional, u omita el elemento si es opcional."
            );
        }
        try {
            $el = $this->dom->createElement($name);
        } catch (\DOMException $e) {
            throw new ValidationException("Nombre de elemento XML inválido: '$name'", [], 0, $e);
        }
        if ($value !== null) {
            $el->appendChild($this->dom->createTextNode($value));
        }
        $parent->appendChild($el);
        return $el;
    }
}
