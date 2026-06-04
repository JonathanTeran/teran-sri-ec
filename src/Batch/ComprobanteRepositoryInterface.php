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
