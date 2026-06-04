# Fase 1.7 — Comprobantes restantes (NC/ND/Guía/Retención) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar el modelo tipado + serialización del 2.0 para los 4 comprobantes restantes — **NotaCrédito, NotaDébito, GuíaRemisión, Retención** — portando la estructura de los generadores 1.x probados en producción a DTOs tipados (`src/Documents/`) + serializadores (`src/Xml/`), verificados por **paridad con el generador 1.x**.

**Architecture:** Cada tipo replica el patrón ya probado de `Factura` (Fase 1.1/1.2): un DTO `readonly` con `::fromArray()` (mismas llaves 1.x) + un serializador que **reusa `DomBuilder`** (escape correcto) y **espeja la estructura del generador 1.x correspondiente** (`src/Generators/Xxx Generator.php`). No se reimplementa nada: se porta lo probado. No toca el 1.x.

**Tech Stack:** PHP 8.2, PHPUnit 10. Reusa `Money`, `Catalogs2\*`, `Documents\{Impuesto,Detalle,Pago}`, `Xml\DomBuilder`.

> **Oráculo de correctitud (clave):** no hay XSD oficial commiteado para estos 4 tipos (solo factura). Pero los generadores 1.x **producen XML que el SRI acepta en producción**. Por eso cada serializador nuevo se valida por **paridad**: para los mismos datos, `XxxXmlSerializer::serialize(DTO, clave)` debe producir XML **idéntico** (normalizado) al de `XxxGenerator::generate($dataConClaveYCodDoc)`. Más tests de escape y determinismo. (Los XSD oficiales se pueden añadir luego como gate extra; ver Fase futura.)

---

## Contexto y alcance

Plan 7. Cubre los 4 comprobantes restantes. El shim 1.x→`SriClient` es el plan siguiente (1.8). Versiones de cada tipo (según ficha técnica, ya correctas en los generadores 1.x): NotaCrédito `1.1.0`, NotaDébito `1.0.0`, GuíaRemisión `1.1.0`, Retención `2.0.0`.

### Ejecución en 2 tandas
- **Tanda A:** NotaCrédito + NotaDébito (Tasks 1-2).
- **Tanda B:** GuíaRemisión + Retención (Tasks 3-4).

### Patrón compartido — helper de paridad (crear una vez, en Task 1)

`tests/Support/XmlParity.php`:

```php
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
```

### Patrón de cada serializador

Idéntico a `FacturaXmlSerializer` (Fase 1.2): `final class XxxXmlSerializer { public function serialize(Xxx $doc, string $claveAcceso): string }`, usa `new DomBuilder($dom)`, crea root con `id="comprobante"` + `version="<la del tipo>"`, emite `infoTributaria` (mismos campos que `XmlGenerator::createInfoTributaria` con `claveAcceso` + `codDoc`) y luego la sección específica + detalles, **en el mismo orden que el generador 1.x**. Decimales: 6 para cantidad/precioUnitario, 2 para montos (igual que Factura).

### Patrón de cada DTO

Idéntico a `Factura` (Fase 1.1): `final class Xxx` con props `readonly`, constructor que valida lo esencial, `::fromArray()` con las llaves del README/generador 1.x. Reusa `InfoTributaria`, `Detalle`, `Impuesto`, `Pago` donde aplique. Fechas `dd/MM/yyyy` validadas.

---

### Task 1: NotaCrédito (DTO + serializador) — referencia: `src/Generators/NotaCreditoGenerator.php`

**Files:**
- Create: `tests/Support/XmlParity.php` (helper compartido)
- Create: `src/Documents/NotaCredito.php`, `src/Xml/NotaCreditoXmlSerializer.php`
- Test: `tests/Unit/Documents/NotaCreditoTest.php`, `tests/Unit/Xml/NotaCreditoXmlSerializerTest.php`

**DTO `NotaCredito`** — campos (de `infoNotaCredito` en el generador 1.x): `infoTributaria` (InfoTributaria), `fechaEmision`, `tipoIdentificacionComprador`, `razonSocialComprador`, `identificacionComprador`, `obligadoContabilidad` (default 'NO'), `codDocModificado`, `numDocModificado`, `fechaEmisionDocSustento`, `totalSinImpuestos` (Money), `valorModificacion` (Money), `moneda` (default 'DOLAR'), `totalConImpuestos` (Impuesto[]), `motivo`, `detalles` (Detalle[]). `::fromArray($data)` lee `infoTributaria`, `infoNotaCredito`, `detalles` (mismas llaves 1.x).

- [ ] **Step 1 (RED): DTO test**

Escribir `NotaCreditoTest` siguiendo `FacturaTest` (Fase 1.1): `test_from_array_builds_aggregate` (arma desde un array con `infoTributaria`+`infoNotaCredito`+`detalles`, asserta `codDocModificado`, `valorModificacion->format(2)`, `count(detalles)`), y `test_rejects_without_detalles`. Correr: `./vendor/bin/phpunit tests/Unit/Documents/NotaCreditoTest.php` → FAIL (clase no existe).

- [ ] **Step 2 (GREEN): implementar `src/Documents/NotaCredito.php`**

Espeja `Factura` (constructor valida fecha `dd/MM/yyyy` + cada detalle instanceof Detalle; `fromArray` construye `InfoTributaria::fromArray`, `Impuesto::fromArray` para totalConImpuestos, `Detalle::fromArray` para detalles). Correr el test → PASS.

- [ ] **Step 3 (RED): serializador test (paridad + escape + determinismo)**

`NotaCreditoXmlSerializerTest`:
- `test_matches_1x_generator`: con datos representativos SIN caracteres especiales, comparar `XmlParity::normalize` de `(new NotaCreditoGenerator())->generate($dataConClaveYCodDoc)` vs `(new NotaCreditoXmlSerializer())->serialize(NotaCredito::fromArray($data), $clave)`. (Inyecta en `$dataConClaveYCodDoc['infoTributaria']` las llaves `claveAcceso`=$clave y `codDoc`='04' antes de llamar al generador 1.x, que es lo que hace `SRI.php`.)
- `test_escapes_special_chars`: razón social con `&`/`<` round-trippea correcto (parse del output).
- `test_is_deterministic`: serializar dos veces == .
Correr → FAIL (serializer no existe).

- [ ] **Step 4 (GREEN): implementar `src/Xml/NotaCreditoXmlSerializer.php`**

Lee `src/Generators/NotaCreditoGenerator.php` y espeja su estructura exacta (orden de `infoNotaCredito`: simpleFields → totalConImpuestos → motivo; luego `detalles`), consumiendo el DTO, con `DomBuilder`, `version="1.1.0"`, `codDoc="04"`. Ajusta hasta que **paridad** pase. Correr → PASS.

- [ ] **Step 5: suite + commits**

`./vendor/bin/phpunit` verde. Commits: `feat: add NotaCredito document DTO` y `feat: add NotaCreditoXmlSerializer (parity with 1.x generator)` (+ el helper XmlParity en el primer commit de serializador o aparte).

---

### Task 2: NotaDébito (DTO + serializador) — referencia: `src/Generators/NotaDebitoGenerator.php`

**Files:** `src/Documents/NotaDebito.php`, `src/Xml/NotaDebitoXmlSerializer.php`, tests.

**DTO `NotaDebito`** — campos (de `infoNotaDebito`): `infoTributaria`, `fechaEmision`, `tipoIdentificacionComprador`, `razonSocialComprador`, `identificacionComprador`, `obligadoContabilidad`, `codDocModificado`, `numDocModificado`, `fechaEmisionDocSustento`, `totalSinImpuestos` (Money), `impuestos` (Impuesto[]), `valorTotal` (Money), `pagos` (Pago[]), y `motivos` (array de `{razon, valor}` → un value object `MotivoDebito` simple, o array tipado). `::fromArray` lee `infoTributaria`, `infoNotaDebito`, `motivos`.

> Nota: la NotaDébito tiene `motivos` (no `detalles`). Modela `Motivo` como un pequeño value object (`razon`:string, `valor`:Money) en `src/Documents/Motivo.php`.

- [ ] **Step 1 (RED) → Step 4 (GREEN):** mismo patrón que Task 1 (DTO test + DTO; serializer test con paridad vs `NotaDebitoGenerator` + escape + determinismo + serializer). `version="1.0.0"`, `codDoc="05"`. La sección `infoNotaDebito` espeja el generador: simpleFields → impuestos → valorTotal → pagos; luego `motivos`.
- [ ] **Step 5:** suite verde + commits análogos.

---

### Task 3: GuíaRemisión (DTO + serializador) — referencia: `src/Generators/GuiaRemisionGenerator.php`

**Files:** `src/Documents/GuiaRemision.php`, `src/Documents/Destinatario.php`, `src/Xml/GuiaRemisionXmlSerializer.php`, tests.

**DTO `GuiaRemision`** — `infoTributaria`, `infoGuiaRemision` (dirEstablecimiento, dirPartida, razonSocialTransportista, tipoIdentificacionTransportista, rucTransportista, obligadoContabilidad, fechaIniTransporte, fechaFinTransporte, placa, …), `destinatarios` (Destinatario[]). `Destinatario` (value object): identificacionDestinatario, razonSocialDestinatario, dirDestinatario, motivoTraslado, codEstabDestino, ruta, codDocSustento, numDocSustento, numAutDocSustento, fechaEmisionDocSustento, `detalles` (array de `{codigoInterno, descripcion, cantidad}`). `::fromArray` lee `infoTributaria`, `infoGuiaRemision`, `destinatarios`.

- [ ] **Step 1→5:** mismo patrón. `version="1.1.0"`, `codDoc="06"`. Serializador espeja `GuiaRemisionGenerator` (infoGuiaRemision simpleFields; destinatarios → destinatario simpleFields → detalles → detalle). Paridad + escape + determinismo. Suite verde + commits.

---

### Task 4: Retención (DTO + serializador) — referencia: `src/Generators/RetencionGenerator.php`

**Files:** `src/Documents/Retencion.php`, `src/Documents/DocSustento.php`, `src/Xml/RetencionXmlSerializer.php`, tests.

**DTO `Retencion`** (formato v2.0.0 con `docsSustento`) — `infoTributaria`, `infoCompRetencion` (fechaEmision, dirEstablecimiento, tipoIdentificacionSujetoRetenido, razonSocialSujetoRetenido, identificacionSujetoRetenido, periodoFiscal, …), `docsSustento` (DocSustento[]). `DocSustento` (value object): codSustento, codDocSustento, numDocSustento, fechaEmisionDocSustento, totalSinImpuestos (Money), importeTotal (Money), `impuestosDocSustento` (array), `retenciones` (array de `{codigo, codigoRetencion, baseImponible, porcentajeRetener, valorRetenido}`), `pagos`. `::fromArray` lee `infoTributaria`, `infoCompRetencion`, `docsSustento`.

- [ ] **Step 1→5:** mismo patrón. `version="2.0.0"`, `codDoc="07"`. Serializador espeja `RetencionGenerator::createDocsSustento` (orden: infoCompRetencion → docsSustento → docSustento simpleFields → impuestosDocSustento → retenciones → pagos). Paridad + escape + determinismo. Suite verde + commits.

---

## Self-Review

**1. Cobertura (spec §4.2/§4.4):** los 4 comprobantes restantes con DTO tipado + serializador → Tasks 1-4. ✅ Completa los 5 tipos del 2.0 (con Factura de Fase 1.1/1.2).

**2. Oráculo:** paridad con el generador 1.x probado en producción (sustituye al golden-XSD que no está disponible para estos 4 tipos) + escape + determinismo. Fuerte y honesto. ✅

**3. Placeholders:** el helper de paridad y el patrón están con código completo; los DTOs/serializadores son ports de código en el repo (`src/Generators/*`) con el test de paridad como oráculo ejecutable (igual criterio que el port de XadesSigner en Fase 1.4).

**4. Consistencia:** cada `XxxXmlSerializer::serialize(Xxx, string): string`; cada `Xxx::fromArray(array): self`; reusan `DomBuilder`, `Money`, `Impuesto`/`Detalle`/`Pago`. ✅

**5. Aditivo:** solo `src/Documents/`, `src/Xml/`, `tests/`; no toca el 1.x (que sirve de referencia y sigue como shim). ✅

**6. Pendiente Fase 1.8:** shim — el viejo `SRI::{facturaFromArray,notaCreditoFromArray,…,procesar}` delega en `SriClient` (genera clave de acceso con `Utils\ClaveAcceso`, arma el DTO con `::fromArray`, serializa+firma+envía), devolviendo el array vía `EmissionResult` ArrayAccess. Con auto-discovery del cliente PSR-18.
