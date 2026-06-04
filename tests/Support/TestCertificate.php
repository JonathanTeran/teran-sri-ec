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

    /**
     * Genera un .p12 con cadena: CA autofirmada + certificado hoja firmado por la CA.
     * El CA cert se pasa en `extracerts` para simular un bundle de CA intermedia.
     *
     * @return array{p12: string, password: string, leafCN: string, caCN: string}
     */
    public static function modernP12WithChain(
        string $password = 'test-chain-pass',
        string $leafCN = 'PRUEBA HOJA SRI',
        string $caCN  = 'PRUEBA CA SRI',
    ): array {
        // 1. Generar CA autofirmada.
        $caKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $caCsr = openssl_csr_new(['commonName' => $caCN], $caKey, ['digest_alg' => 'sha256']);
        $caCert = openssl_csr_sign($caCsr, null, $caKey, 365, ['digest_alg' => 'sha256']);

        // 2. Generar hoja firmada por la CA.
        $leafKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $leafCsr = openssl_csr_new(['commonName' => $leafCN], $leafKey, ['digest_alg' => 'sha256']);
        $leafCert = openssl_csr_sign($leafCsr, $caCert, $caKey, 365, ['digest_alg' => 'sha256']);

        // 3. Exportar p12 con hoja + clave + CA en extracerts (5th arg = $options).
        $p12 = '';
        openssl_pkcs12_export(
            $leafCert,
            $p12,
            $leafKey,
            $password,
            ['extracerts' => [$caCert]],
        );

        return ['p12' => $p12, 'password' => $password, 'leafCN' => $leafCN, 'caCN' => $caCN];
    }
}
