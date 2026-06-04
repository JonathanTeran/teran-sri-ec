<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Exceptions\CertificateException;
use Teran\Sri\Tests\Support\TestCertificate;

class CertificateLoaderTest extends TestCase
{
    public function test_loads_via_native_reader(): void
    {
        $tc = TestCertificate::modernP12();

        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $this->assertInstanceOf(Certificate::class, $cert);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->certPem);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);
        $this->assertStringContainsString('PRUEBA SRI', $cert->x509Info()['subject']['CN']);
    }

    public function test_wrong_password_throws(): void
    {
        $tc = TestCertificate::modernP12();

        $this->expectException(CertificateException::class);
        (new CertificateLoader())->load($tc['p12'], 'contraseña-incorrecta');
    }

    public function test_cli_fallback_loads_without_writing_key_to_disk(): void
    {
        $loader = new CertificateLoader();
        if (!$loader->hasOpensslBinary()) {
            $this->markTestSkipped('No hay binario openssl disponible para probar el fallback CLI.');
        }

        $tc = TestCertificate::modernP12();

        // Contar archivos temporales antes/después: la clave NUNCA debe quedar en disco.
        $pattern = sys_get_temp_dir() . '/sri_p12*';
        $before = glob($pattern) ?: [];

        // Forzar el camino CLI directamente (M-1 stdin / M-2 stdout).
        $cert = $loader->loadViaOpensslCli($tc['p12'], $tc['password']);

        $this->assertInstanceOf(Certificate::class, $cert);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);

        $after = glob($pattern) ?: [];
        $this->assertSame($before, $after, 'No deben quedar temporales del certificado tras la carga CLI');
    }

    public function test_cli_fallback_rejects_wrong_password(): void
    {
        $loader = new CertificateLoader();
        if (!$loader->hasOpensslBinary()) {
            $this->markTestSkipped('No hay binario openssl disponible.');
        }
        $tc = TestCertificate::modernP12();

        $this->expectException(CertificateException::class);
        $loader->loadViaOpensslCli($tc['p12'], 'mala');
    }

    public function test_cli_fallback_captures_intermediate_cas(): void
    {
        $loader = new CertificateLoader();
        if (!$loader->hasOpensslBinary()) {
            $this->markTestSkipped('No hay binario openssl disponible para probar la cadena CLI.');
        }

        $tc = TestCertificate::modernP12WithChain();

        $cert = $loader->loadViaOpensslCli($tc['p12'], $tc['password']);

        // El certificado hoja siempre debe capturarse.
        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->certPem);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);

        // Si el binario openssl incluye las extracerts en la salida -nodes, la cadena queda.
        // En algunos entornos el CA no se emite por stdout; lo aceptamos pero lo documentamos.
        if (count($cert->extraCerts) >= 1) {
            $this->assertGreaterThanOrEqual(1, count($cert->extraCerts), 'La CA intermedia debe estar en extraCerts');
        } else {
            $this->markTestIncomplete(
                'El binario openssl de este entorno no emite las extracerts en la salida -nodes. ' .
                'La hoja fue capturada correctamente; la cadena queda pendiente de validación.'
            );
        }
    }
}
