<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\IssuerName;

/**
 * El SRI valida la firma XAdES con un stack Java. El campo <ds:X509IssuerName>
 * debe coincidir con lo que produce java.security.auth.x500.X500Principal.getName("RFC2253"):
 *
 *   - Atributos con palabra clave conocida (CN, O, OU, C, L, ST, STREET, DC, UID)
 *     se emiten como `KEYWORD=valor`.
 *   - Cualquier otro atributo (p.ej. organizationIdentifier / OID 2.5.4.97, presente en
 *     certificados Uanataca y eIDAS) se emite como `OID.numerico=#<hexDER>`.
 *
 * Emitir `organizationIdentifier=...` (formato OpenSSL) hace que el SRI rechace con
 * "[39] FIRMA INVALIDA - La información sobre el certificado de firma no se ajusta a XAdES".
 */
final class IssuerNameTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Support/fixtures/' . $name);
    }

    public function test_non_standard_attribute_uses_numeric_oid_with_der_hex(): void
    {
        // El certificado tiene organizationIdentifier=VATES-A66721499 (UTF8String).
        // Java lo serializa como 2.5.4.97=#0c0f<hex de "VATES-A66721499">.
        $issuer = IssuerName::fromCertificate(self::fixture('issuer-with-orgid.pem'));

        $this->assertSame(
            '2.5.4.97=#0c0f56415445532d413636373231343939,CN=AC PRUEBAS,OU=TIC,O=PRUEBAS SRI S.A.,L=Quito,C=EC',
            $issuer,
        );
    }

    public function test_standard_attributes_match_rfc2253_without_regression(): void
    {
        // Solo atributos estándar -> idéntico a `openssl x509 -nameopt RFC2253`
        // (el comportamiento que ya funcionaba para CAs ecuatorianas).
        $issuer = IssuerName::fromCertificate(self::fixture('issuer-standard.pem'));

        $this->assertSame(
            'CN=AUTORIDAD DE CERTIFICACION,OU=ENTIDAD DE CERTIFICACION,O=SECURITY DATA S.A.,C=EC',
            $issuer,
        );
    }

    public function test_escapes_special_characters_like_java(): void
    {
        // El emisor tiene CN="AC PRUEBAS=TEST" y O="ORG#1 S.A.".
        // Java X500Principal escapa '=' y '#' en CUALQUIER posición (set completo:
        // , + " \ < > ; = # y espacios al inicio/fin). Verdad obtenida de
        // c.getIssuerX500Principal().getName("RFC2253") sobre este mismo certificado.
        $issuer = IssuerName::fromCertificate(self::fixture('issuer-special-chars.pem'));

        $this->assertSame(
            'CN=AC PRUEBAS\=TEST,O=ORG\#1 S.A.,C=EC',
            $issuer,
        );
    }

    public function test_accepts_raw_der_input(): void
    {
        $pem = self::fixture('issuer-standard.pem');
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s/', '', $pem), true);
        $this->assertIsString($der);

        $this->assertSame(
            'CN=AUTORIDAD DE CERTIFICACION,OU=ENTIDAD DE CERTIFICACION,O=SECURITY DATA S.A.,C=EC',
            IssuerName::fromCertificate($der),
        );
    }
}
