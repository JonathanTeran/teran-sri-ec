<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;

class OutcomeTest extends TestCase
{
    public function test_reception_outcome_holds_estado_and_messages(): void
    {
        $o = new ReceptionOutcome('RECIBIDA', []);
        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame([], $o->mensajes);
    }

    public function test_authorization_outcome_holds_fields(): void
    {
        $o = new AuthorizationOutcome('AUTORIZADO', '1234567890', '2026-01-26T10:00:00-05:00', '<xml/>', []);
        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('1234567890', $o->numeroAutorizacion);
    }
}
