<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Emission;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Emission\Message;
use Teran\Sri\Emission\EmissionStatus;

class MessageTest extends TestCase
{
    public function test_message_holds_sri_fields(): void
    {
        $m = new Message('43', 'RUC del emisor se encuentra...', 'ERROR', 'info extra');
        $this->assertSame('43', $m->identificador);
        $this->assertSame('ERROR', $m->tipo);
        $this->assertSame('info extra', $m->informacionAdicional);
    }

    public function test_status_cases_exist(): void
    {
        $this->assertNotNull(EmissionStatus::Authorized);
        $this->assertNotNull(EmissionStatus::Rejected);
        $this->assertNotNull(EmissionStatus::InProcess);
    }
}
