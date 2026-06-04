<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Generators\XmlGenerator;
use Teran\Sri\Exceptions\ValidationException;

class XmlGeneratorTest extends TestCase
{
    private function generator(): XmlGenerator
    {
        return new class extends XmlGenerator {
            public function generate(array $data): string
            {
                return $this->dom->saveXML();
            }

            public function makeElement(string $name, string $value): \DOMElement
            {
                return $this->createTextElement($name, $value);
            }
        };
    }

    public function test_valid_element_name_with_special_value_round_trips(): void
    {
        $el = $this->generator()->makeElement('razonSocial', 'J & M <Co>');

        $this->assertSame('razonSocial', $el->nodeName);
        $this->assertSame('J & M <Co>', $el->textContent);
    }

    public function test_invalid_element_name_throws_validation_exception_not_fatal(): void
    {
        // Una clave de usuario inválida (espacios, '!') haría que DOMDocument::createElement
        // lance un DOMException no capturado. Debe convertirse en ValidationException capturable.
        $this->expectException(ValidationException::class);

        $this->generator()->makeElement('campo invalido!', 'x');
    }
}
