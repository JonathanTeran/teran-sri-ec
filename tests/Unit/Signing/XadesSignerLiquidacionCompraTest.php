<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\XadesSigner;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Xml\LiquidacionCompraXmlSerializer;
use Teran\Sri\Documents\LiquidacionCompra;
use Teran\Sri\Schema\XsdValidator;
use Teran\Sri\Tests\Support\TestCertificate;

class XadesSignerLiquidacionCompraTest extends TestCase
{
    private Certificate $cert;
    private string $unsignedXml;

    protected function setUp(): void
    {
        $tc = TestCertificate::modernP12();
        $this->cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $liquidacion = LiquidacionCompra::fromArray([
            'infoTributaria' => [
                'ambiente' => '1', 'razonSocial' => 'EMPRESA & CIA', 'ruc' => '1790011001001',
                'estab' => '001', 'ptoEmi' => '001', 'secuencial' => '000000001', 'dirMatriz' => 'Quito',
            ],
            'infoLiquidacionCompra' => [
                'fechaEmision' => '26/01/2026', 'tipoIdentificacionProveedor' => '05',
                'razonSocialProveedor' => 'PROVEEDOR NATURAL', 'identificacionProveedor' => '1712345678',
                'totalSinImpuestos' => '100.00', 'totalDescuento' => '0.00', 'importeTotal' => '115.00',
                'totalConImpuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00']],
                'pagos' => [['formaPago' => '01', 'total' => '115.00']],
            ],
            'detalles' => [[
                'codigoPrincipal' => 'S1', 'descripcion' => 'Servicio', 'cantidad' => '1.00',
                'precioUnitario' => '100.00', 'descuento' => '0.00', 'precioTotalSinImpuesto' => '100.00',
                'impuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00']],
            ]],
        ]);
        $clave = '2601202603179001100100110010010000000011234567819';
        $this->unsignedXml = (new LiquidacionCompraXmlSerializer())->serialize($liquidacion, $clave);
    }

    public function test_signed_xml_validates_against_xsd(): void
    {
        $signed = (new XadesSigner())->sign($this->unsignedXml, $this->cert);
        $xsd = __DIR__ . '/../../../resources/xsd/liquidacionCompra_v1.1.0.xsd';
        $this->assertTrue(XsdValidator::validate($signed, $xsd));
        $this->assertStringContainsString('ds:Signature', $signed);
    }

    public function test_signature_value_verifies_cryptographically(): void
    {
        $signed = (new XadesSigner())->sign($this->unsignedXml, $this->cert);

        $dom = new \DOMDocument();
        $dom->loadXML($signed);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signedInfo = $xp->query('//ds:SignedInfo')->item(0);
        $sigValueB64 = trim($xp->query('//ds:SignatureValue')->item(0)->textContent);
        $this->assertNotEmpty($sigValueB64);

        $c14n = $signedInfo->C14N();
        $ok = openssl_verify($c14n, base64_decode($sigValueB64), $this->cert->certPem, OPENSSL_ALGO_SHA1);
        $this->assertSame(1, $ok, 'La firma debe verificar criptográficamente sobre SignedInfo canonicalizado.');
    }
}
