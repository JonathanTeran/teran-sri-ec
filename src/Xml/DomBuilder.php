<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use DOMDocument;
use DOMElement;

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
        $el = $this->dom->createElement($name);
        if ($value !== null && $value !== '') {
            $el->appendChild($this->dom->createTextNode($value));
        }
        $parent->appendChild($el);
        return $el;
    }
}
