<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\CertificateException;

final class Certificate
{
    /** @param string[] $extraCerts CAs intermedias en PEM */
    public function __construct(
        public readonly string $certPem,
        public readonly string $privateKeyPem,
        public readonly array $extraCerts = [],
    ) {
        if (trim($certPem) === '') {
            throw new CertificateException('Certificate: el PEM del certificado está vacío.');
        }
        if (trim($privateKeyPem) === '') {
            throw new CertificateException('Certificate: el PEM de la clave privada está vacío.');
        }
    }

    /** @return array<string,mixed> openssl_x509_parse del certificado */
    public function x509Info(): array
    {
        $info = openssl_x509_parse($this->certPem);
        if ($info === false) {
            throw new CertificateException('Certificate: no se pudo parsear el certificado X.509.');
        }
        return $info;
    }
}
