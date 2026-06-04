# Fase 2.2 — BatchProcessor + API de envío masivo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el motor que conduce la máquina de estados del envío masivo: `BatchProcessor` (idempotente, con reintentos y rate-limit, sobre `SriTransportInterface`) y la API pública `BatchEmitter` (`add` / `run` / `status` / `result`).

**Architecture:** Código nuevo bajo `src/Batch/`. `BatchProcessor::step()` avanza UN `BatchItem` un paso (enviar→autorizar), idempotente (los terminales no se tocan). `process()` recorre los pendientes del repositorio hasta que no haya progreso (runner síncrono; un worker de cola lo llama periódicamente). `BatchEmitter` es la fachada. Reusa `SriTransportInterface`, `Catalogs2\Ambiente`, `Emission\Message`, y todo el modelo de Fase 2.1. No toca nada existente.

**Tech Stack:** PHP 8.2, PHPUnit 10. Tests con `FakeTransport` (Fase 1.5) — sin red.

---

## Contexto y alcance

Fase 2 parte 2 de 2. Cierra el núcleo del envío masivo. El adapter Laravel (cola+workers, EloquentRepository) es Fase 3.

### Mapa de archivos

- Create: `src/Batch/RateLimiterInterface.php`, `src/Batch/NullRateLimiter.php`
- Create: `src/Batch/BatchProcessor.php`
- Create: `src/Batch/BatchEmitter.php`
- Create: `tests/Support/ThrowingTransport.php`
- Test: `tests/Unit/Batch/{BatchProcessorTest,BatchEmitterTest}.php`

---

### Task 1: `RateLimiterInterface` + `NullRateLimiter`

**Files:** `src/Batch/RateLimiterInterface.php`, `src/Batch/NullRateLimiter.php`

- [ ] **Step 1: Implementar** (interfaz trivial + no-op; se ejercita vía el procesador)

`src/Batch/RateLimiterInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

/**
 * Limita la tasa de llamadas al SRI. `throttle()` bloquea lo necesario antes
 * de cada llamada (implementaciones reales: token-bucket global / por RUC).
 */
interface RateLimiterInterface
{
    public function throttle(string $key = 'sri'): void;
}
```

`src/Batch/NullRateLimiter.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

final class NullRateLimiter implements RateLimiterInterface
{
    public function throttle(string $key = 'sri'): void
    {
        // no-op
    }
}
```

- [ ] **Step 2: Commit** `feat: add RateLimiterInterface + NullRateLimiter`.

---

### Task 2: `BatchProcessor`

**Files:** `src/Batch/BatchProcessor.php`, `tests/Support/ThrowingTransport.php`, `tests/Unit/Batch/BatchProcessorTest.php`

- [ ] **Step 1: Crear el doble que lanza** `tests/Support/ThrowingTransport.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;

final class ThrowingTransport implements SriTransportInterface
{
    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        throw new CommunicationException('fallo de red simulado');
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        throw new CommunicationException('fallo de red simulado');
    }
}
```

- [ ] **Step 2 (RED): test del procesador**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Batch;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Batch\BatchProcessor;
use Teran\Sri\Batch\BatchItem;
use Teran\Sri\Batch\ComprobanteState;
use Teran\Sri\Batch\InMemoryComprobanteRepository;
use Teran\Sri\Batch\RetryPolicy;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Emission\Message;
use Teran\Sri\Tests\Support\FakeTransport;
use Teran\Sri\Tests\Support\ThrowingTransport;

class BatchProcessorTest extends TestCase
{
    private function processor(FakeTransport|ThrowingTransport $t, ?RetryPolicy $policy = null): BatchProcessor
    {
        return new BatchProcessor($t, Ambiente::Pruebas, $policy ?? new RetryPolicy());
    }

    public function test_authorized_flow_reaches_authorized(): void
    {
        $t = new FakeTransport(new ReceptionOutcome('RECIBIDA', []), new AuthorizationOutcome('AUTORIZADO', '123', null, '<a/>', []));
        $repo = new InMemoryComprobanteRepository();
        $repo->save(new BatchItem('clave', '<xml/>'));

        $this->processor($t)->process($repo);

        $item = $repo->find('clave');
        $this->assertSame(ComprobanteState::Authorized, $item->state);
        $this->assertSame('123', $item->numeroAutorizacion);
    }

    public function test_devuelta_at_reception_is_rejected_and_not_authorized(): void
    {
        $t = new FakeTransport(new ReceptionOutcome('DEVUELTA', [new Message('43', 'RUC inválido', 'ERROR')]), new AuthorizationOutcome('AUTORIZADO', '999'));
        $repo = new InMemoryComprobanteRepository();
        $repo->save(new BatchItem('clave', '<xml/>'));

        $this->processor($t)->process($repo);

        $this->assertSame(ComprobanteState::Rejected, $repo->find('clave')->state);
        $this->assertNull($repo->find('clave')->numeroAutorizacion); // nunca autorizó
    }

    public function test_en_proceso_stays_in_process(): void
    {
        $t = new FakeTransport(new ReceptionOutcome('RECIBIDA', []), new AuthorizationOutcome('EN PROCESO'));
        $repo = new InMemoryComprobanteRepository();
        $repo->save(new BatchItem('clave', '<xml/>'));

        $this->processor($t)->process($repo);

        $this->assertSame(ComprobanteState::InProcess, $repo->find('clave')->state);
    }

    public function test_transient_failure_exhausts_retries_to_failed(): void
    {
        $repo = new InMemoryComprobanteRepository();
        $repo->save(new BatchItem('clave', '<xml/>'));

        $this->processor(new ThrowingTransport(), new RetryPolicy(maxAttempts: 1))->process($repo);

        $this->assertSame(ComprobanteState::Failed, $repo->find('clave')->state);
    }

    public function test_idempotent_terminal_items_are_not_reprocessed(): void
    {
        $t = new FakeTransport(new ReceptionOutcome('RECIBIDA', []), new AuthorizationOutcome('AUTORIZADO', '123'));
        $repo = new InMemoryComprobanteRepository();
        $repo->save((new BatchItem('clave', '<xml/>'))->markAuthorized('original', null, []));

        $processor = $this->processor($t);
        $processor->process($repo);
        $processor->process($repo); // segunda corrida

        // sigue con su autorización original; no se re-procesó (idempotencia sobre terminal)
        $this->assertSame('original', $repo->find('clave')->numeroAutorizacion);
    }
}
```

- [ ] **Step 3: Correr → FAIL** (`Class "...BatchProcessor" not found`).

- [ ] **Step 4 (GREEN): implementar `BatchProcessor`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Emission\Message;
use Teran\Sri\Exceptions\CommunicationException;

/**
 * Conduce la máquina de estados del envío masivo de forma idempotente:
 * enviar → autorizar, con reintentos (RetryPolicy) y rate-limit.
 */
final class BatchProcessor
{
    public function __construct(
        private readonly SriTransportInterface $transport,
        private readonly Ambiente $ambiente,
        private readonly RetryPolicy $retryPolicy = new RetryPolicy(),
        private readonly RateLimiterInterface $rateLimiter = new NullRateLimiter(),
    ) {
    }

    /** Avanza un item UN paso. Idempotente: los terminales se devuelven sin cambios. */
    public function step(BatchItem $item): BatchItem
    {
        if ($item->isTerminal()) {
            return $item;
        }

        try {
            if ($item->state === ComprobanteState::Pending) {
                $this->rateLimiter->throttle();
                $r = $this->transport->enviar($item->signedXml, $this->ambiente);
                return $r->estado === 'RECIBIDA' ? $item->markSent($r->mensajes) : $item->markRejected($r->mensajes);
            }

            // Sent o InProcess → consultar autorización
            $this->rateLimiter->throttle();
            $a = $this->transport->autorizar($item->claveAcceso, $this->ambiente);
            return match (strtoupper($a->estado)) {
                'AUTORIZADO' => $item->markAuthorized($a->numeroAutorizacion, $a->comprobante, $a->mensajes),
                'EN PROCESO', 'EN PROCESAMIENTO' => $this->retryPolicy->shouldRetry($item->attempts + 1)
                    ? $item->markInProcess($a->mensajes)
                    : $item->markFailed($a->mensajes),
                default => $item->markRejected($a->mensajes), // NO AUTORIZADO
            };
        } catch (CommunicationException $e) {
            $next = $item->incrementAttempts();
            return $this->retryPolicy->shouldRetry($next->attempts)
                ? $next
                : $item->markFailed([new Message('', $e->getMessage())]);
        }
    }

    /**
     * Recorre los pendientes del repositorio avanzándolos paso a paso hasta que
     * no haya progreso de estado (los `InProcess` quedan a la espera para una
     * corrida posterior). Runner síncrono; un worker de cola lo invoca periódicamente.
     */
    public function process(ComprobanteRepositoryInterface $repository, int $maxPasses = 20): void
    {
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $pending = $repository->pending();
            if ($pending === []) {
                return;
            }
            $stateChanged = false;
            foreach ($pending as $item) {
                $next = $this->step($item);
                if ($next->state !== $item->state || $next->attempts !== $item->attempts) {
                    $repository->save($next);
                }
                if ($next->state !== $item->state) {
                    $stateChanged = true;
                }
            }
            if (!$stateChanged) {
                return; // sin progreso (p. ej. todo EN PROCESO) — reintentar más tarde
            }
        }
    }
}
```

- [ ] **Step 5: Correr → PASS** (5 tests). **Step 6: Commit** `feat: add BatchProcessor (idempotent state-machine engine)`.

---

### Task 3: `BatchEmitter` (API pública)

**Files:** `src/Batch/BatchEmitter.php`, `tests/Unit/Batch/BatchEmitterTest.php`

- [ ] **Step 1 (RED): test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Batch;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Batch\BatchEmitter;
use Teran\Sri\Batch\BatchProcessor;
use Teran\Sri\Batch\InMemoryComprobanteRepository;
use Teran\Sri\Batch\ComprobanteState;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Tests\Support\FakeTransport;

class BatchEmitterTest extends TestCase
{
    private function emitter(): BatchEmitter
    {
        $t = new FakeTransport(new ReceptionOutcome('RECIBIDA', []), new AuthorizationOutcome('AUTORIZADO', '123', null, '<a/>', []));
        return new BatchEmitter(new BatchProcessor($t, Ambiente::Pruebas), new InMemoryComprobanteRepository());
    }

    public function test_add_run_status_and_result(): void
    {
        $emitter = $this->emitter();
        $emitter->add('clave-1', '<f1/>');
        $emitter->add('clave-2', '<f2/>');

        $emitter->run();

        $this->assertSame(2, $emitter->status()['AUTHORIZED']);
        $this->assertSame(ComprobanteState::Authorized, $emitter->result('clave-1')->state);
        $this->assertSame('123', $emitter->result('clave-2')->numeroAutorizacion);
    }

    public function test_add_is_idempotent_by_clave(): void
    {
        $emitter = $this->emitter();
        $emitter->add('clave-1', '<f1/>');
        $emitter->add('clave-1', '<otro/>'); // misma clave: no duplica
        $emitter->run();

        $counts = $emitter->status();
        $this->assertSame(1, array_sum($counts));
    }
}
```

- [ ] **Step 2: Correr → FAIL.**

- [ ] **Step 3 (GREEN): implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

/**
 * Fachada del envío masivo: agrega comprobantes firmados, procesa y consulta estado.
 * El firmado por tipo lo hace el caller (serializador + XadesSigner de Fase 1.x);
 * este motor es agnóstico al tipo (opera sobre claveAcceso + XML firmado).
 */
final class BatchEmitter
{
    public function __construct(
        private readonly BatchProcessor $processor,
        private readonly ComprobanteRepositoryInterface $repository = new InMemoryComprobanteRepository(),
    ) {
    }

    /** Agrega un comprobante firmado. Idempotente por clave de acceso (no duplica). */
    public function add(string $claveAcceso, string $signedXml): void
    {
        if ($this->repository->find($claveAcceso) === null) {
            $this->repository->save(new BatchItem($claveAcceso, $signedXml));
        }
    }

    /** Procesa todos los pendientes (síncrono). Re-llamable: reanuda donde quedó. */
    public function run(int $maxPasses = 20): void
    {
        $this->processor->process($this->repository, $maxPasses);
    }

    /** @return array<string,int> conteo por estado */
    public function status(): array
    {
        return $this->repository->statusCounts();
    }

    public function result(string $claveAcceso): ?BatchItem
    {
        return $this->repository->find($claveAcceso);
    }
}
```

- [ ] **Step 4: Correr → PASS.** **Step 5: suite completa verde + commit** `feat: add BatchEmitter (mass emission facade)`.

---

## Self-Review

**1. Cobertura (spec §5):**
- Motor que conduce la máquina de estados, idempotente → `BatchProcessor` (Task 2). ✅
- Reintentos con criterio (transitorios + EN_PROCESO) → `step()` + `RetryPolicy`. ✅
- Rate-limit → `RateLimiterInterface`/`NullRateLimiter` (impl real token-bucket en el adapter). ✅
- Reanudable → `process()` recorre pendientes; re-llamable. ✅
- API pública → `BatchEmitter` (add/run/status/result). ✅

**2. Idempotencia:** los terminales se devuelven sin cambios (`step` early-return); `add` no duplica por clave. ✅ (Reconciliación tras-crash más fina — consultar autorización antes de re-enviar un Pending — es un refinamiento futuro; la persistencia por paso minimiza la ventana.)

**3. Placeholders:** ninguno; código completo.

**4. Consistencia:** `BatchProcessor::{step(BatchItem):BatchItem, process(repo,int):void}`; `BatchEmitter::{add,run,status,result}`; reusa `SriTransportInterface`, `RetryPolicy`, `ComprobanteRepositoryInterface`. ✅

**5. Aditivo:** solo `src/Batch/` + tests; no toca nada. ✅

**6. Pendiente (Fase 3):** adapter Laravel — `EloquentComprobanteRepository` (con `version` para optimistic lock), jobs de cola (`EmitJob`/`AuthorizeJob` que llaman a `step`), `RateLimiter` real (cache/Redis), comandos Artisan. Más CI/PHPStan/Infection/docs.
