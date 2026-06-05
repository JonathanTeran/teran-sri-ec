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

    /**
     * Parses the X.509 certificate and returns its fields.
     * The shape mirrors openssl_x509_parse(): includes 'subject', 'issuer',
     * 'serialNumber', 'serialNumberHex', etc.
     *
     * @return array<string, mixed>
     */
    public function x509Info(): array
    {
        $info = openssl_x509_parse($this->certPem);
        if ($info === false) {
            throw new CertificateException('Certificate: no se pudo parsear el certificado X.509.');
        }
        /** @var array<string, mixed> $info */
        return $info;
    }
}
