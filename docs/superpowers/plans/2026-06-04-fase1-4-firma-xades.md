# Fase 1.4 — Firma XAdES-BES (XadesSigner) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Firmar el XML de un comprobante con XAdES-BES en el 2.0, **portando la lógica probada del 1.x** (`src/Signature/XadesSignature.php`) a un `XadesSigner` que consume el `Certificate` (Fase 1.3), con `Clock` inyectable (para `SigningTime` testeable) y `Description` configurable (sin el branding de terceros), verificado por un test que valida contra XSD, comprueba la firma **criptográficamente** y es determinista.

**Architecture:** Código nuevo bajo `src/Signing/`. **NO se reimplementa XAdES desde cero** — se adapta la lógica existente del 1.x (que produce firmas que el SRI acepta) a la nueva forma: recibe un `Certificate` en vez de cargar el `.p12`, toma `SigningTime` de un `Clock`, y la `Description` de `SignatureOptions`. El 1.x `XadesSignature` queda intacto como shim.

**Tech Stack:** PHP 8.2, `ext-dom`/`ext-openssl`/`ext-libxml`, PHPUnit 10. Reusa `Teran\Sri\Signing\Certificate`, `Teran\Sri\Signing\CertificateLoader`, `Teran\Sri\Xml\FacturaXmlSerializer`, `Teran\Sri\Schema\XsdValidator`, `Teran\Sri\Tests\Support\TestCertificate`.

---

## Contexto y alcance

Plan 4 de la Fase 1. Cubre la **firma**. No cubre el transporte ni el `SriClient` (planes siguientes). El SRI exige **SHA-1** para la firma; se mantiene por defecto (configurable). El `factura_v2.1.0.xsd` ya admite el nodo `ds:Signature` (wildcard `processContents="lax"`), así que un comprobante firmado **valida** contra él.

### Mapa de archivos

- Crear: `src/Signing/ClockInterface.php`, `src/Signing/SystemClock.php` — reloj inyectable.
- Crear: `src/Signing/SignatureOptions.php` — opciones (description, digest).
- Crear: `src/Signing/XadesSigner.php` — firma XAdES (port del 1.x, consume Certificate).
- Crear: `tests/Support/FixedClock.php` — reloj fijo para tests.
- Test: `tests/Unit/Signing/XadesSignerTest.php`.

---

### Task 1: Reloj inyectable (`ClockInterface`, `SystemClock`, `FixedClock`)

**Files:**
- Create: `src/Signing/ClockInterface.php`, `src/Signing/SystemClock.php`, `tests/Support/FixedClock.php`
- Test: `tests/Unit/Signing/ClockTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\SystemClock;
use Teran\Sri\Tests\Support\FixedClock;

class ClockTest extends TestCase
{
    public function test_system_clock_returns_now(): void
    {
        $before = new \DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function test_fixed_clock_returns_fixed_instant(): void
    {
        $instant = new \DateTimeImmutable('2026-01-26T10:00:00-05:00');
        $this->assertEquals($instant, (new FixedClock($instant))->now());
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Signing/ClockTest.php`
Expected: FAIL con `Class "Teran\Sri\Signing\SystemClock" not found`.

- [ ] **Step 3: Implementar**

`src/Signing/ClockInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

`src/Signing/SystemClock.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
```

`tests/Support/FixedClock.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Signing\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $instant)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Signing/ClockTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Signing/ClockInterface.php src/Signing/SystemClock.php tests/Support/FixedClock.php tests/Unit/Signing/ClockTest.php
git commit -m "feat: add injectable Clock for deterministic signing time"
```

---

### Task 2: `SignatureOptions`

**Files:**
- Create: `src/Signing/SignatureOptions.php`
- Test: `tests/Unit/Signing/SignatureOptionsTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\SignatureOptions;
use Teran\Sri\Exceptions\SignatureException;

class SignatureOptionsTest extends TestCase
{
    public function test_defaults_are_sri_compatible_and_generic(): void
    {
        $o = new SignatureOptions();
        $this->assertSame('sha1', $o->digestAlgorithm); // SRI requiere SHA-1
        $this->assertStringNotContainsStringIgnoringCase('ecuanexus', $o->description);
        $this->assertStringNotContainsStringIgnoringCase('ecuafact', $o->description);
        $this->assertNotSame('', $o->description);
    }

    public function test_rejects_unsupported_digest(): void
    {
        $this->expectException(SignatureException::class);
        new SignatureOptions(digestAlgorithm: 'md5');
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Signing/SignatureOptionsTest.php`
Expected: FAIL con `Class "Teran\Sri\Signing\SignatureOptions" not found`.

- [ ] **Step 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\SignatureException;

final class SignatureOptions
{
    public function __construct(
        public readonly string $digestAlgorithm = 'sha1',
        public readonly string $description = 'Comprobante electrónico SRI Ecuador',
    ) {
        if (!in_array(strtolower($digestAlgorithm), ['sha1', 'sha256'], true)) {
            throw new SignatureException("Algoritmo de digest no soportado: $digestAlgorithm. Use sha1 o sha256.");
        }
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Signing/SignatureOptionsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Signing/SignatureOptions.php tests/Unit/Signing/SignatureOptionsTest.php
git commit -m "feat: add SignatureOptions (configurable description, generic default)"
```

---

### Task 3: `XadesSigner` (port de la firma probada del 1.x)

**Files:**
- Create: `src/Signing/XadesSigner.php`
- Reference (READ, do not modify): `src/Signature/XadesSignature.php`

**Contexto — esto es un PORT, no una reimplementación.** La lógica XAdES del 1.x (`src/Signature/XadesSignature.php`) produce firmas que el SRI acepta en producción. Cópiala a `src/Signing/XadesSigner.php` con EXACTAMENTE estas transformaciones, dejando **toda la construcción XAdES idéntica** (IDs, References, digests, C14N, `buildKeyInfo`, `buildSignedProperties`, `extractCertificateInfo`, `normalizeSerialNumber`, `buildDistinguishedName`, algoritmos, etc.):

1. **Namespace y nombre:** `namespace Teran\Sri\Signing;` clase `XadesSigner`.
2. **Constructor nuevo** (sin cargar `.p12`):
   ```php
   public function __construct(
       private readonly SignatureOptions $options = new SignatureOptions(),
       private readonly ClockInterface $clock = new SystemClock(),
   ) {
       $this->digestAlgorithm = $this->options->digestAlgorithm;
   }
   ```
   Conserva la propiedad `private string $digestAlgorithm;` que usan los helpers.
3. **Eliminar** del 1.x todo lo relativo a cargar el certificado: los métodos `loadCertificate()`, `validateLoadedCerts()`, las propiedades `$p12Content`, `$password`, y el uso de `$this->certs`/`$this->extraCerts` como estado de instancia. Esa responsabilidad ahora es de `CertificateLoader`.
4. **Firma:** `public function sign(string $xml, Certificate $cert): string`. Dentro, en vez de `$this->certs['cert']`/`$this->certs['pkey']`/`$this->extraCerts`, usa `$cert->certPem`, `$cert->privateKeyPem`, `$cert->extraCerts`. Adapta `extractCertificateInfo()` y `buildKeyInfo()` para recibir/usar el `Certificate` (pásalo como parámetro a los helpers que lo necesiten en vez de leer `$this->certs`).
5. **`SigningTime`** (en `buildSignedProperties`): usa `$this->clock->now()->format('Y-m-d\TH:i:sP')` en lugar de `date('Y-m-d\TH:i:sP')`.
6. **`etsi:Description`** (en `buildSignedProperties`): usa `$this->options->description` en lugar de la cadena fija con "ECUAFACT/ecuanexus".
7. **NO** debe existir ninguna escritura de debug (`base_path`, `file_put_contents`). Conserva `loadXML($xmlContent, LIBXML_NONET)` y `saveXML(null, LIBXML_NOEMPTYTAG)`.
8. Mantén `setDigestAlgorithm`/`getCertificateInfo` solo si los usas; si no, omítelos (YAGNI). El núcleo es `sign()` y sus helpers de construcción.

El **test de la Task 4 es la fuente de verdad**: ajusta el port hasta que el test pase (XSD válido + verificación criptográfica + determinismo). Si algo del port no compila o no verifica, corrígelo conservando la estructura XAdES del 1.x.

- [ ] **Step 1: Leer la referencia y escribir el `XadesSigner`**

Lee `src/Signature/XadesSignature.php` completo. Crea `src/Signing/XadesSigner.php` aplicando las transformaciones 1-8. Importa: `use Teran\Sri\Exceptions\SignatureException; use DOMDocument; use DOMElement;` y los tipos `Certificate`, `SignatureOptions`, `ClockInterface`, `SystemClock` del mismo namespace. Usa `SignatureException` (no `SriException`) para errores de firma.

- [ ] **Step 2: Verificar que la clase carga**

Run: `php -r "require 'vendor/autoload.php'; var_dump(class_exists('Teran\\Sri\\Signing\\XadesSigner'));"`
Expected: `bool(true)` (si hay error de parseo, corregirlo).

- [ ] **Step 3: Commit (la verificación de comportamiento va en Task 4)**

```bash
git add src/Signing/XadesSigner.php
git commit -m "feat: add XadesSigner (ported XAdES-BES, consumes Certificate + Clock)"
```

---

### Task 4: Test de firma — XSD + verificación criptográfica + determinismo

**Files:**
- Create: `tests/Unit/Signing/XadesSignerTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\XadesSigner;
use Teran\Sri\Signing\SignatureOptions;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Schema\XsdValidator;
use Teran\Sri\Tests\Support\TestCertificate;
use Teran\Sri\Tests\Support\FixedClock;

class XadesSignerTest extends TestCase
{
    private Certificate $cert;
    private string $unsignedXml;

    protected function setUp(): void
    {
        $tc = TestCertificate::modernP12();
        $this->cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $factura = Factura::fromArray([
            'infoTributaria' => [
                'ambiente' => '1', 'razonSocial' => 'EMPRESA & CIA', 'ruc' => '1790011001001',
                'estab' => '001', 'ptoEmi' => '001', 'secuencial' => '000000001', 'dirMatriz' => 'Quito',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026', 'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CONSUMIDOR FINAL', 'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '100.00', 'totalDescuento' => '0.00', 'importeTotal' => '115.00',
                'totalConImpuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00']],
                'pagos' => [['formaPago' => '01', 'total' => '115.00']],
            ],
            'detalles' => [[
                'codigoPrincipal' => 'P1', 'descripcion' => 'Producto', 'cantidad' => '1.00',
                'precioUnitario' => '100.00', 'descuento' => '0.00', 'precioTotalSinImpuesto' => '100.00',
                'impuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00']],
            ]],
        ]);
        $clave = '2601202601179001100100110010010000000011234567819';
        $this->unsignedXml = (new FacturaXmlSerializer())->serialize($factura, $clave);
    }

    public function test_signed_xml_validates_against_xsd(): void
    {
        $signed = (new XadesSigner())->sign($this->unsignedXml, $this->cert);
        $xsd = __DIR__ . '/../../../resources/xsd/factura_v2.1.0.xsd';
        $this->assertTrue(XsdValidator::validate($signed, $xsd));
        $this->assertStringContainsString('ds:Signature', $signed);
    }

    public function test_signing_time_comes_from_clock(): void
    {
        $instant = new \DateTimeImmutable('2026-01-26T10:00:00-05:00');
        $signed = (new XadesSigner(new SignatureOptions(), new FixedClock($instant)))->sign($this->unsignedXml, $this->cert);
        $this->assertStringContainsString('<etsi:SigningTime>2026-01-26T10:00:00-05:00</etsi:SigningTime>', $signed);
    }

    public function test_description_is_generic_not_third_party(): void
    {
        $signed = (new XadesSigner())->sign($this->unsignedXml, $this->cert);
        $this->assertStringNotContainsStringIgnoringCase('ecuanexus', $signed);
        $this->assertStringNotContainsStringIgnoringCase('ecuafact', $signed);
    }

    public function test_is_deterministic_for_fixed_clock_and_cert(): void
    {
        $signer = new XadesSigner(new SignatureOptions(), new FixedClock(new \DateTimeImmutable('2026-01-26T10:00:00-05:00')));
        $this->assertSame(
            $signer->sign($this->unsignedXml, $this->cert),
            $signer->sign($this->unsignedXml, $this->cert)
        );
    }

    public function test_signature_value_verifies_cryptographically(): void
    {
        $signed = (new XadesSigner())->sign($this->unsignedXml, $this->cert);

        $dom = new \DOMDocument();
        $dom->loadXML($signed);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signedInfo = $xp->query('//ds:SignedInfo')->item(0);
        $sigValueB64 = trim($xp->query('//ds:SignatureValue')->item(0)->textContent);
        $this->assertNotEmpty($sigValueB64);

        // Verificar la SignatureValue contra SignedInfo canonicalizado con la clave pública del cert.
        $c14n = $signedInfo->C14N();
        $ok = openssl_verify($c14n, base64_decode($sigValueB64), $this->cert->certPem, OPENSSL_ALGO_SHA1);
        $this->assertSame(1, $ok, 'La firma debe verificar criptográficamente sobre SignedInfo canonicalizado.');
    }
}
```

> Nota para el implementador: el test `test_signature_value_verifies_cryptographically` es la verificación criptográfica fuerte. Si la canonicalización independiente de `SignedInfo` resultara dependiente del entorno (contexto de namespaces de C14N) y no diera `1` pese a una firma estructuralmente correcta, NO debilites el resto: deja ese test, investiga el contexto de C14N (debe coincidir con el que usa el firmante: inclusive C14N `REC-xml-c14n-20010315` sobre el nodo `SignedInfo` dentro del documento), y ajústalo hasta que verifique. Si tras investigar es genuinamente inestable, conviértelo en aserción de que la `SignatureValue` es base64 válido y reporta DONE_WITH_CONCERNS explicando por qué; los otros 4 tests (XSD, clock, description, determinismo) deben pasar siempre.

- [ ] **Step 2: Correr y ver fallar/pasar; iterar el port hasta verde**

Run: `./vendor/bin/phpunit tests/Unit/Signing/XadesSignerTest.php`
Expected: ajustar `XadesSigner` (Task 3) hasta que los 5 tests pasen. El XSD y la verificación criptográfica son objetivos: si fallan, el port tiene un error que corregir (no toques el XSD ni debilites las aserciones de seguridad).

- [ ] **Step 3: Suite completa**

Run: `./vendor/bin/phpunit`
Expected: verde, sin regresiones, 1 skip pre-existente.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Signing/XadesSignerTest.php
git commit -m "test: XadesSigner validates XSD, verifies cryptographically, is deterministic"
```

---

## Self-Review

**1. Cobertura (spec §4.3):**
- Firma XAdES-BES consumiendo `Certificate` → Task 3. ✅
- `Clock` inyectable (SigningTime determinista) → Tasks 1, 4. ✅
- `Description` configurable, sin branding de terceros → Tasks 2, 4 (`test_description_is_generic_not_third_party`). ✅
- SHA-1 por defecto (requisito SRI), configurable → Task 2. ✅
- Garantía de confiabilidad: XSD + verificación criptográfica + determinismo → Task 4. ✅

**2. Riesgo controlado:** se PORTA la lógica probada del 1.x (no se reinventa XAdES). El 1.x queda intacto como shim.

**3. Placeholders:** Tasks 1, 2, 4 traen código completo. Task 3 es un port preciso de código en el repo (reproducir 800 líneas de XAdES en el plan sería peor y propenso a error); el test objetivo de Task 4 valida el resultado.

**4. Consistencia de tipos:** `XadesSigner::sign(string, Certificate): string`; `__construct(SignatureOptions, ClockInterface)`; `ClockInterface::now(): DateTimeImmutable`; `SignatureOptions(digestAlgorithm, description)`. ✅

**5. Aditivo:** solo `src/Signing/` y `tests/`; no toca `src/Signature/XadesSignature.php` (1.x). ✅

**6. Pendiente Fase 1.5:** `SriClient` orquestará serializar → firmar → (transporte) → autorizar, devolviendo `EmissionResult`.
