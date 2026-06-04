<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Exceptions\CertificateException;

class CertificateTest extends TestCase
{
    public function test_exposes_pems_and_x509_info(): void
    {
        // Generar par de claves + certificado autofirmado en PHP puro.
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'PRUEBA SRI'], $pkey);
        $x509 = openssl_csr_sign($csr, null, $pkey, 1);
        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($pkey, $keyPem);

        $cert = new Certificate($certPem, $keyPem, []);

        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->certPem);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);
        $this->assertSame('PRUEBA SRI', $cert->x509Info()['subject']['CN']);
    }

    public function test_rejects_empty_cert_or_key(): void
    {
        $this->expectException(CertificateException::class);
        new Certificate('', 'x', []);
    }

    public function test_rejects_empty_private_key(): void
    {
        $this->expectException(CertificateException::class);
        new Certificate('-----BEGIN CERTIFICATE-----', '', []);
    }
}
