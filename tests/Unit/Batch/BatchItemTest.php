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
