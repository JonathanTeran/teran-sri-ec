# Fase 2.1 — Modelo de estado + repositorio (masivo) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el modelo de estado del motor de envío masivo: una entidad `BatchItem` inmutable con su máquina de estados (`ComprobanteState`), una `RetryPolicy`, y el contrato de persistencia `ComprobanteRepositoryInterface` con una implementación de referencia `InMemoryComprobanteRepository`.

**Architecture:** Código nuevo bajo `src/Batch/`. El motor masivo opera sobre `(claveAcceso, signedXml)` — **agnóstico al tipo de comprobante** (el caller firma con cualquiera de los 5 serializadores). `BatchItem` es inmutable (transiciones devuelven copias), lo que hace el estado trivialmente testeable y seguro para *optimistic locking* en la impl de BD futura. Reusa `Emission\Message`. No toca el 1.x ni el resto del 2.0.

**Tech Stack:** PHP 8.2 (enums, readonly), PHPUnit 10.

---

## Contexto y alcance

Plan de la Fase 2 (parte 1 de 2). Cubre **estado + persistencia**. El `BatchProcessor` (motor que conduce la máquina de estados usando el transporte) y la API pública van en la Fase 2.2.

### Máquina de estados

```
Pending ──enviar RECIBIDA──► Sent ──autorizar AUTORIZADO──► Authorized ✓ (terminal)
   │                          │                              
   │                          ├─ EN_PROCESO ─► InProcess ──(re-poll)──► Authorized / Rejected
   │                          └─ NO_AUTORIZADO ───────────────────────► Rejected ✗ (terminal)
   └─ enviar DEVUELTA ──────────────────────────────────────────────► Rejected ✗ (terminal)
   (fallo transitorio + reintentos agotados) ───────────────────────► Failed ✗ (terminal)
```

### Mapa de archivos

- Create: `src/Batch/ComprobanteState.php` — enum de estados.
- Create: `src/Batch/BatchItem.php` — entidad inmutable + transiciones.
- Create: `src/Batch/RetryPolicy.php` — política de reintentos.
- Create: `src/Batch/ComprobanteRepositoryInterface.php` — contrato.
- Create: `src/Batch/InMemoryComprobanteRepository.php` — impl de referencia.
- Test: `tests/Unit/Batch/{BatchItemTest,RetryPolicyTest,InMemoryComprobanteRepositoryTest}.php`

---

### Task 1: `ComprobanteState` enum

**Files:** `src/Batch/ComprobanteState.php`, test en `BatchItemTest` (Task 2).

- [ ] **Step 1: Implementar** (no requiere test propio; se ejercita en Task 2)

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

enum ComprobanteState: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Authorized = 'AUTHORIZED';
    case Rejected = 'REJECTED';
    case InProcess = 'IN_PROCESS';
    case Failed = 'FAILED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Authorized, self::Rejected, self::Failed], true);
    }
}
```

- [ ] **Step 2: Verificar carga**

Run: `php -r "require 'vendor/autoload.php'; var_dump(Teran\Sri\Batch\ComprobanteState::Authorized->isTerminal());"` → `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add src/Batch/ComprobanteState.php
git commit -m "feat: add ComprobanteState enum (mass emission state machine)"
```

---

### Task 2: `BatchItem` (entidad inmutable + transiciones)

**Files:** `src/Batch/BatchItem.php`, `tests/Unit/Batch/BatchItemTest.php`

- [ ] **Step 1 (RED): test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Batch;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Batch\BatchItem;
use Teran\Sri\Batch\ComprobanteState;
use Teran\Sri\Emission\Message;

class BatchItemTest extends TestCase
{
    public function test_new_item_is_pending(): void
    {
        $item = new BatchItem('2601...819', '<factura/>');
        $this->assertSame(ComprobanteState::Pending, $item->state);
        $this->assertSame(0, $item->attempts);
        $this->assertFalse($item->isTerminal());
    }

    public function test_transitions_return_new_immutable_instances(): void
    {
        $item = new BatchItem('clave', '<xml/>');

        $sent = $item->markSent();
        $this->assertSame(ComprobanteState::Sent, $sent->state);
        $this->assertSame(ComprobanteState::Pending, $item->state); // original sin cambios

        $auth = $sent->markAuthorized('123', '<auth/>', []);
        $this->assertSame(ComprobanteState::Authorized, $auth->state);
        $this->assertSame('123', $auth->numeroAutorizacion);
        $this->assertTrue($auth->isTerminal());
    }

    public function test_in_process_increments_attempts(): void
    {
        $item = (new BatchItem('clave', '<xml/>'))->markSent();
        $p1 = $item->markInProcess([new Message('70', 'EN PROCESAMIENTO')]);
        $p2 = $p1->markInProcess([]);
        $this->assertSame(ComprobanteState::InProcess, $p2->state);
        $this->assertSame(2, $p2->attempts);
    }

    public function test_rejected_and_failed_are_terminal(): void
    {
        $item = new BatchItem('clave', '<xml/>');
        $this->assertTrue($item->markRejected([])->isTerminal());
        $this->assertTrue($item->markFailed([])->isTerminal());
    }
}
```

- [ ] **Step 2: Correr → FAIL** (`Class "...BatchItem" not found`).

- [ ] **Step 3 (GREEN): implementar**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

use Teran\Sri\Emission\Message;

/**
 * Entidad inmutable de un comprobante en el flujo masivo. Las transiciones
 * devuelven NUEVAS instancias (no mutan), lo que hace el estado seguro y testeable.
 */
final class BatchItem
{
    /** @param Message[] $messages */
    public function __construct(
        public readonly string $claveAcceso,
        public readonly string $signedXml,
        public readonly ComprobanteState $state = ComprobanteState::Pending,
        public readonly int $attempts = 0,
        public readonly ?string $numeroAutorizacion = null,
        public readonly ?string $authorizedXml = null,
        public readonly array $messages = [],
    ) {
    }

    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
    }

    /** @param Message[] $messages */
    private function with(ComprobanteState $state, array $messages, int $attempts, ?string $num = null, ?string $xml = null): self
    {
        return new self($this->claveAcceso, $this->signedXml, $state, $attempts, $num ?? $this->numeroAutorizacion, $xml ?? $this->authorizedXml, $messages);
    }

    /** @param Message[] $messages */
    public function markSent(array $messages = []): self
    {
        return $this->with(ComprobanteState::Sent, $messages, $this->attempts);
    }

    /** @param Message[] $messages */
    public function markAuthorized(?string $numeroAutorizacion, ?string $authorizedXml, array $messages): self
    {
        return $this->with(ComprobanteState::Authorized, $messages, $this->attempts, $numeroAutorizacion, $authorizedXml);
    }

    /** @param Message[] $messages */
    public function markRejected(array $messages): self
    {
        return $this->with(ComprobanteState::Rejected, $messages, $this->attempts);
    }

    /** @param Message[] $messages */
    public function markInProcess(array $messages): self
    {
        return $this->with(ComprobanteState::InProcess, $messages, $this->attempts + 1);
    }

    /** @param Message[] $messages */
    public function markFailed(array $messages): self
    {
        return $this->with(ComprobanteState::Failed, $messages, $this->attempts);
    }

    public function incrementAttempts(): self
    {
        return $this->with($this->state, $this->messages, $this->attempts + 1);
    }
}
```

- [ ] **Step 4: Correr → PASS** (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Batch/BatchItem.php tests/Unit/Batch/BatchItemTest.php
git commit -m "feat: add immutable BatchItem with state transitions"
```

---

### Task 3: `RetryPolicy`

**Files:** `src/Batch/RetryPolicy.php`, `tests/Unit/Batch/RetryPolicyTest.php`

- [ ] **Step 1 (RED): test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Batch;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Batch\RetryPolicy;

class RetryPolicyTest extends TestCase
{
    public function test_allows_attempts_up_to_max(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);
        $this->assertTrue($policy->shouldRetry(1));
        $this->assertTrue($policy->shouldRetry(2));
        $this->assertFalse($policy->shouldRetry(3));
        $this->assertFalse($policy->shouldRetry(4));
    }

    public function test_backoff_grows(): void
    {
        $policy = new RetryPolicy(baseDelaySeconds: 2);
        $this->assertSame(2, $policy->delaySeconds(1));
        $this->assertSame(4, $policy->delaySeconds(2));
        $this->assertSame(8, $policy->delaySeconds(3));
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
 * Política de reintentos para fallos transitorios y estado EN_PROCESO.
 * Backoff exponencial: delay = baseDelaySeconds * 2^(attempt-1), con tope.
 */
final class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts = 5,
        public readonly int $baseDelaySeconds = 3,
        public readonly int $maxDelaySeconds = 600,
    ) {
    }

    public function shouldRetry(int $attempts): bool
    {
        return $attempts < $this->maxAttempts;
    }

    public function delaySeconds(int $attempt): int
    {
        $delay = $this->baseDelaySeconds * (2 ** ($attempt - 1));
        return (int) min($delay, $this->maxDelaySeconds);
    }
}
```

- [ ] **Step 4: Correr → PASS.** **Step 5: Commit** `feat: add RetryPolicy (exponential backoff)`.

---

### Task 4: `ComprobanteRepositoryInterface` + `InMemoryComprobanteRepository`

**Files:** `src/Batch/ComprobanteRepositoryInterface.php`, `src/Batch/InMemoryComprobanteRepository.php`, `tests/Unit/Batch/InMemoryComprobanteRepositoryTest.php`

- [ ] **Step 1 (RED): test**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Batch;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Batch\InMemoryComprobanteRepository;
use Teran\Sri\Batch\BatchItem;
use Teran\Sri\Batch\ComprobanteState;

class InMemoryComprobanteRepositoryTest extends TestCase
{
    public function test_save_find_and_upsert_by_clave(): void
    {
        $repo = new InMemoryComprobanteRepository();
        $repo->save(new BatchItem('clave-1', '<a/>'));
        $this->assertSame(ComprobanteState::Pending, $repo->find('clave-1')->state);

        // upsert: mismo claveAcceso reemplaza
        $repo->save((new BatchItem('clave-1', '<a/>'))->markSent());
        $this->assertSame(ComprobanteState::Sent, $repo->find('clave-1')->state);
        $this->assertNull($repo->find('inexistente'));
    }

    public function test_pending_excludes_terminal(): void
    {
        $repo = new InMemoryComprobanteRepository();
        $repo->save(new BatchItem('a', '<a/>'));                       // Pending
        $repo->save((new BatchItem('b', '<b/>'))->markSent());          // Sent (no terminal)
        $repo->save((new BatchItem('c', '<c/>'))->markAuthorized('1', null, [])); // terminal
        $repo->save((new BatchItem('d', '<d/>'))->markRejected([]));    // terminal

        $pendingClaves = array_map(fn($i) => $i->claveAcceso, $repo->pending());
        sort($pendingClaves);
        $this->assertSame(['a', 'b'], $pendingClaves);
    }

    public function test_status_counts(): void
    {
        $repo = new InMemoryComprobanteRepository();
        $repo->save((new BatchItem('a', '<a/>'))->markAuthorized('1', null, []));
        $repo->save((new BatchItem('b', '<b/>'))->markAuthorized('2', null, []));
        $repo->save((new BatchItem('c', '<c/>'))->markRejected([]));

        $counts = $repo->statusCounts();
        $this->assertSame(2, $counts['AUTHORIZED']);
        $this->assertSame(1, $counts['REJECTED']);
    }
}
```

- [ ] **Step 2: Correr → FAIL.**

- [ ] **Step 3 (GREEN): implementar**

`src/Batch/ComprobanteRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

interface ComprobanteRepositoryInterface
{
    public function save(BatchItem $item): void;

    public function find(string $claveAcceso): ?BatchItem;

    /** @return BatchItem[] no-terminales (Pending, Sent, InProcess) */
    public function pending(): array;

    /** @return array<string,int> conteo por estado (clave = ComprobanteState->value) */
    public function statusCounts(): array;
}
```

`src/Batch/InMemoryComprobanteRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

final class InMemoryComprobanteRepository implements ComprobanteRepositoryInterface
{
    /** @var array<string,BatchItem> */
    private array $items = [];

    public function save(BatchItem $item): void
    {
        $this->items[$item->claveAcceso] = $item;
    }

    public function find(string $claveAcceso): ?BatchItem
    {
        return $this->items[$claveAcceso] ?? null;
    }

    public function pending(): array
    {
        return array_values(array_filter($this->items, fn(BatchItem $i) => !$i->isTerminal()));
    }

    public function statusCounts(): array
    {
        $counts = [];
        foreach ($this->items as $item) {
            $key = $item->state->value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        return $counts;
    }
}
```

- [ ] **Step 4: Correr → PASS.** **Step 5: suite completa verde + commit** `feat: add ComprobanteRepository interface + in-memory implementation`.

---

## Self-Review

**1. Cobertura (spec §5.1-5.3):** máquina de estados (`ComprobanteState`+`BatchItem`), reintentos (`RetryPolicy`), persistencia (`ComprobanteRepositoryInterface`+`InMemory`). ✅ El `BatchProcessor` y la API van en 2.2.

**2. Agnóstico al tipo:** `BatchItem` opera sobre `(claveAcceso, signedXml)` → sirve para los 5 comprobantes (el caller firma). ✅

**3. Inmutabilidad:** transiciones devuelven copias; seguro para *optimistic locking* en la impl de BD futura. ✅

**4. Placeholders:** ninguno; código completo.

**5. Aditivo:** solo `src/Batch/` + tests; reusa `Emission\Message`; no toca nada existente. ✅

**6. Pendiente 2.2:** `BatchProcessor` (conduce la máquina vía `SriTransportInterface`, idempotente, con `RetryPolicy`) + `RateLimiter` + API pública (`BatchEmitter`: add/run/status).
