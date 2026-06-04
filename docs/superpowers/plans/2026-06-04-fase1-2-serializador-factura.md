# Fase 1.2 — Serializador XML de la Factura Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serializar el DTO `Factura` (Fase 1.1) al XML del SRI **válido contra `factura_v2.1.0.xsd`**, con escape correcto, decimales SRI (2 y 6) y `version="2.1.0"`, de forma determinista (golden test).

**Architecture:** Código nuevo bajo `src/Xml/`. Un helper `DomBuilder` centraliza la creación de nodos con valor de texto **escapado** (`createTextNode`), evitando el bug histórico de `createElement($n,$v)`. `FacturaXmlSerializer` consume el `Factura` tipado y produce el XML en el orden exacto que exige el XSD (mismo orden estructural que el generador 1.x ya probado en producción, pero declarando la versión correcta `2.1.0`). No toca el código 1.x.

**Tech Stack:** PHP 8.2, `ext-dom`/`ext-libxml`, PHPUnit 10. Reusa `Teran\Sri\Documents\*`, `Teran\Sri\Money\Money`, y `Teran\Sri\Schema\XsdValidator` (1.x, reutilizable).

---

## Contexto y alcance

Plan 2 de la Fase 1. Cubre **solo el serializador de la Factura**. La `claveAcceso` se calcula fuera (la inyecta quien orquesta; el serializador la recibe como parámetro). La firma XAdES, el transporte y el `SriClient` van en planes posteriores. Los serializadores de NC/ND/Guía/Retención replican este patrón después.

### Mapa de archivos

- Crear: `src/Xml/DomBuilder.php` — helper de nodos con texto escapado.
- Crear: `src/Xml/FacturaXmlSerializer.php` — `serialize(Factura $factura, string $claveAcceso): string`.
- Test: `tests/Unit/Xml/DomBuilderTest.php`, `tests/Unit/Xml/FacturaXmlSerializerTest.php`.

---

### Task 1: Helper `DomBuilder` (nodos con texto escapado)

**Files:**
- Create: `src/Xml/DomBuilder.php`
- Test: `tests/Unit/Xml/DomBuilderTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
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
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Xml/DomBuilderTest.php`
Expected: FAIL con `Class "Teran\Sri\Xml\DomBuilder" not found`.

- [ ] **Step 3: Implementar `DomBuilder`**

```php
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
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Xml/DomBuilderTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Xml/DomBuilder.php tests/Unit/Xml/DomBuilderTest.php
git commit -m "feat: add DomBuilder helper for escaped XML text nodes"
```

---

### Task 2: `FacturaXmlSerializer`

**Files:**
- Create: `src/Xml/FacturaXmlSerializer.php`
- Test: `tests/Unit/Xml/FacturaXmlSerializerTest.php`

**Contexto de estructura:** el orden de elementos debe seguir el XSD `factura_v2.1.0.xsd`. Se replica el orden del generador 1.x (probado en producción): `infoTributaria` → `infoFactura` → `detalles`. Atributo raíz `version="2.1.0"` (valor correcto según la ficha técnica; el generador 1.x emitía "1.1.0", que es el bug diferido — aquí se corrige porque es código nuevo). `codDoc` es `'01'` (factura).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Documents\Factura;

class FacturaXmlSerializerTest extends TestCase
{
    private function factura(): Factura
    {
        return Factura::fromArray([
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'COMERCIAL J & M',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Quito',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CONSUMIDOR FINAL',
                'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '100.00',
                'totalDescuento' => '0.00',
                'importeTotal' => '115.00',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'PROD001',
                    'descripcion' => 'Tornillos & tuercas',
                    'cantidad' => '1.00',
                    'precioUnitario' => '100.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '100.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
                    ],
                ],
            ],
        ]);
    }

    public function test_serializes_header_and_clave_acceso(): void
    {
        $clave = '2601202601179001100100110010010000000011234567819';
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), $clave);

        $this->assertStringContainsString('<factura id="comprobante" version="2.1.0">', $xml);
        $this->assertStringContainsString("<claveAcceso>$clave</claveAcceso>", $xml);
        $this->assertStringContainsString('<codDoc>01</codDoc>', $xml);
        $this->assertStringContainsString('<ambiente>1</ambiente>', $xml);
    }

    public function test_escapes_values_and_formats_decimals(): void
    {
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), '0000000000000000000000000000000000000000000000000');

        // El & se reparsea correctamente (bien formado).
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('COMERCIAL J & M', $dom->getElementsByTagName('razonSocial')->item(0)->textContent);
        $this->assertSame('Tornillos & tuercas', $dom->getElementsByTagName('descripcion')->item(0)->textContent);

        // Decimales SRI: 6 para cantidad/precioUnitario, 2 para montos.
        $this->assertStringContainsString('<cantidad>1.000000</cantidad>', $xml);
        $this->assertStringContainsString('<precioUnitario>100.000000</precioUnitario>', $xml);
        $this->assertStringContainsString('<importeTotal>115.00</importeTotal>', $xml);
        $this->assertStringContainsString('<formaPago>01</formaPago>', $xml);
    }

    public function test_is_deterministic(): void
    {
        $f = $this->factura();
        $s = new FacturaXmlSerializer();
        $this->assertSame(
            $s->serialize($f, '0000000000000000000000000000000000000000000000000'),
            $s->serialize($f, '0000000000000000000000000000000000000000000000000')
        );
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Xml/FacturaXmlSerializerTest.php`
Expected: FAIL con `Class "Teran\Sri\Xml\FacturaXmlSerializer" not found`.

- [ ] **Step 3: Implementar `FacturaXmlSerializer`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\Documents\Factura;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Pago;
use DOMDocument;
use DOMElement;

final class FacturaXmlSerializer
{
    private const VERSION = '2.1.0';
    private const COD_DOC = '01';

    public function serialize(Factura $factura, string $claveAcceso): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $factura, $claveAcceso);
        $this->infoFactura($b, $root, $factura);
        $this->detalles($b, $root, $factura);

        return $dom->saveXML();
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, Factura $f, string $claveAcceso): void
    {
        $info = $f->infoTributaria;
        $node = $b->child($root, 'infoTributaria');
        $b->child($node, 'ambiente', $info->ambiente->value);
        $b->child($node, 'tipoEmision', $info->tipoEmision->value);
        $b->child($node, 'razonSocial', $info->razonSocial);
        if ($info->nombreComercial !== null) {
            $b->child($node, 'nombreComercial', $info->nombreComercial);
        }
        $b->child($node, 'ruc', $info->ruc);
        $b->child($node, 'claveAcceso', $claveAcceso);
        $b->child($node, 'codDoc', self::COD_DOC);
        $b->child($node, 'estab', $info->estab);
        $b->child($node, 'ptoEmi', $info->ptoEmi);
        $b->child($node, 'secuencial', $info->secuencial);
        $b->child($node, 'dirMatriz', $info->dirMatriz);
    }

    private function infoFactura(DomBuilder $b, DOMElement $root, Factura $f): void
    {
        $node = $b->child($root, 'infoFactura');
        $b->child($node, 'fechaEmision', $f->fechaEmision);
        $b->child($node, 'obligadoContabilidad', $f->obligadoContabilidad);
        $b->child($node, 'tipoIdentificacionComprador', $f->tipoIdentificacionComprador);
        $b->child($node, 'razonSocialComprador', $f->razonSocialComprador);
        $b->child($node, 'identificacionComprador', $f->identificacionComprador);
        $b->child($node, 'totalSinImpuestos', $f->totalSinImpuestos->format(2));
        $b->child($node, 'totalDescuento', $f->totalDescuento->format(2));

        $tci = $b->child($node, 'totalConImpuestos');
        foreach ($f->totalConImpuestos as $imp) {
            /** @var Impuesto $imp */
            $ti = $b->child($tci, 'totalImpuesto');
            $b->child($ti, 'codigo', $imp->codigo);
            $b->child($ti, 'codigoPorcentaje', $imp->codigoPorcentaje);
            $b->child($ti, 'baseImponible', $imp->baseImponible->format(2));
            $b->child($ti, 'valor', $imp->valor->format(2));
        }

        $b->child($node, 'propina', '0.00');
        $b->child($node, 'importeTotal', $f->importeTotal->format(2));
        $b->child($node, 'moneda', 'DOLAR');

        $pagos = $b->child($node, 'pagos');
        foreach ($f->pagos as $pago) {
            /** @var Pago $pago */
            $p = $b->child($pagos, 'pago');
            $b->child($p, 'formaPago', $pago->formaPago->value);
            $b->child($p, 'total', $pago->total->format(2));
        }
    }

    private function detalles(DomBuilder $b, DOMElement $root, Factura $f): void
    {
        $node = $b->child($root, 'detalles');
        foreach ($f->detalles as $det) {
            /** @var Detalle $det */
            $d = $b->child($node, 'detalle');
            $b->child($d, 'codigoPrincipal', $det->codigoPrincipal);
            if ($det->codigoAuxiliar !== null) {
                $b->child($d, 'codigoAuxiliar', $det->codigoAuxiliar);
            }
            $b->child($d, 'descripcion', $det->descripcion);
            $b->child($d, 'cantidad', $det->cantidad->format(6));
            $b->child($d, 'precioUnitario', $det->precioUnitario->format(6));
            $b->child($d, 'descuento', $det->descuento->format(2));
            $b->child($d, 'precioTotalSinImpuesto', $det->precioTotalSinImpuesto->format(2));

            $imps = $b->child($d, 'impuestos');
            foreach ($det->impuestos as $imp) {
                /** @var Impuesto $imp */
                $i = $b->child($imps, 'impuesto');
                $b->child($i, 'codigo', $imp->codigo);
                $b->child($i, 'codigoPorcentaje', $imp->codigoPorcentaje);
                $b->child($i, 'tarifa', $imp->tarifa ?? '0');
                $b->child($i, 'baseImponible', $imp->baseImponible->format(2));
                $b->child($i, 'valor', $imp->valor->format(2));
            }
        }
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Xml/FacturaXmlSerializerTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Xml/FacturaXmlSerializer.php tests/Unit/Xml/FacturaXmlSerializerTest.php
git commit -m "feat: add FacturaXmlSerializer (typed Factura -> SRI XML)"
```

---

### Task 3: Golden test — conformidad XSD del XML serializado

**Files:**
- Modify: `tests/Unit/Xml/FacturaXmlSerializerTest.php` (añadir test)

**Contexto:** confirma que el XML producido por el serializador es **válido contra el esquema oficial** `resources/xsd/factura_v2.1.0.xsd`, usando el `XsdValidator` 1.x existente. Esta es la garantía de "funciona perfecto" del núcleo: si un refactor rompe la estructura, el test falla.

- [ ] **Step 1: Escribir el test que falla**

Añadir este método a `tests/Unit/Xml/FacturaXmlSerializerTest.php` (usa el `factura()` ya definido). Añadir el `use` al inicio del archivo: `use Teran\Sri\Schema\XsdValidator;`.

```php
    public function test_serialized_xml_is_valid_against_official_xsd(): void
    {
        $clave = '2601202601179001100100110010010000000011234567819';
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), $clave);

        $xsdPath = __DIR__ . '/../../../resources/xsd/factura_v2.1.0.xsd';
        $this->assertFileExists($xsdPath, 'Debe existir el XSD oficial de factura.');

        // XsdValidator::validate lanza ValidationException si no cumple; true si cumple.
        $this->assertTrue(XsdValidator::validate($xml, $xsdPath));
    }
```

- [ ] **Step 2: Correr el test y verificar que falla o pasa**

Run: `./vendor/bin/phpunit tests/Unit/Xml/FacturaXmlSerializerTest.php --filter test_serialized_xml_is_valid_against_official_xsd`
Expected: si el XML cumple el XSD → PASS directo (es la meta). Si **falla** con `ValidationException` (estructura/orden incorrectos), corregir `FacturaXmlSerializer` (orden de elementos, campos faltantes) hasta que el XSD valide. Documentar cualquier ajuste de orden necesario.

> Nota para el implementador: si el XSD exige campos o un orden distinto al asumido, ajusta `FacturaXmlSerializer` (NO el XSD ni el test) hasta validar. El esquema es la fuente de verdad. Si el XSD requiere `infoAdicional` u otros campos no presentes en el DTO, repórtalo como DONE_WITH_CONCERNS para planificar la extensión del DTO.

- [ ] **Step 3: Correr la suite completa**

Run: `./vendor/bin/phpunit`
Expected: PASS — todos los tests nuevos verdes; sin regresiones; 1 skip pre-existente intacto.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Xml/FacturaXmlSerializerTest.php
git commit -m "test: assert serialized Factura validates against official XSD"
```

---

## Self-Review

**1. Cobertura del spec (§4.4 "XML y validación"):**
- Escape correcto vía nodos de texto → `DomBuilder` (Task 1). ✅
- XML válido contra XSD → Task 3 (golden/XSD test). ✅
- Decimales SRI (2 y 6) → Task 2. ✅
- `version="2.1.0"` correcto (corrige el bug diferido en el path nuevo) → Task 2. ✅
- Determinismo (base del golden) → Task 2 `test_is_deterministic`. ✅

**2. Placeholders:** ninguno; código completo. La única condición abierta es explícita y manejada (si el XSD exige más campos → DONE_WITH_CONCERNS para extender el DTO).

**3. Consistencia de tipos:** `FacturaXmlSerializer::serialize(Factura, string): string`; `DomBuilder::child(DOMElement, string, ?string): DOMElement`; consume `Factura`/`Impuesto`/`Detalle`/`Pago` de Fase 1.1 con sus props reales (`->infoTributaria`, `->detalles`, `->pagos`, `Money->format(int)`, enums `->value`). ✅

**4. Reúso:** usa `Teran\Sri\Schema\XsdValidator` 1.x (con `LIBXML_NONET` ya endurecido) — reutilizable y agnóstico. ✅
