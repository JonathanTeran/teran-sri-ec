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

    private function throttleKey(BatchItem $item): string
    {
        // El RUC ocupa 13 dígitos a partir de la posición 10 en la clave de acceso (49 díg).
        return strlen($item->claveAcceso) >= 23 ? substr($item->claveAcceso, 10, 13) : 'sri';
    }

    /** Avanza un item UN paso. Idempotente: los terminales se devuelven sin cambios. */
    public function step(BatchItem $item): BatchItem
    {
        if ($item->isTerminal()) {
            return $item;
        }

        try {
            if ($item->state === ComprobanteState::Pending) {
                // Nota: si el proceso falla entre un `enviar` exitoso y el `repository->save`,
                // una re-ejecución reenviará el item (todavía Pending). La reconciliación real
                // (consultar autorización antes de re-enviar) es un refinamiento planeado.
                $this->rateLimiter->throttle($this->throttleKey($item));
                $r = $this->transport->enviar($item->signedXml, $this->ambiente);
                return $r->estado === 'RECIBIDA' ? $item->markSent($r->mensajes) : $item->markRejected($r->mensajes);
            }

            // Sent o InProcess → consultar autorización
            $this->rateLimiter->throttle($this->throttleKey($item));
            $a = $this->transport->autorizar($item->claveAcceso, $this->ambiente);
            return match (strtoupper($a->estado)) {
                'AUTORIZADO' => $item->markAuthorized($a->numeroAutorizacion, $a->comprobante, $a->mensajes),
                'EN PROCESO', 'EN PROCESAMIENTO' => $this->retryPolicy->shouldRetry($item->attempts + 1)
                    ? $item->markInProcess($a->mensajes)
                    : $item->incrementAttempts()->markFailed($a->mensajes),
                default => $item->markRejected($a->mensajes), // NO AUTORIZADO
            };
        } catch (CommunicationException $e) {
            $next = $item->incrementAttempts();
            return $this->retryPolicy->shouldRetry($next->attempts)
                ? $next
                : $next->markFailed([new Message('', $e->getMessage())]);
        }
    }

    /**
     * Recorre los pendientes del repositorio avanzándolos paso a paso hasta que
     * no haya progreso de estado (los `InProcess` quedan a la espera para una
     * corrida posterior). Runner síncrono; un worker de cola lo invoca periódicamente.
     *
     * Nota: cuando otros items del lote siguen avanzando de estado, un item con
     * fallo transitorio puede consumir más de un slot de reintento por llamada a
     * `process()`. El adaptador de cola evita esto invocando `step()` por item.
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
