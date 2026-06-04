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
