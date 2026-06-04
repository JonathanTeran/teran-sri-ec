# Fase 1.5 — SriClient + transporte (interfaz) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exponer la API pública de emisión individual del 2.0: `SriClient::emit(Factura, claveAcceso): EmissionResult`, que orquesta **serializar → firmar → enviar → autorizar** a través de un `SriTransportInterface` (transporte abstracto), devolviendo un `EmissionResult` inmutable con `ArrayAccess` (compatibilidad con el shim 1.x). El transporte concreto SOAP/PSR-18 se implementa en la Fase 1.6; aquí se prueba la orquestación con un `FakeTransport`.

**Architecture:** Código nuevo bajo `src/Emission/` y `src/Transport/`. `SriClient` compone `FacturaXmlSerializer` (1.2) + `XadesSigner` (1.4) + un `SriTransportInterface` inyectado, sobre un `Certificate` (1.3). El transporte devuelve *outcomes* tipados. `EmissionResult` mapea las llaves de 1.x (`claveAcceso`, `xmlFirmado`) vía `ArrayAccess`. No toca el 1.x.

**Tech Stack:** PHP 8.2, PHPUnit 10. Reusa `Documents\Factura`, `Xml\FacturaXmlSerializer`, `Signing\{Certificate,XadesSigner}`, `Catalogs2\Ambiente`, `Tests\Support\{TestCertificate}`.

---

## Contexto y alcance

Plan 5 de la Fase 1. Cubre la **orquestación de emisión individual** y la **interfaz** de transporte. NO incluye el transporte real al SRI (SOAP/PSR-18) — eso es la Fase 1.6, que implementará `SriTransportInterface`. El `FakeTransport` (en `tests/Support`) permite probar `SriClient` de punta a punta sin red.

### Mapa de archivos

- Crear: `src/Emission/EmissionStatus.php` — enum del resultado final.
- Crear: `src/Emission/Message.php` — value object de mensaje del SRI.
- Crear: `src/Transport/ReceptionOutcome.php`, `src/Transport/AuthorizationOutcome.php` — resultados del transporte.
- Crear: `src/Transport/SriTransportInterface.php` — contrato del transporte.
- Crear: `src/Emission/EmissionResult.php` — resultado inmutable (`ArrayAccess`).
- Crear: `src/SriClient.php` — orquestador `emit()`.
- Crear: `tests/Support/FakeTransport.php` — transporte de prueba.
- Test: `tests/Unit/Emission/EmissionResultTest.php`, `tests/Unit/SriClientTest.php`.

---

### Task 1: `EmissionStatus` enum + `Message` value object

**Files:**
- Create: `src/Emission/EmissionStatus.php`, `src/Emission/Message.php`
- Test: `tests/Unit/Emission/MessageTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Emission;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Emission\Message;
use Teran\Sri\Emission\EmissionStatus;

class MessageTest extends TestCase
{
    public function test_message_holds_sri_fields(): void
    {
        $m = new Message('43', 'RUC del emisor se encuentra...', 'ERROR', 'info extra');
        $this->assertSame('43', $m->identificador);
        $this->assertSame('ERROR', $m->tipo);
        $this->assertSame('info extra', $m->informacionAdicional);
    }

    public function test_status_cases_exist(): void
    {
        $this->assertNotNull(EmissionStatus::Authorized);
        $this->assertNotNull(EmissionStatus::Rejected);
        $this->assertNotNull(EmissionStatus::InProcess);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Emission/MessageTest.php`
Expected: FAIL con `Class "Teran\Sri\Emission\Message" not found`.

- [ ] **Step 3: Implementar**

`src/Emission/EmissionStatus.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

enum EmissionStatus: string
{
    case Authorized = 'AUTORIZADO';
    case Rejected = 'RECHAZADO';
    case InProcess = 'EN_PROCESO';
    case Error = 'ERROR';
}
```

`src/Emission/Message.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

final class Message
{
    public function __construct(
        public readonly string $identificador,
        public readonly string $mensaje,
        public readonly string $tipo = '',
        public readonly string $informacionAdicional = '',
    ) {
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Emission/MessageTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Emission/EmissionStatus.php src/Emission/Message.php tests/Unit/Emission/MessageTest.php
git commit -m "feat: add EmissionStatus enum and Message value object"
```

---

### Task 2: `ReceptionOutcome` + `AuthorizationOutcome` + `SriTransportInterface`

**Files:**
- Create: `src/Transport/ReceptionOutcome.php`, `src/Transport/AuthorizationOutcome.php`, `src/Transport/SriTransportInterface.php`
- Test: `tests/Unit/Transport/OutcomeTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;

class OutcomeTest extends TestCase
{
    public function test_reception_outcome_holds_estado_and_messages(): void
    {
        $o = new ReceptionOutcome('RECIBIDA', []);
        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame([], $o->mensajes);
    }

    public function test_authorization_outcome_holds_fields(): void
    {
        $o = new AuthorizationOutcome('AUTORIZADO', '1234567890', '2026-01-26T10:00:00-05:00', '<xml/>', []);
        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('1234567890', $o->numeroAutorizacion);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Transport/OutcomeTest.php`
Expected: FAIL con `Class "Teran\Sri\Transport\ReceptionOutcome" not found`.

- [ ] **Step 3: Implementar**

`src/Transport/ReceptionOutcome.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

final class ReceptionOutcome
{
    /** @param Message[] $mensajes */
    public function __construct(
        public readonly string $estado,
        public readonly array $mensajes = [],
    ) {
    }
}
```

`src/Transport/AuthorizationOutcome.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

final class AuthorizationOutcome
{
    /** @param Message[] $mensajes */
    public function __construct(
        public readonly string $estado,
        public readonly ?string $numeroAutorizacion = null,
        public readonly ?string $fechaAutorizacion = null,
        public readonly ?string $comprobante = null,
        public readonly array $mensajes = [],
    ) {
    }
}
```

`src/Transport/SriTransportInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;

interface SriTransportInterface
{
    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome;

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome;
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Transport/OutcomeTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Transport/ tests/Unit/Transport/OutcomeTest.php
git commit -m "feat: add transport outcomes and SriTransportInterface"
```

---

### Task 3: `FakeTransport` (test support)

**Files:**
- Create: `tests/Support/FakeTransport.php`

- [ ] **Step 1: Crear el doble de prueba**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Catalogs2\Ambiente;

final class FakeTransport implements SriTransportInterface
{
    public ?string $lastSentXml = null;
    public ?string $lastAuthorizedClave = null;

    public function __construct(
        private readonly ReceptionOutcome $reception,
        private readonly AuthorizationOutcome $authorization,
    ) {
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $this->lastSentXml = $signedXml;
        return $this->reception;
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $this->lastAuthorizedClave = $claveAcceso;
        return $this->authorization;
    }
}
```

- [ ] **Step 2: Verificar autoload**

Run: `composer dump-autoload 2>&1 | tail -1 && php -r "require 'vendor/autoload.php'; var_dump(interface_exists('Teran\\Sri\\Transport\\SriTransportInterface') && class_exists('Teran\\Sri\\Tests\\Support\\FakeTransport'));"`
Expected: `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add tests/Support/FakeTransport.php composer.json composer.lock
git commit -m "test: add FakeTransport double"
```

---

### Task 4: `EmissionResult` (inmutable + ArrayAccess)

**Files:**
- Create: `src/Emission/EmissionResult.php`
- Test: `tests/Unit/Emission/EmissionResultTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Emission;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Emission\EmissionResult;
use Teran\Sri\Emission\EmissionStatus;

class EmissionResultTest extends TestCase
{
    public function test_property_and_array_access_both_work(): void
    {
        $r = new EmissionResult(
            status: EmissionStatus::Authorized,
            claveAcceso: '2601...819',
            signedXml: '<factura/>',
            numeroAutorizacion: '1234567890',
        );

        // Acceso por propiedad (estilo 2.0)
        $this->assertSame(EmissionStatus::Authorized, $r->status);
        $this->assertSame('2601...819', $r->claveAcceso);

        // Acceso por array (compat 1.x)
        $this->assertSame('2601...819', $r['claveAcceso']);
        $this->assertSame('<factura/>', $r['xmlFirmado']);
        $this->assertSame('1234567890', $r['numeroAutorizacion']);
        $this->assertTrue(isset($r['claveAcceso']));
        $this->assertFalse(isset($r['inexistente']));
    }

    public function test_is_immutable_via_array_access(): void
    {
        $r = new EmissionResult(EmissionStatus::Error, 'x', '<xml/>');
        $this->expectException(\LogicException::class);
        $r['claveAcceso'] = 'otro';
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Emission/EmissionResultTest.php`
Expected: FAIL con `Class "Teran\Sri\Emission\EmissionResult" not found`.

- [ ] **Step 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

/**
 * Resultado inmutable de una emisión. Implementa ArrayAccess para que el código
 * 1.x (`$resultado['claveAcceso']`, `$resultado['xmlFirmado']`) siga funcionando.
 *
 * @implements \ArrayAccess<string,mixed>
 */
final class EmissionResult implements \ArrayAccess
{
    /** @param Message[] $messages */
    public function __construct(
        public readonly EmissionStatus $status,
        public readonly string $claveAcceso,
        public readonly string $signedXml,
        public readonly ?string $numeroAutorizacion = null,
        public readonly ?string $fechaAutorizacion = null,
        public readonly ?string $authorizedXml = null,
        public readonly array $messages = [],
    ) {
    }

    /** @return array<string,mixed> mapa de llaves legacy 1.x → valores */
    private function legacyMap(): array
    {
        return [
            'claveAcceso' => $this->claveAcceso,
            'xmlFirmado' => $this->signedXml,
            'numeroAutorizacion' => $this->numeroAutorizacion,
            'fechaAutorizacion' => $this->fechaAutorizacion,
            'estado' => $this->status->value,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->legacyMap());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->legacyMap()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('EmissionResult es inmutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('EmissionResult es inmutable.');
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Emission/EmissionResultTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Emission/EmissionResult.php tests/Unit/Emission/EmissionResultTest.php
git commit -m "feat: add immutable EmissionResult with ArrayAccess (1.x compat)"
```

---

### Task 5: `SriClient` — orquestación `emit()`

**Files:**
- Create: `src/SriClient.php`
- Test: `tests/Unit/SriClientTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teran\Sri\SriClient;
use Teran\Sri\Emission\EmissionStatus;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Emission\Message;
use Teran\Sri\Tests\Support\TestCertificate;
use Teran\Sri\Tests\Support\FakeTransport;

class SriClientTest extends TestCase
{
    private function factura(): Factura
    {
        return Factura::fromArray([
            'infoTributaria' => [
                'ambiente' => '1', 'razonSocial' => 'EMPRESA', 'ruc' => '1790011001001',
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
    }

    private function client(FakeTransport $t): SriClient
    {
        $tc = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);
        return new SriClient(Ambiente::Pruebas, $cert, $t);
    }

    public function test_emit_authorized_flow(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('AUTORIZADO', '1234567890', '2026-01-26T10:00:00-05:00', '<auth/>', []),
        );
        $client = $this->client($transport);

        $result = $client->emit($this->factura(), '2601202601179001100100110010010000000011234567819');

        $this->assertSame(EmissionStatus::Authorized, $result->status);
        $this->assertSame('1234567890', $result->numeroAutorizacion);
        $this->assertStringContainsString('ds:Signature', $result->signedXml); // se firmó
        $this->assertStringContainsString('ds:Signature', $transport->lastSentXml); // se envió el firmado
    }

    public function test_emit_returned_at_reception_is_rejected(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('DEVUELTA', [new Message('43', 'RUC inválido', 'ERROR')]),
            new AuthorizationOutcome('NO AUTORIZADO'),
        );
        $client = $this->client($transport);

        $result = $client->emit($this->factura(), '2601202601179001100100110010010000000011234567819');

        $this->assertSame(EmissionStatus::Rejected, $result->status);
        $this->assertNull($transport->lastAuthorizedClave); // no se consultó autorización
        $this->assertNotEmpty($result->messages);
    }

    public function test_emit_in_process_maps_to_in_process(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('EN PROCESO'),
        );
        $result = $this->client($transport)->emit($this->factura(), '2601202601179001100100110010010000000011234567819');
        $this->assertSame(EmissionStatus::InProcess, $result->status);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/SriClientTest.php`
Expected: FAIL con `Class "Teran\Sri\SriClient" not found`.

- [ ] **Step 3: Implementar `SriClient`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Signing\XadesSigner;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Emission\EmissionResult;
use Teran\Sri\Emission\EmissionStatus;

/**
 * Entrada del 2.0 para emisión individual. Orquesta:
 * serializar → firmar → enviar (recepción) → autorizar.
 */
final class SriClient
{
    public function __construct(
        private readonly Ambiente $ambiente,
        private readonly Certificate $certificate,
        private readonly SriTransportInterface $transport,
        private readonly XadesSigner $signer = new XadesSigner(),
        private readonly FacturaXmlSerializer $facturaSerializer = new FacturaXmlSerializer(),
    ) {
    }

    public function emit(Factura $factura, string $claveAcceso): EmissionResult
    {
        $xml = $this->facturaSerializer->serialize($factura, $claveAcceso);
        $signed = $this->signer->sign($xml, $this->certificate);

        $reception = $this->transport->enviar($signed, $this->ambiente);
        if ($reception->estado !== 'RECIBIDA') {
            return new EmissionResult(
                status: EmissionStatus::Rejected,
                claveAcceso: $claveAcceso,
                signedXml: $signed,
                messages: $reception->mensajes,
            );
        }

        $auth = $this->transport->autorizar($claveAcceso, $this->ambiente);
        $status = match (strtoupper($auth->estado)) {
            'AUTORIZADO' => EmissionStatus::Authorized,
            'EN PROCESO', 'EN PROCESAMIENTO' => EmissionStatus::InProcess,
            default => EmissionStatus::Rejected,
        };

        return new EmissionResult(
            status: $status,
            claveAcceso: $claveAcceso,
            signedXml: $signed,
            numeroAutorizacion: $auth->numeroAutorizacion,
            fechaAutorizacion: $auth->fechaAutorizacion,
            authorizedXml: $auth->comprobante,
            messages: $auth->mensajes,
        );
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/SriClientTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Suite completa**

Run: `./vendor/bin/phpunit`
Expected: verde, sin regresiones, 1 skip pre-existente.

- [ ] **Step 6: Commit**

```bash
git add src/SriClient.php tests/Unit/SriClientTest.php
git commit -m "feat: add SriClient.emit orchestration (serialize -> sign -> send -> authorize)"
```

---

## Self-Review

**1. Cobertura (spec §4.1, §5):**
- API `SriClient::emit(Factura, clave): EmissionResult` → Task 5. ✅
- Orquestación serializar→firmar→enviar→autorizar → Task 5. ✅
- `EmissionResult` inmutable + `ArrayAccess` (compat 1.x: `claveAcceso`, `xmlFirmado`) → Task 4. ✅
- `SriTransportInterface` (transporte abstracto, real en 1.6) + outcomes tipados → Tasks 2-3. ✅
- Estados mapeados (AUTORIZADO/EN PROCESO/DEVUELTA→rechazado) → Task 5. ✅

**2. Placeholders:** ninguno; código completo.

**3. Consistencia de tipos:** `SriClient::emit(Factura, string): EmissionResult`; `SriTransportInterface::{enviar(string,Ambiente):ReceptionOutcome, autorizar(string,Ambiente):AuthorizationOutcome}`; `EmissionResult` con props readonly + ArrayAccess; `EmissionStatus` enum. ✅

**4. Aditivo:** solo `src/Emission/`, `src/Transport/`, `src/SriClient.php`, `tests/`; no toca el 1.x. ✅

**5. Pendiente Fase 1.6:** implementar `SriTransportInterface` real (envelope SOAP sobre PSR-18, TLS verificado, reintentos idempotency-safe) + el shim legacy `SRI` que delega en `SriClient`.
