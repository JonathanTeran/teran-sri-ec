<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\DocSustento;
use Teran\Sri\Documents\Retencion;
use Teran\Sri\Exceptions\ValidationException;

class RetencionTest extends TestCase
{
    private function validData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'AGENTE RETENCION S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Av. Amazonas N1-01, Quito',
            ],
            'infoCompRetencion' => [
                'fechaEmision' => '10/02/2026',
                'dirEstablecimiento' => 'Calle Falsa 123',
                'obligadoContabilidad' => 'SI',
                'tipoIdentificacionSujetoRetenido' => '04',
                'razonSocialSujetoRetenido' => 'PROVEEDOR EC S.A.',
                'identificacionSujetoRetenido' => '1790011002001',
                'periodoFiscal' => '02/2026',
            ],
            'docsSustento' => [
                [
                    'codSustento' => '01',
                    'codDocSustento' => '01',
                    'numDocSustento' => '001-001-000000100',
                    'fechaEmisionDocSustento' => '05/02/2026',
                    'totalSinImpuestos' => '1000.00',
                    'importeTotal' => '1120.00',
                    'impuestosDocSustento' => [
                        [
                            'codImpuestoDocSustento' => '2',
                            'codigoPorcentaje' => '4',
                            'baseImponible' => '1000.00',
                            'tarifa' => '12.00',
                            'factorProporcionalidad' => '1.00',
                            'baseImponibleModificada' => '1000.00',
                            'valorImpuesto' => '120.00',
                        ],
                    ],
                    'retenciones' => [
                        [
                            'codigo' => '2',
                            'codigoRetencion' => '10',
                            'baseImponible' => '1000.00',
                            'porcentajeRetener' => '10',
                            'valorRetenido' => '100.00',
                        ],
                    ],
                    'pagos' => [
                        [
                            'formaPago' => '01',
                            'total' => '1020.00',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_from_array_builds_aggregate(): void
    {
        $ret = Retencion::fromArray($this->validData());

        $this->assertSame('1790011001001', $ret->infoTributaria->ruc);
        $this->assertSame('10/02/2026', $ret->fechaEmision);
        $this->assertSame('PROVEEDOR EC S.A.', $ret->razonSocialSujetoRetenido);
        $this->assertSame('02/2026', $ret->periodoFiscal);
        $this->assertCount(1, $ret->docsSustento);

        $doc = $ret->docsSustento[0];
        $this->assertInstanceOf(DocSustento::class, $doc);
        $this->assertSame('01', $doc->codSustento);
        $this->assertSame('1000.00', $doc->totalSinImpuestos);
        $this->assertCount(1, $doc->impuestosDocSustento);
        $this->assertCount(1, $doc->retenciones);
        $this->assertCount(1, $doc->pagos);
    }

    public function test_rejects_without_docs_sustento(): void
    {
        $data = $this->validData();
        $data['docsSustento'] = [];

        $this->expectException(ValidationException::class);
        Retencion::fromArray($data);
    }

    public function test_rejects_invalid_fecha_emision(): void
    {
        $data = $this->validData();
        $data['infoCompRetencion']['fechaEmision'] = '2026-02-10';

        $this->expectException(ValidationException::class);
        Retencion::fromArray($data);
    }

    public function test_rejects_iso_fecha_emision_doc_sustento(): void
    {
        $data = $this->validData();
        $data['docsSustento'][0]['fechaEmisionDocSustento'] = '2026-01-25';

        $this->expectException(ValidationException::class);
        Retencion::fromArray($data);
    }
}
