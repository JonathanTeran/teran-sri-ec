# Fase 1.6 — Transporte SOAP sobre PSR-18 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el `SriTransportInterface` real: construir el envelope SOAP de los WS *offline* del SRI (recepción/autorización), enviarlo por un cliente **PSR-18** (HTTP agnóstico, TLS verificado), y parsear la respuesta a los *outcomes* tipados — todo testeable con respuestas grabadas (sin red ni `ext-soap`).

**Architecture:** Código nuevo bajo `src/Transport/`. `SoapEnvelopeBuilder` arma los cuerpos SOAP. `Psr18SoapTransport` (implements `SriTransportInterface`) compone el builder + un `Psr\Http\Client\ClientInterface` + factories PSR-17 (inyectados) y delega el parseo en `SoapResponseParser` (XPath namespace-agnóstico). El núcleo solo depende de las **interfaces** PSR (psr/http-client, psr/http-factory); el cliente concreto lo inyecta el usuario. No toca el 1.x.

**Tech Stack:** PHP 8.2, `ext-dom`/`ext-libxml`, `psr/http-client` + `psr/http-factory` (interfaces), PHPUnit 10; `nyholm/psr7` en `require-dev` para los tests.

> ⚠️ **Verificación contra el SRI:** estos tests validan la **estructura** del envelope (formato documentado del WS offline del SRI) y el **parseo** de respuestas grabadas. NO prueban contra el SRI real (requiere credenciales/ambiente). Antes de usar en producción, hacer un *smoke test* contra el ambiente de **pruebas** del SRI (`celcer.sri.gob.ec`). El `SoapResponseParser` es namespace-agnóstico para tolerar variaciones de prefijos.

---

## Contexto y alcance

Plan 6 de la Fase 1. Implementa el transporte (la interfaz quedó en 1.5). Endpoints SRI *offline*:

| Servicio | Pruebas | Producción | Namespace | Operación / parámetro |
|---|---|---|---|---|
| Recepción | `https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline` | `https://cel.sri.gob.ec/...` | `http://ec.gob.sri.ws.recepcion` | `validarComprobante` / `xml` (base64) |
| Autorización | `https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline` | `https://cel.sri.gob.ec/...` | `http://ec.gob.sri.ws.autorizacion` | `autorizacionComprobante` / `claveAccesoComprobante` |

### Mapa de archivos

- Modify: `composer.json` — `require`: `psr/http-client`, `psr/http-factory`; `require-dev`: `nyholm/psr7`.
- Create: `src/Transport/SoapEnvelopeBuilder.php` — arma los cuerpos SOAP.
- Create: `src/Transport/SoapResponseParser.php` — parsea respuestas → outcomes.
- Create: `src/Transport/Psr18SoapTransport.php` — el transporte.
- Create: `tests/Support/FakePsr18Client.php` — cliente PSR-18 que devuelve respuestas grabadas.
- Test: `tests/Unit/Transport/SoapEnvelopeBuilderTest.php`, `SoapResponseParserTest.php`, `Psr18SoapTransportTest.php`.

---

### Task 1: composer — dependencias PSR

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Añadir dependencias**

En `require` añadir (orden alfabético, tras `psr/log` no — `psr/http-*` van antes de `psr/log`):

```json
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/log": "^3.0"
```

En `require-dev` añadir junto a phpunit:

```json
    "require-dev": {
        "nyholm/psr7": "^1.8",
        "phpunit/phpunit": "^10.0"
    },
```

- [ ] **Step 2: Instalar y verificar**

Run: `composer update nyholm/psr7 psr/http-client psr/http-factory 2>&1 | tail -3 && php -r "require 'vendor/autoload.php'; var_dump(interface_exists('Psr\\Http\\Client\\ClientInterface') && class_exists('Nyholm\\Psr7\\Factory\\Psr17Factory'));"`
Expected: `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "build: require PSR-18/PSR-17 interfaces (+ nyholm/psr7 dev)"
```

---

### Task 2: `SoapEnvelopeBuilder`

**Files:**
- Create: `src/Transport/SoapEnvelopeBuilder.php`
- Test: `tests/Unit/Transport/SoapEnvelopeBuilderTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapEnvelopeBuilder;

class SoapEnvelopeBuilderTest extends TestCase
{
    public function test_reception_envelope_base64_encodes_signed_xml(): void
    {
        $signed = '<factura>áé&</factura>';
        $env = (new SoapEnvelopeBuilder())->reception($signed);

        $this->assertStringContainsString('http://ec.gob.sri.ws.recepcion', $env);
        $this->assertStringContainsString('validarComprobante', $env);
        $this->assertStringContainsString('<xml>' . base64_encode($signed) . '</xml>', $env);
        // Bien formado
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($env));
    }

    public function test_authorization_envelope_carries_clave(): void
    {
        $env = (new SoapEnvelopeBuilder())->authorization('2601...819');

        $this->assertStringContainsString('http://ec.gob.sri.ws.autorizacion', $env);
        $this->assertStringContainsString('autorizacionComprobante', $env);
        $this->assertStringContainsString('<claveAccesoComprobante>2601...819</claveAccesoComprobante>', $env);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Transport/SoapEnvelopeBuilderTest.php`
Expected: FAIL con `Class "Teran\Sri\Transport\SoapEnvelopeBuilder" not found`.

- [ ] **Step 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

final class SoapEnvelopeBuilder
{
    public function reception(string $signedXml): string
    {
        $b64 = base64_encode($signedXml);
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.recepcion">
           <soapenv:Header/>
           <soapenv:Body>
              <ec:validarComprobante>
                 <xml>{$b64}</xml>
              </ec:validarComprobante>
           </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }

    public function authorization(string $claveAcceso): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.autorizacion">
           <soapenv:Header/>
           <soapenv:Body>
              <ec:autorizacionComprobante>
                 <claveAccesoComprobante>{$claveAcceso}</claveAccesoComprobante>
              </ec:autorizacionComprobante>
           </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }
}
```

> Nota: `claveAccesoComprobante` es siempre 49 dígitos (validado aguas arriba), por lo que no introduce caracteres especiales. El `xml` va en base64, sin caracteres a escapar.

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Transport/SoapEnvelopeBuilderTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Transport/SoapEnvelopeBuilder.php tests/Unit/Transport/SoapEnvelopeBuilderTest.php
git commit -m "feat: add SoapEnvelopeBuilder for SRI offline WS"
```

---

### Task 3: `SoapResponseParser`

**Files:**
- Create: `src/Transport/SoapResponseParser.php`
- Test: `tests/Unit/Transport/SoapResponseParserTest.php`

**Contexto:** parseo **namespace-agnóstico** (XPath `local-name()`) para tolerar prefijos variables del SRI. Maneja: recepción `RECIBIDA`/`DEVUELTA`; autorización `AUTORIZADO`/`NO AUTORIZADO`/`EN PROCESAMIENTO`; y mensajes (identificador/mensaje/tipo/informacionAdicional).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapResponseParser;

class SoapResponseParserTest extends TestCase
{
    public function test_parses_recibida(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:validarComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.recepcion">
              <RespuestaRecepcionComprobante>
                <estado>RECIBIDA</estado>
                <comprobantes/>
              </RespuestaRecepcionComprobante>
            </ns2:validarComprobanteResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

        $o = (new SoapResponseParser())->parseReception($xml);
        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame([], $o->mensajes);
    }

    public function test_parses_devuelta_with_messages(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <validarComprobanteResponse>
              <RespuestaRecepcionComprobante>
                <estado>DEVUELTA</estado>
                <comprobantes>
                  <comprobante>
                    <claveAcceso>2601...819</claveAcceso>
                    <mensajes>
                      <mensaje>
                        <identificador>43</identificador>
                        <mensaje>RUC del emisor no existe</mensaje>
                        <tipo>ERROR</tipo>
                        <informacionAdicional>1790011001001</informacionAdicional>
                      </mensaje>
                    </mensajes>
                  </comprobante>
                </comprobantes>
              </RespuestaRecepcionComprobante>
            </validarComprobanteResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

        $o = (new SoapResponseParser())->parseReception($xml);
        $this->assertSame('DEVUELTA', $o->estado);
        $this->assertCount(1, $o->mensajes);
        $this->assertSame('43', $o->mensajes[0]->identificador);
        $this->assertSame('ERROR', $o->mensajes[0]->tipo);
    }

    public function test_parses_autorizado(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion">
              <RespuestaAutorizacionComprobante>
                <autorizaciones>
                  <autorizacion>
                    <estado>AUTORIZADO</estado>
                    <numeroAutorizacion>2601202601179001100112345678</numeroAutorizacion>
                    <fechaAutorizacion>2026-01-26T10:00:00-05:00</fechaAutorizacion>
                    <comprobante>&lt;factura/&gt;</comprobante>
                    <mensajes/>
                  </autorizacion>
                </autorizaciones>
              </RespuestaAutorizacionComprobante>
            </ns2:autorizacionComprobanteResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

        $o = (new SoapResponseParser())->parseAuthorization($xml);
        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('2601202601179001100112345678', $o->numeroAutorizacion);
        $this->assertSame('<factura/>', $o->comprobante);
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Transport/SoapResponseParserTest.php`
Expected: FAIL con `Class "Teran\Sri\Transport\SoapResponseParser" not found`.

- [ ] **Step 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;
use DOMDocument;
use DOMXPath;
use DOMNode;

/**
 * Parser namespace-agnóstico (local-name) de las respuestas SOAP del SRI offline.
 */
final class SoapResponseParser
{
    public function parseReception(string $responseXml): ReceptionOutcome
    {
        $xp = $this->xpath($responseXml);
        $estado = $this->text($xp, '//*[local-name()="estado"]') ?? 'DEVUELTA';
        $mensajes = $this->messages($xp, '//*[local-name()="mensaje"][*[local-name()="identificador"] or *[local-name()="mensaje"]]');
        return new ReceptionOutcome($estado, $mensajes);
    }

    public function parseAuthorization(string $responseXml): AuthorizationOutcome
    {
        $xp = $this->xpath($responseXml);
        $auth = $xp->query('//*[local-name()="autorizacion"]')->item(0);
        if (!$auth instanceof DOMNode) {
            return new AuthorizationOutcome('NO AUTORIZADO');
        }
        return new AuthorizationOutcome(
            estado: $this->childText($auth, 'estado') ?? 'NO AUTORIZADO',
            numeroAutorizacion: $this->childText($auth, 'numeroAutorizacion'),
            fechaAutorizacion: $this->childText($auth, 'fechaAutorizacion'),
            comprobante: $this->childText($auth, 'comprobante'),
            mensajes: $this->messages(new DOMXPath($auth->ownerDocument), './/*[local-name()="mensaje"][*[local-name()="identificador"] or *[local-name()="mensaje"]]', $auth),
        );
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function text(DOMXPath $xp, string $query): ?string
    {
        $n = $xp->query($query)->item(0);
        return $n instanceof DOMNode ? trim($n->textContent) : null;
    }

    private function childText(DOMNode $node, string $localName): ?string
    {
        foreach ($node->childNodes as $c) {
            if ($c->localName === $localName) {
                $v = trim($c->textContent);
                return $v === '' ? null : $v;
            }
        }
        return null;
    }

    /** @return Message[] */
    private function messages(DOMXPath $xp, string $query, ?DOMNode $context = null): array
    {
        $nodes = $context ? $xp->query($query, $context) : $xp->query($query);
        $out = [];
        foreach ($nodes as $m) {
            $out[] = new Message(
                identificador: $this->childText($m, 'identificador') ?? '',
                mensaje: $this->childText($m, 'mensaje') ?? '',
                tipo: $this->childText($m, 'tipo') ?? '',
                informacionAdicional: $this->childText($m, 'informacionAdicional') ?? '',
            );
        }
        return $out;
    }
}
```

> Nota para el implementador: ajusta los XPath si algún test no pasa, pero mantén el enfoque `local-name()` (namespace-agnóstico). El nodo `<mensaje>` aparece dos veces como concepto (el contenedor `mensajes` y el campo de texto `mensaje`); por eso el predicado filtra nodos `mensaje` que tengan hijos `identificador`/`mensaje` (los elementos-registro), no el campo de texto. Si el filtro resulta frágil, usa `//*[local-name()="mensajes"]/*[local-name()="mensaje"]`.

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Transport/SoapResponseParserTest.php`
Expected: PASS (3 tests). Si algún XPath falla, corregirlo conservando el enfoque namespace-agnóstico.

- [ ] **Step 5: Commit**

```bash
git add src/Transport/SoapResponseParser.php tests/Unit/Transport/SoapResponseParserTest.php
git commit -m "feat: add namespace-agnostic SoapResponseParser"
```

---

### Task 4: `Psr18SoapTransport` + `FakePsr18Client`

**Files:**
- Create: `src/Transport/Psr18SoapTransport.php`, `tests/Support/FakePsr18Client.php`
- Test: `tests/Unit/Transport/Psr18SoapTransportTest.php`

- [ ] **Step 1: Escribir el doble de prueba y el test que falla**

`tests/Support/FakePsr18Client.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

final class FakePsr18Client implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(private readonly string $responseBody, private readonly int $status = 200)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;
        return (new Psr17Factory())->createResponse($this->status)
            ->withBody((new Psr17Factory())->createStream($this->responseBody));
    }
}
```

`tests/Unit/Transport/Psr18SoapTransportTest.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\Psr18SoapTransport;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Tests\Support\FakePsr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;

class Psr18SoapTransportTest extends TestCase
{
    private function transport(string $responseBody): Psr18SoapTransport
    {
        $factory = new Psr17Factory();
        return new Psr18SoapTransport(new FakePsr18Client($responseBody), $factory, $factory);
    }

    public function test_enviar_posts_to_reception_endpoint_and_parses(): void
    {
        $body = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><r><RespuestaRecepcionComprobante><estado>RECIBIDA</estado></RespuestaRecepcionComprobante></r></soap:Body></soap:Envelope>';
        $client = new FakePsr18Client($body);
        $factory = new Psr17Factory();
        $transport = new Psr18SoapTransport($client, $factory, $factory);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('RECIBIDA', $outcome->estado);
        $this->assertSame('POST', $client->lastRequest->getMethod());
        $this->assertStringContainsString('celcer.sri.gob.ec', (string) $client->lastRequest->getUri());
        $this->assertStringContainsString('RecepcionComprobantesOffline', (string) $client->lastRequest->getUri());
    }

    public function test_autorizar_uses_production_endpoint_for_produccion(): void
    {
        $body = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><RespuestaAutorizacionComprobante><autorizaciones><autorizacion><estado>AUTORIZADO</estado><numeroAutorizacion>123</numeroAutorizacion></autorizacion></autorizaciones></RespuestaAutorizacionComprobante></soap:Body></soap:Envelope>';
        $client = new FakePsr18Client($body);
        $factory = new Psr17Factory();
        $transport = new Psr18SoapTransport($client, $factory, $factory);

        $outcome = $transport->autorizar('2601...819', Ambiente::Produccion);

        $this->assertSame('AUTORIZADO', $outcome->estado);
        $this->assertSame('123', $outcome->numeroAutorizacion);
        $this->assertStringContainsString('cel.sri.gob.ec', (string) $client->lastRequest->getUri());
        $this->assertStringNotContainsString('celcer', (string) $client->lastRequest->getUri());
    }
}
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Transport/Psr18SoapTransportTest.php`
Expected: FAIL con `Class "Teran\Sri\Transport\Psr18SoapTransport" not found`.

- [ ] **Step 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Psr18SoapTransport implements SriTransportInterface
{
    private const ENDPOINTS = [
        'recepcion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
        ],
        'autorizacion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
        ],
    ];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly SoapEnvelopeBuilder $envelopes = new SoapEnvelopeBuilder(),
        private readonly SoapResponseParser $parser = new SoapResponseParser(),
    ) {
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $body = $this->post(
            self::ENDPOINTS['recepcion'][$this->ambienteKey($ambiente)],
            $this->envelopes->reception($signedXml),
        );
        return $this->parser->parseReception($body);
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $body = $this->post(
            self::ENDPOINTS['autorizacion'][$this->ambienteKey($ambiente)],
            $this->envelopes->authorization($claveAcceso),
        );
        return $this->parser->parseAuthorization($body);
    }

    private function ambienteKey(Ambiente $ambiente): string
    {
        return $ambiente === Ambiente::Produccion ? 'produccion' : 'pruebas';
    }

    private function post(string $url, string $soapBody): string
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withHeader('SOAPAction', '')
            ->withBody($this->streamFactory->createStream($soapBody));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new CommunicationException('Error de comunicación con el SRI: ' . $e->getMessage());
        }

        if ($response->getStatusCode() >= 400) {
            throw new CommunicationException('El SRI respondió HTTP ' . $response->getStatusCode());
        }

        return (string) $response->getBody();
    }
}
```

- [ ] **Step 4: Correr y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Transport/Psr18SoapTransportTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Suite completa**

Run: `./vendor/bin/phpunit`
Expected: verde, sin regresiones, 1 skip pre-existente.

- [ ] **Step 6: Commit**

```bash
git add src/Transport/Psr18SoapTransport.php tests/Support/FakePsr18Client.php tests/Unit/Transport/Psr18SoapTransportTest.php
git commit -m "feat: add Psr18SoapTransport (SOAP over PSR-18, TLS via injected client)"
```

---

## Self-Review

**1. Cobertura (spec §3.1, §4.5):**
- `SriTransportInterface` implementado real (PSR-18) → Task 4. ✅
- Envelope SOAP de los WS offline (recepción/autorización) → Task 2. ✅
- Parseo de respuestas namespace-agnóstico → Task 3. ✅
- TLS: lo aporta el cliente PSR-18 inyectado por el usuario (Guzzle/Symfony verifican por defecto). Endpoints HTTPS hardcodeados. ✅
- Núcleo agnóstico: solo depende de interfaces PSR; cliente concreto inyectado. ✅

**2. Limitación explícita (documentada arriba):** los tests verifican estructura + parseo contra el formato documentado, NO contra el SRI real → requiere smoke test en ambiente de pruebas antes de producción.

**3. Placeholders:** ninguno; código completo.

**4. Consistencia de tipos:** `Psr18SoapTransport implements SriTransportInterface`; `enviar(string,Ambiente):ReceptionOutcome`, `autorizar(string,Ambiente):AuthorizationOutcome`; usa `SoapEnvelopeBuilder`/`SoapResponseParser`. ✅

**5. Aditivo:** solo `src/Transport/`, `tests/`, `composer.json`; no toca el 1.x. ✅

**6. Pendiente Fase 1.7:** shim legacy `SRI` que delega en `SriClient` + `Psr18SoapTransport` (con auto-discovery del cliente PSR-18), para que `facturaFromArray()` siga funcionando.
