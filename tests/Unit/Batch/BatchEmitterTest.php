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
