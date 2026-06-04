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
