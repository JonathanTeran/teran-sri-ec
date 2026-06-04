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
