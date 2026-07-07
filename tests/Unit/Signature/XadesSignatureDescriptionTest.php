<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signature;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signature\XadesSignature;
use Teran\Sri\Tests\Support\TestCertificate;

class XadesSignatureDescriptionTest extends TestCase
{
    private const XML = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<factura id="comprobante" version="1.1.0"><infoTributaria><ruc>1790011001001</ruc></infoTributaria></factura>';

    /**
     * Regresión: la firma NO debe contener marca de terceros (ECUAFACT /
     * ecuanexus). El firmador 1.x tenía ese texto hardcodeado.
     */
    public function test_no_incluye_marca_de_terceros(): void
    {
        $tc = TestCertificate::modernP12();
        $signed = (new XadesSignature($tc['p12'], $tc['password']))->sign(self::XML);

        $this->assertStringNotContainsStringIgnoringCase('ECUAFACT', $signed);
        $this->assertStringNotContainsStringIgnoringCase('ecuanexus', $signed);
        $this->assertStringContainsString(XadesSignature::DEFAULT_DESCRIPTION, $signed);
    }

    /**
     * La descripción del DataObjectFormat es configurable por constructor.
     */
    public function test_descripcion_personalizada(): void
    {
        $tc = TestCertificate::modernP12();
        $marca = 'Facturón EC — AmePhia';
        $signed = (new XadesSignature($tc['p12'], $tc['password'], $marca))->sign(self::XML);

        $this->assertStringContainsString($marca, $signed);
        $this->assertStringNotContainsStringIgnoringCase('ECUAFACT', $signed);
    }

    /**
     * Una descripción vacía cae al default neutral (no rompe la firma).
     */
    public function test_descripcion_vacia_usa_default(): void
    {
        $tc = TestCertificate::modernP12();
        $signed = (new XadesSignature($tc['p12'], $tc['password'], '   '))->sign(self::XML);

        $this->assertStringContainsString(XadesSignature::DEFAULT_DESCRIPTION, $signed);
    }
}
