<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Xml\DomBuilder;
use DOMDocument;

class DomBuilderTest extends TestCase
{
    public function test_child_escapes_special_characters(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('root');
        $dom->appendChild($root);

        $builder = new DomBuilder($dom);
        $builder->child($root, 'razonSocial', 'J & M <Co>');

        $xml = $dom->saveXML();
        $reparsed = new DOMDocument();
        $this->assertTrue($reparsed->loadXML($xml));
        $this->assertSame('J & M <Co>', $reparsed->getElementsByTagName('razonSocial')->item(0)->textContent);
    }

    public function test_child_with_null_value_creates_empty_element(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('root');
        $dom->appendChild($root);

        $builder = new DomBuilder($dom);
        $node = $builder->child($root, 'vacio', null);

        $this->assertSame('vacio', $node->nodeName);
        $this->assertSame('', $node->textContent);
    }
}
