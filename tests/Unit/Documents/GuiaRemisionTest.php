<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Destinatario;
use Teran\Sri\Documents\GuiaRemision;
use Teran\Sri\Exceptions\ValidationException;

class GuiaRemisionTest extends TestCase
{
    private function validData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'EMPRESA TRANSPORTISTA S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Av. Amazonas N1-01, Quito',
            ],
            'infoGuiaRemision' => [
                'dirEstablecimiento' => 'Calle Falsa 123',
                'dirPartida' => 'Bodega Principal, Quito',
                'razonSocialTransportista' => 'TRANSPORTES EC S.A.',
                'tipoIdentificacionTransportista' => '04',
                'rucTransportista' => '1790011002001',
                'obligadoContabilidad' => 'SI',
                'fechaIniTransporte' => '10/02/2026',
                'fechaFinTransporte' => '11/02/2026',
                'placa' => 'ABC-1234',
            ],
            'destinatarios' => [
                [
                    'identificacionDestinatario' => '0912345678001',
                    'razonSocialDestinatario' => 'CLIENTE DESTINO S.A.',
                    'dirDestinatario' => 'Av. 9 de Octubre, Guayaquil',
                    'motivoTraslado' => 'Venta',
                    'codEstabDestino' => '001',
                    'ruta' => 'Quito-Guayaquil',
                    'codDocSustento' => '01',
                    'numDocSustento' => '001-001-000000100',
                    'numAutDocSustento' => '0123456789',
                    'fechaEmisionDocSustento' => '10/02/2026',
                    'detalles' => [
                        [
                            'codigoInterno' => 'PROD001',
                            'descripcion' => 'Producto de prueba',
                            'cantidad' => '10.000000',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_from_array_builds_aggregate(): void
    {
        $gr = GuiaRemision::fromArray($this->validData());

        $this->assertSame('1790011001001', $gr->infoTributaria->ruc);
        $this->assertSame('TRANSPORTES EC S.A.', $gr->razonSocialTransportista);
        $this->assertSame('10/02/2026', $gr->fechaIniTransporte);
        $this->assertSame('ABC-1234', $gr->placa);
        $this->assertCount(1, $gr->destinatarios);

        $dest = $gr->destinatarios[0];
        $this->assertInstanceOf(Destinatario::class, $dest);
        $this->assertSame('CLIENTE DESTINO S.A.', $dest->razonSocialDestinatario);
        $this->assertCount(1, $dest->detalles);
        $this->assertSame('PROD001', $dest->detalles[0]['codigoInterno']);
    }

    public function test_rejects_without_destinatarios(): void
    {
        $data = $this->validData();
        $data['destinatarios'] = [];

        $this->expectException(ValidationException::class);
        GuiaRemision::fromArray($data);
    }

    public function test_rejects_invalid_fecha_ini_transporte(): void
    {
        $data = $this->validData();
        $data['infoGuiaRemision']['fechaIniTransporte'] = '2026-02-10';

        $this->expectException(ValidationException::class);
        GuiaRemision::fromArray($data);
    }
}
