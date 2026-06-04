<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

final class TestCertificate
{
    /**
     * Genera un .p12 moderno autofirmado en memoria.
     *
     * @return array{p12: string, password: string, subjectCN: string}
     */
    public static function modernP12(string $password = 'test-pass', string $cn = 'PRUEBA SRI 1790011001001'): array
    {
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => $cn], $pkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);

        $p12 = '';
        openssl_pkcs12_export($x509, $p12, $pkey, $password);

        return ['p12' => $p12, 'password' => $password, 'subjectCN' => $cn];
    }
}
