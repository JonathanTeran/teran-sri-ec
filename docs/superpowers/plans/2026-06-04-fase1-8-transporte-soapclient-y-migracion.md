# Fase 1.8 — Transporte zero-config (SoapClient) + migración Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hacer el núcleo 2.0 **usable out-of-the-box** (sin forzar un cliente HTTP externo) con un `SoapClientTransport` basado en `ext-soap` (ya requerido), un factory `SriClient::create(...)`, y una guía de migración `UPGRADE.md`. El `SRI` 1.x queda **intacto** (código probado, cero riesgo) — la migración es: viejo (sigue funcionando) → nuevo `SriClient`.

**Architecture:** `SoapClientTransport implements SriTransportInterface` porta el cliente SOAP probado del 1.x (`src/Soap/SriSoapClient.php`) y el parseo de respuestas (`src/Dto/RecepcionResponse.php`, `AutorizacionResponse.php`) a los *outcomes* tipados del 2.0, con un *seam* de prueba (la llamada SOAP es un callable inyectable) para testear sin red. `SriClient::create()` arma todo. No toca el 1.x.

**Tech Stack:** PHP 8.2, `ext-soap`, PHPUnit 10. Reusa `Transport\{SriTransportInterface,ReceptionOutcome,AuthorizationOutcome}`, `Emission\Message`, `Catalogs2\Ambiente`, `Signing\{Certificate,CertificateLoader,XadesSigner}`, `SriClient`.

---

## Contexto y alcance

Plan 8 (final del scope "5 comprobantes + shim"). Entrega usabilidad zero-config + migración. NO reescribe `src/SRI.php` (1.x). Las respuestas de `SoapClient` son objetos `stdClass`; el parseo se porta de los DTOs 1.x (`RecepcionResponse`/`AutorizacionResponse::fromSoap`) a `ReceptionOutcome`/`AuthorizationOutcome`.

### Mapa de archivos

- Create: `src/Transport/SoapClientTransport.php` — transporte ext-soap (con seam de prueba).
- Modify: `src/SriClient.php` — añadir factory estático `create()`.
- Create: `UPGRADE.md` — guía de migración 1.x → 2.0.
- Test: `tests/Unit/Transport/SoapClientTransportTest.php`, `tests/Unit/SriClientCreateTest.php`.

---

### Task 1: `SoapClientTransport` (ext-soap + seam de prueba)

**Files:**
- Create: `src/Transport/SoapClientTransport.php`
- Test: `tests/Unit/Transport/SoapClientTransportTest.php`
- Reference (READ): `src/Soap/SriSoapClient.php`, `src/Dto/RecepcionResponse.php`, `src/Dto/AutorizacionResponse.php`, `src/Dto/Mensaje.php`

**Contexto:** porta el WSDL/endpoints + retries del 1.x `SriSoapClient` y el parseo de los DTOs 1.x (que leen `stdClass`) a los outcomes 2.0. La llamada SOAP real se aísla en un callable inyectable (`$soapCaller`) — por defecto usa `SoapClient`; en tests se inyecta un fake que devuelve `stdClass` grabados.

- [ ] **Step 1: Escribir el test que falla (con seam)**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapClientTransport;
use Teran\Sri\Catalogs2\Ambiente;

class SoapClientTransportTest extends TestCase
{
    public function test_enviar_parses_recibida_from_soap_object(): void
    {
        $recibida = (object) [
            'RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => ''],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $recibida);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('RECIBIDA', $outcome->estado);
    }

    public function test_enviar_parses_devuelta_with_messages(): void
    {
        $devuelta = (object) [
            'RespuestaRecepcionComprobante' => (object) [
                'estado' => 'DEVUELTA',
                'comprobantes' => (object) [
                    'comprobante' => (object) [
                        'mensajes' => (object) [
                            'mensaje' => (object) ['identificador' => '43', 'mensaje' => 'RUC inválido', 'tipo' => 'ERROR'],
                        ],
                    ],
                ],
            ],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $devuelta);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('DEVUELTA', $outcome->estado);
        $this->assertCount(1, $outcome->mensajes);
        $this->assertSame('43', $outcome->mensajes[0]->identificador);
    }

    public function test_autorizar_parses_autorizado(): void
    {
        $auth = (object) [
            'autorizaciones' => (object) [
                'autorizacion' => (object) [
                    'estado' => 'AUTORIZADO',
                    'numeroAutorizacion' => '123',
                    'fechaAutorizacion' => '2026-01-26T10:00:00-05:00',
                    'comprobante' => '<factura/>',
                ],
            ],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $auth);

        $outcome = $transport->autorizar('2601...819', Ambiente::Pruebas);

        $this->assertSame('AUTORIZADO', $outcome->estado);
        $this->assertSame('123', $outcome->numeroAutorizacion);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Transport/SoapClientTransportTest.php`
Expected: FAIL con `Class "Teran\Sri\Transport\SoapClientTransport" not found`.

- [ ] **Step 3: Implementar `SoapClientTransport`**

Lee `src/Soap/SriSoapClient.php` (endpoints WSDL por ambiente + retries) y `src/Dto/RecepcionResponse.php`/`AutorizacionResponse.php` (lógica de parseo de `stdClass`, incluyendo el caso "código 70 / PROCESAMIENTO → RECIBIDA"). Porta:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Emission\Message;
use Teran\Sri\Exceptions\CommunicationException;
use SoapClient;
use SoapFault;

final class SoapClientTransport implements SriTransportInterface
{
    private const WSDL = [
        'recepcion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
        ],
        'autorizacion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ];

    /** @var (callable(string,array,string):object)|null */
    private $soapCaller;

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        ?callable $soapCaller = null,
    ) {
        $this->soapCaller = $soapCaller;
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $resp = $this->call('validarComprobante', ['xml' => $signedXml], self::WSDL['recepcion'][$this->key($ambiente)]);
        return $this->parseReception($resp);
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $resp = $this->call('autorizacionComprobante', ['claveAccesoComprobante' => $claveAcceso], self::WSDL['autorizacion'][$this->key($ambiente)]);
        return $this->parseAuthorization($resp);
    }

    private function key(Ambiente $a): string
    {
        return $a === Ambiente::Produccion ? 'produccion' : 'pruebas';
    }

    /** @param array<string,mixed> $params */
    private function call(string $method, array $params, string $wsdl): object
    {
        if ($this->soapCaller !== null) {
            return ($this->soapCaller)($method, $params, $wsdl);
        }
        $attempt = 0;
        while ($attempt < $this->retries) {
            try {
                $client = new SoapClient($wsdl, ['connection_timeout' => $this->timeout, 'trace' => true, 'exceptions' => true]);
                return $client->__soapCall($method, [$params]);
            } catch (SoapFault $e) {
                if (++$attempt >= $this->retries) {
                    throw new CommunicationException("Error de comunicación SRI tras {$this->retries} intentos: " . $e->getMessage());
                }
                usleep(500000);
            }
        }
        throw new CommunicationException('Falla desconocida en la comunicación con el SRI.');
    }

    private function parseReception(object $data): ReceptionOutcome
    {
        $root = $data->RespuestaRecepcionComprobante ?? $data;
        $estado = isset($root->estado) ? (string) $root->estado : 'DEVUELTA';
        $mensajes = [];
        if (isset($root->comprobantes->comprobante->mensajes->mensaje)) {
            $raw = $root->comprobantes->comprobante->mensajes->mensaje;
            foreach (is_array($raw) ? $raw : [$raw] as $m) {
                $mensajes[] = $this->message($m);
            }
        }
        // Código 70 / PROCESAMIENTO se trata como RECIBIDA (no es rechazo), igual que 1.x.
        if ($estado === 'DEVUELTA') {
            foreach ($mensajes as $m) {
                if ($m->identificador === '70' || stripos($m->mensaje, 'PROCESAMIENTO') !== false) {
                    $estado = 'RECIBIDA';
                    break;
                }
            }
        }
        return new ReceptionOutcome($estado, $mensajes);
    }

    private function parseAuthorization(object $data): AuthorizationOutcome
    {
        $a = null;
        if (isset($data->autorizaciones->autorizacion)) {
            $a = is_array($data->autorizaciones->autorizacion) ? $data->autorizaciones->autorizacion[0] : $data->autorizaciones->autorizacion;
        }
        if ($a === null) {
            return new AuthorizationOutcome('NO AUTORIZADO');
        }
        $mensajes = [];
        if (isset($a->mensajes->mensaje)) {
            $raw = $a->mensajes->mensaje;
            foreach (is_array($raw) ? $raw : [$raw] as $m) {
                $mensajes[] = $this->message($m);
            }
        }
        return new AuthorizationOutcome(
            estado: (string) ($a->estado ?? 'NO AUTORIZADO'),
            numeroAutorizacion: isset($a->numeroAutorizacion) ? (string) $a->numeroAutorizacion : null,
            fechaAutorizacion: isset($a->fechaAutorizacion) ? (string) $a->fechaAutorizacion : null,
            comprobante: isset($a->comprobante) ? (string) $a->comprobante : null,
            mensajes: $mensajes,
        );
    }

    private function message(object $m): Message
    {
        return new Message(
            identificador: isset($m->identificador) ? (string) $m->identificador : '',
            mensaje: isset($m->mensaje) ? (string) $m->mensaje : '',
            tipo: isset($m->tipo) ? (string) $m->tipo : '',
            informacionAdicional: isset($m->informacionAdicional) ? (string) $m->informacionAdicional : '',
        );
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Transport/SoapClientTransportTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Transport/SoapClientTransport.php tests/Unit/Transport/SoapClientTransportTest.php
git commit -m "feat: add zero-config SoapClientTransport (ext-soap)"
```

---

### Task 2: `SriClient::create()` factory

**Files:**
- Modify: `src/SriClient.php`
- Test: `tests/Unit/SriClientCreateTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teran\Sri\SriClient;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Transport\SoapClientTransport;
use Teran\Sri\Tests\Support\TestCertificate;

class SriClientCreateTest extends TestCase
{
    public function test_create_builds_a_working_client(): void
    {
        $tc = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        // Transporte explícito (fake) para no tocar la red.
        $transport = new SoapClientTransport(soapCaller: fn() => (object) [
            'RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => ''],
        ]);

        $client = SriClient::create(Ambiente::Pruebas, $cert, $transport);

        $this->assertInstanceOf(SriClient::class, $client);
    }

    public function test_create_defaults_to_soap_client_transport(): void
    {
        $tc = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $client = SriClient::create(Ambiente::Pruebas, $cert);
        $this->assertInstanceOf(SriClient::class, $client);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/SriClientCreateTest.php`
Expected: FAIL con `Call to undefined method ...::create()`.

- [ ] **Step 3: Implementar el factory en `SriClient`**

Añadir el método estático (sin cambiar el constructor existente):

```php
    use Teran\Sri\Transport\SoapClientTransport;
    // ... (asegurar el use al inicio del archivo)

    public static function create(
        Ambiente $ambiente,
        Certificate $certificate,
        ?SriTransportInterface $transport = null,
    ): self {
        return new self(
            ambiente: $ambiente,
            certificate: $certificate,
            transport: $transport ?? new SoapClientTransport(),
        );
    }
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/SriClientCreateTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Suite completa + commit**

Run: `./vendor/bin/phpunit` → verde.

```bash
git add src/SriClient.php tests/Unit/SriClientCreateTest.php
git commit -m "feat: add SriClient::create factory (defaults to SoapClientTransport)"
```

---

### Task 3: `UPGRADE.md` (guía de migración 1.x → 2.0)

**Files:**
- Create: `UPGRADE.md`

- [ ] **Step 1: Escribir la guía**

Crear `UPGRADE.md` con: tabla de equivalencias (API vieja → nueva), ejemplo antes/después, y la nota de que la clase `SRI` (1.x) sigue funcionando (deprecada de facto) mientras se migra a `SriClient`. Contenido mínimo:

```markdown
# Guía de migración a 2.0

La 2.0 introduce una API tipada y agnóstica de framework. **La clase `Teran\Sri\SRI` (1.x) sigue funcionando** sin cambios; migrar a `SriClient` es opcional pero recomendado.

## Equivalencias

| 1.x | 2.0 |
|---|---|
| `new SRI('pruebas')` | `SriClient::create(Ambiente::Pruebas, $cert)` |
| `$sri->setFirma($p12, $pass)` | `$cert = (new CertificateLoader())->load($p12, $pass)` |
| `$sri->facturaFromArray($data)` | `$client->emit(Factura::fromArray($data), $claveAcceso)` |
| resultado `['claveAcceso']` / `['xmlFirmado']` | `$result->claveAcceso` / `$result->signedXml` (o `$result['claveAcceso']`, `$result['xmlFirmado']` por ArrayAccess) |

## Antes / Después

(ejemplo de factura: construir cert, Factura::fromArray, generar clave con Utils\ClaveAcceso, emit)

## Notas
- El transporte por defecto (`SoapClientTransport`, ext-soap) funciona sin configuración. Para un cliente HTTP agnóstico/testeable, inyecta `Psr18SoapTransport` con tu cliente PSR-18 (Guzzle/Symfony).
- La clave de acceso se genera con `Teran\Sri\Utils\ClaveAcceso::generar(...)` (igual que en 1.x).
```

Rellenar el ejemplo antes/después con código real basado en el README 1.x y la API 2.0.

- [ ] **Step 2: Commit**

```bash
git add UPGRADE.md
git commit -m "docs: add 1.x -> 2.0 migration guide"
```

---

## Self-Review

**1. Cobertura (spec §4.5/§6):**
- 2.0 usable zero-config (sin forzar cliente HTTP) → `SoapClientTransport` (Task 1). ✅
- Factory de conveniencia → `SriClient::create` (Task 2). ✅
- Migración suave documentada → `UPGRADE.md` (Task 3). ✅
- El `SRI` 1.x intacto (cero riesgo para usuarios actuales). ✅

**2. Decisión de diseño:** no se reescribe el `SRI` 1.x (código probado en producción). La migración es "viejo sigue funcionando → `SriClient` nuevo", no una delegación interna riesgosa. (La delegación interna total / remoción del código 1.x es tema de 3.0.)

**3. Placeholders:** Tasks 1-2 con código completo; Task 3 es documentación (rellenar el ejemplo con código real).

**4. Consistencia:** `SoapClientTransport implements SriTransportInterface`; mismo `enviar/autorizar` que `Psr18SoapTransport`; `SriClient::create(Ambiente, Certificate, ?SriTransportInterface): self`. ✅

**5. Aditivo:** `src/Transport/`, `UPGRADE.md`, tests, y un método estático nuevo en `SriClient` (no cambia el constructor). No toca el 1.x ni rompe nada. ✅
