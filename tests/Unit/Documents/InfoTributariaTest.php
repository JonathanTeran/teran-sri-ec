<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\InfoTributaria;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\ValidationException;

class InfoTributariaTest extends TestCase
{
    public function test_from_array_maps_and_resolves_ambiente(): void
    {
        $info = InfoTributaria::fromArray([
            'ambiente' => '1',
            'razonSocial' => 'MI EMPRESA S.A.',
            'ruc' => '1790011001001',
            'estab' => '001',
            'ptoEmi' => '001',
            'secuencial' => '000000001',
            'dirMatriz' => 'Quito, Ecuador',
        ]);

        $this->assertSame(Ambiente::Pruebas, $info->ambiente);
        $this->assertSame('1790011001001', $info->ruc);
        $this->assertSame('001', $info->estab);
    }

    public function test_rejects_ruc_with_wrong_length(): void
    {
        $this->expectException(ValidationException::class);
        InfoTributaria::fromArray([
            'ambiente' => '1',
            'razonSocial' => 'X',
            'ruc' => '123',
            'estab' => '001',
            'ptoEmi' => '001',
            'secuencial' => '000000001',
            'dirMatriz' => 'Quito',
        ]);
    }
}
