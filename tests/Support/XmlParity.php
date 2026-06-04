<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

final class XmlParity
{
    /** Normaliza whitespace entre tags para comparar estructura XML. */
    public static function normalize(string $xml): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        return $dom->saveXML();
    }

    public static function assertSameStructure(string $expected, string $actual, \PHPUnit\Framework\TestCase $t): void
    {
        $t->assertSame(self::normalize($expected), self::normalize($actual));
    }
}
