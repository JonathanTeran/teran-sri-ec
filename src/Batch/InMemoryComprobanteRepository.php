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
