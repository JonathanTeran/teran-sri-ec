# Fase 1.8 — SoapClientTransport (out-of-the-box) + deprecación del API 1.x Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Implementar `SoapClientTransport` — un `SriTransportInterface` basado en `ext-soap` (siempre disponible) que porta el cliente SOAP probado del 1.x, para que el API 2.0 (`SriClient`/`BatchEmitter`) funcione **sin requerir un cliente PSR-18 externo**. (2) Marcar el API 1.x (`SRI`) como `@deprecated` apuntando a `SriClient`, sin cambiar su comportamiento.

**Architecture:** `SoapClientTransport` (en `src/Transport/`) usa `SoapClient` (ext-soap) para las 2 operaciones del SRI y delega el parseo de la respuesta `stdClass` en un `SoapStdClassParser` testeable (porta la lógica probada de `Dto\RecepcionResponse`/`Dto\AutorizacionResponse` → outcomes tipados). La llamada SOAP se aísla tras un *seam* inyectable para poder testear el flujo sin red. El `SRI` 1.x se deja intacto en comportamiento, solo se le añaden docblocks `@deprecated`. No se reescribe el camino probado.

**Tech Stack:** PHP 8.2, `ext-soap`, PHPUnit 10. Reusa `SriTransportInterface`, `ReceptionOutcome`/`AuthorizationOutcome`, `Emission\Message`, `Catalogs2\Ambiente`.

---

## Contexto y alcance

Plan 8 (último del núcleo de emisión). Cierra la usabilidad out-of-the-box del 2.0 y la compatibilidad. **No** reescribe `SRI::facturaFromArray`/`procesar` para delegar en `SriClient` — esa unificación es de 3.0 (al remover el 1.x); ahora ambos caminos coexisten y funcionan. Los serializadores 2.0 ya están verificados por paridad con los generadores 1.x, así que la unificación futura es de bajo riesgo.

### Mapa de archivos

- Create: `src/Transport/SoapStdClassParser.php` — parsea la respuesta `stdClass` de `SoapClient` → outcomes.
- Create: `src/Transport/SoapClientTransport.php` — `SriTransportInterface` vía `ext-soap`.
- Modify: `src/SRI.php` — docblocks `@deprecated` (sin cambio de comportamiento).
- Test: `tests/Unit/Transport/SoapStdClassParserTest.php`, `tests/Unit/Transport/SoapClientTransportTest.php`.

---

### Task 1: `SoapStdClassParser`

**Files:** `src/Transport/SoapStdClassParser.php`, `tests/Unit/Transport/SoapStdClassParserTest.php`

**Contexto:** `SoapClient::__soapCall` devuelve un `stdClass` anidado. Porta la lógica de `src/Dto/RecepcionResponse.php::fromSoap` y `src/Dto/AutorizacionResponse.php::fromSoap` (léelas) a métodos que devuelven `ReceptionOutcome`/`AuthorizationOutcome`. Normaliza mensajes (objeto único o array).

- [ ] **Step 1 (RED): test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapStdClassParser;

class SoapStdClassParserTest extends TestCase
{
    public function test_parses_recibida(): void
    {
        $resp = (object) ['RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => '']];
        $o = (new SoapStdClassParser())->reception($resp);
        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame([], $o->mensajes);
    }

    public function test_parses_devuelta_with_single_message(): void
    {
        $resp = (object) ['RespuestaRecepcionComprobante' => (object) [
            'estado' => 'DEVUELTA',
            'comprobantes' => (object) ['comprobante' => (object) ['mensajes' => (object) ['mensaje' => (object) [
                'identificador' => '43', 'mensaje' => 'RUC inválido', 'tipo' => 'ERROR', 'informacionAdicional' => 'x',
            ]]]],
        ]];
        $o = (new SoapStdClassParser())->reception($resp);
        $this->assertSame('DEVUELTA', $o->estado);
        $this->assertCount(1, $o->mensajes);
        $this->assertSame('43', $o->mensajes[0]->identificador);
    }

    public function test_parses_autorizado(): void
    {
        $resp = (object) ['autorizaciones' => (object) ['autorizacion' => (object) [
            'estado' => 'AUTORIZADO', 'numeroAutorizacion' => '123', 'fechaAutorizacion' => '2026-01-26', 'comprobante' => '<f/>', 'mensajes' => '',
        ]]];
        $o = (new SoapStdClassParser())->authorization($resp);
        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('123', $o->numeroAutorizacion);
        $this->assertSame('<f/>', $o->comprobante);
    }
}
```

- [ ] **Step 2: Correr → FAIL.**

- [ ] **Step 3 (GREEN): implementar** `src/Transport/SoapStdClassParser.php` portando la lógica de los DTOs 1.x:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

/** Parsea la respuesta stdClass de SoapClient del SRI a outcomes tipados (port de los DTOs 1.x). */
final class SoapStdClassParser
{
    public function reception(object $data): ReceptionOutcome
    {
        $root = $data->RespuestaRecepcionComprobante ?? $data;
        $estado = isset($root->estado) ? (string) $root->estado : 'DEVUELTA';

        $mensajes = [];
        if (isset($root->comprobantes->comprobante->mensajes->mensaje)) {
            $mensajes = $this->messages($root->comprobantes->comprobante->mensajes->mensaje);
        }
        return new ReceptionOutcome($estado, $mensajes);
    }

    public function authorization(object $data): AuthorizationOutcome
    {
        $autorizacion = null;
        if (isset($data->autorizaciones->autorizacion)) {
            $autorizacion = is_array($data->autorizaciones->autorizacion)
                ? ($data->autorizaciones->autorizacion[0] ?? null)
                : $data->autorizaciones->autorizacion;
        }
        if ($autorizacion === null) {
            return new AuthorizationOutcome('NO AUTORIZADO');
        }

        $mensajes = isset($autorizacion->mensajes->mensaje) ? $this->messages($autorizacion->mensajes->mensaje) : [];

        return new AuthorizationOutcome(
            estado: (string) ($autorizacion->estado ?? 'NO AUTORIZADO'),
            numeroAutorizacion: isset($autorizacion->numeroAutorizacion) ? (string) $autorizacion->numeroAutorizacion : null,
            fechaAutorizacion: isset($autorizacion->fechaAutorizacion) ? (string) $autorizacion->fechaAutorizacion : null,
            comprobante: isset($autorizacion->comprobante) ? (string) $autorizacion->comprobante : null,
            mensajes: $mensajes,
        );
    }

    /** @return Message[] */
    private function messages(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($rows as $m) {
            $out[] = new Message(
                identificador: isset($m->identificador) ? (string) $m->identificador : '',
                mensaje: isset($m->mensaje) ? (string) $m->mensaje : '',
                tipo: isset($m->tipo) ? (string) $m->tipo : '',
                informacionAdicional: isset($m->informacionAdicional) ? (string) $m->informacionAdicional : '',
            );
        }
        return $out;
    }
}
```

- [ ] **Step 4: Correr → PASS.** **Step 5: Commit** `feat: add SoapStdClassParser (port of 1.x SOAP response parsing)`.

---

### Task 2: `SoapClientTransport`

**Files:** `src/Transport/SoapClientTransport.php`, `tests/Unit/Transport/SoapClientTransportTest.php`

**Contexto:** porta `src/Soap/SriSoapClient.php` (1.x). Las WSDL por ambiente, reintentos, timeout. La llamada SOAP real se aísla en un *seam* `callable` inyectable (`($method, $params, $wsdl) => stdClass`) que por defecto usa `SoapClient`; los tests inyectan un seam que devuelve stdClass grabados.

- [ ] **Step 1 (RED): test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapClientTransport;
use Teran\Sri\Catalogs2\Ambiente;

class SoapClientTransportTest extends TestCase
{
    public function test_enviar_calls_validar_and_parses(): void
    {
        $captured = [];
        $seam = function (string $method, array $params, string $wsdl) use (&$captured) {
            $captured = ['method' => $method, 'params' => $params, 'wsdl' => $wsdl];
            return (object) ['RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => '']];
        };
        $transport = new SoapClientTransport(soapCaller: $seam);

        $o = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame('validarComprobante', $captured['method']);
        $this->assertSame('<factura/>', $captured['params']['xml']);
        $this->assertStringContainsString('celcer.sri.gob.ec', $captured['wsdl']);
        $this->assertStringContainsString('RecepcionComprobantesOffline', $captured['wsdl']);
    }

    public function test_autorizar_uses_production_wsdl(): void
    {
        $captured = [];
        $seam = function (string $method, array $params, string $wsdl) use (&$captured) {
            $captured = ['method' => $method, 'params' => $params, 'wsdl' => $wsdl];
            return (object) ['autorizaciones' => (object) ['autorizacion' => (object) ['estado' => 'AUTORIZADO', 'numeroAutorizacion' => '9']]];
        };
        $transport = new SoapClientTransport(soapCaller: $seam);

        $o = $transport->autorizar('2601...819', Ambiente::Produccion);

        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('autorizacionComprobante', $captured['method']);
        $this->assertSame('2601...819', $captured['params']['claveAccesoComprobante']);
        $this->assertStringContainsString('cel.sri.gob.ec', $captured['wsdl']);
        $this->assertStringNotContainsString('celcer', $captured['wsdl']);
    }
}
```

- [ ] **Step 2: Correr → FAIL.**

- [ ] **Step 3 (GREEN): implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;
use SoapClient;
use SoapFault;

/**
 * Transporte basado en ext-soap (SoapClient), portado del 1.x. Funciona out-of-the-box
 * (ext-soap es requisito del paquete) sin necesidad de un cliente PSR-18 externo.
 * La llamada SOAP real se aísla en $soapCaller para poder testear sin red.
 */
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

    /** @var callable(string,array,string):object */
    private $soapCaller;

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        private readonly SoapStdClassParser $parser = new SoapStdClassParser(),
        ?callable $soapCaller = null,
    ) {
        $this->soapCaller = $soapCaller ?? fn (string $method, array $params, string $wsdl): object
            => $this->realSoapCall($method, $params, $wsdl);
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $wsdl = self::WSDL['recepcion'][$this->key($ambiente)];
        // PHP SoapClient codifica a Base64 automáticamente el campo base64Binary.
        $resp = $this->call('validarComprobante', ['xml' => $signedXml], $wsdl);
        return $this->parser->reception($resp);
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $wsdl = self::WSDL['autorizacion'][$this->key($ambiente)];
        $resp = $this->call('autorizacionComprobante', ['claveAccesoComprobante' => $claveAcceso], $wsdl);
        return $this->parser->authorization($resp);
    }

    private function key(Ambiente $ambiente): string
    {
        return $ambiente === Ambiente::Produccion ? 'produccion' : 'pruebas';
    }

    private function call(string $method, array $params, string $wsdl): object
    {
        $attempt = 0;
        while (true) {
            try {
                return ($this->soapCaller)($method, $params, $wsdl);
            } catch (SoapFault $e) {
                if (++$attempt >= $this->retries) {
                    throw new CommunicationException("Error de comunicación SRI tras {$this->retries} intentos: " . $e->getMessage());
                }
                usleep(500000);
            }
        }
    }

    private function realSoapCall(string $method, array $params, string $wsdl): object
    {
        $client = new SoapClient($wsdl, ['connection_timeout' => $this->timeout, 'trace' => true, 'exceptions' => true]);
        /** @var object $result */
        $result = $client->__soapCall($method, [$params]);
        return $result;
    }
}
```

- [ ] **Step 4: Correr → PASS.** **Step 5: Commit** `feat: add SoapClientTransport (ext-soap, out-of-the-box SriTransportInterface)`.

---

### Task 3: Deprecar el API 1.x (sin cambio de comportamiento)

**Files:** `src/SRI.php`

- [ ] **Step 1:** Añadir un docblock de clase a `Teran\Sri\SRI` y `@deprecated` a sus métodos públicos (`facturaFromArray`, `notaCreditoFromArray`, `notaDebitoFromArray`, `guiaRemisionFromArray`, `retencionFromArray`, `setFirma`, `procesar`, `firmarXml`, `consultarAutorizacion`, `validarXml`). NO cambiar ninguna línea de lógica. Ejemplo de docblock de clase:

```php
/**
 * @deprecated 2.0 Usa Teran\Sri\SriClient (emisión individual) o Teran\Sri\Batch\BatchEmitter
 *             (envío masivo). Esta clase 1.x se mantiene por compatibilidad y se eliminará en 3.0.
 *             Migración: $sri->facturaFromArray($a) → $client->emit(Factura::fromArray($a['... ']), $clave).
 */
```

y por método: `/** @deprecated 2.0 Usa SriClient::emit(). */` etc.

- [ ] **Step 2: Verificar** que la suite sigue **idéntica** (mismo conteo, verde) — los docblocks no cambian comportamiento. `./vendor/bin/phpunit`.

- [ ] **Step 3: Commit** `docs: deprecate 1.x SRI API in favor of SriClient/BatchEmitter (no behavior change)`.

---

## Self-Review

**1. Cobertura:** API 2.0 usable out-of-the-box (`SoapClientTransport`, sin dep PSR-18 externa) + compatibilidad 1.x preservada y deprecada. ✅
**2. Riesgo controlado:** NO se reescribe el camino 1.x probado; solo se añade transporte nuevo (aditivo) y docblocks. La unificación (SRI→SriClient) es de 3.0. Documentado.
**3. Testabilidad:** la llamada SOAP real se aísla tras `$soapCaller`; el parseo (`SoapStdClassParser`) y el ruteo de WSDL se testean sin red. (La llamada `realSoapCall` se valida con un smoke test manual contra el SRI de pruebas.)
**4. Consistencia:** `SoapClientTransport implements SriTransportInterface`; mismas firmas que `Psr18SoapTransport`. El usuario elige el transporte al construir `SriClient`/`BatchProcessor`. ✅
**5. Aditivo:** `src/Transport/` nuevo + docblocks en `SRI.php` (sin lógica). ✅
**6. Acción del usuario (pre-producción):** smoke test del transporte (cualquiera de los dos) contra el ambiente de pruebas del SRI.
