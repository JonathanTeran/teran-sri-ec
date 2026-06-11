<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teran\Sri\SriClient;
use Teran\Sri\Emission\EmissionStatus;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Emission\Message;
use Teran\Sri\Emission\RejectionStage;
use Teran\Sri\Tests\Support\TestCertificate;
use Teran\Sri\Tests\Support\FakeTransport;

class SriClientTest extends TestCase
{
    private function factura(): Factura
    {
        return Factura::fromArray([
            'infoTributaria' => [
                'ambiente' => '1', 'razonSocial' => 'EMPRESA', 'ruc' => '1790011001001',
                'estab' => '001', 'ptoEmi' => '001', 'secuencial' => '000000001', 'dirMatriz' => 'Quito',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026', 'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CONSUMIDOR FINAL', 'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '100.00', 'totalDescuento' => '0.00', 'importeTotal' => '115.00',
                'totalConImpuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00']],
                'pagos' => [['formaPago' => '01', 'total' => '115.00']],
            ],
            'detalles' => [[
                'codigoPrincipal' => 'P1', 'descripcion' => 'Producto', 'cantidad' => '1.00',
                'precioUnitario' => '100.00', 'descuento' => '0.00', 'precioTotalSinImpuesto' => '100.00',
                'impuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00']],
            ]],
        ]);
    }

    private function client(FakeTransport $t): SriClient
    {
        $tc = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);
        return new SriClient(Ambiente::Pruebas, $cert, $t);
    }

    public function test_emit_authorized_flow(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('AUTORIZADO', '1234567890', '2026-01-26T10:00:00-05:00', '<auth/>', []),
        );
        $client = $this->client($transport);

        $result = $client->emit($this->factura(), '2601202601179001100100110010010000000011234567819');

        $this->assertSame(EmissionStatus::Authorized, $result->status);
        $this->assertSame('1234567890', $result->numeroAutorizacion);
        $this->assertStringContainsString('ds:Signature', $result->signedXml); // se firmó
        $this->assertStringContainsString('ds:Signature', $transport->lastSentXml); // se envió el firmado
    }

    public function test_emit_returned_at_reception_is_rejected(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('DEVUELTA', [new Message('43', 'RUC inválido', 'ERROR')]),
            new AuthorizationOutcome('NO AUTORIZADO'),
        );
        $client = $this->client($transport);

        $result = $client->emit($this->factura(), '2601202601179001100100110010010000000011234567819');

        $this->assertSame(EmissionStatus::Rejected, $result->status);
        $this->assertNull($transport->lastAuthorizedClave); // no se consultó autorización
        $this->assertNotEmpty($result->messages);
        // v2.1: el rechazo distingue la etapa — DEVUELTA = recepción.
        $this->assertSame(RejectionStage::Recepcion, $result->rejectedStage);
    }

    public function test_emit_rejected_at_authorization_exposes_stage(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('NO AUTORIZADO', null, null, null, [new Message('60', 'clave registrada', 'ERROR')]),
        );
        $result = $this->client($transport)->emit($this->factura(), '2601202601179001100100110010010000000011234567819');

        $this->assertSame(EmissionStatus::Rejected, $result->status);
        // v2.1: rechazo en autorización (la recepción SÍ pasó).
        $this->assertSame(RejectionStage::Autorizacion, $result->rejectedStage);
    }

    public function test_emit_authorized_and_in_process_have_null_rejected_stage(): void
    {
        $ok = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('AUTORIZADO', '123', '2026-01-26T10:00:00-05:00', '<auth/>', []),
        );
        $this->assertNull($this->client($ok)->emit($this->factura(), '2601202601179001100100110010010000000011234567819')->rejectedStage);

        $pending = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('EN PROCESO'),
        );
        $this->assertNull($this->client($pending)->emit($this->factura(), '2601202601179001100100110010010000000011234567819')->rejectedStage);
    }

    public function test_emit_in_process_maps_to_in_process(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('EN PROCESO'),
        );
        $result = $this->client($transport)->emit($this->factura(), '2601202601179001100100110010010000000011234567819');
        $this->assertSame(EmissionStatus::InProcess, $result->status);
    }
}
