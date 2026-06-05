<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\SignatureException;

/**
 * Construye el nombre distinguido (DN) del emisor de un certificado X.509
 * EXACTAMENTE como lo hace el stack Java del SRI:
 * `javax.security.auth.x500.X500Principal.getName("RFC2253")`.
 *
 * El validador XAdES del SRI compara el contenido de `<ds:X509IssuerName>` contra
 * ese formato. La diferencia crítica frente a OpenSSL es el manejo de atributos sin
 * palabra clave estándar (p.ej. `organizationIdentifier`, OID 2.5.4.97, presente en
 * certificados Uanataca/eIDAS):
 *
 *   - OpenSSL emite:  `organizationIdentifier=VATES-A66721499`
 *   - Java emite:     `2.5.4.97=#0c0f56415445532d413636373231343939`  (OID numérico + DER en hex)
 *
 * Usar el formato de OpenSSL hace que el SRI rechace la firma con
 * "[39] FIRMA INVALIDA - La información sobre el certificado de firma no se ajusta a XAdES".
 *
 * Para parsear el DN se decodifica el DER del certificado directamente (sin depender de
 * la tabla de OIDs de OpenSSL), garantizando el mismo resultado byte-a-byte que Java.
 */
final class IssuerName
{
    /**
     * OIDs que RFC 2253 (y X500Principal) representan con palabra clave.
     * Cualquier OID fuera de este mapa se emite como `oid.numerico=#hexDER`.
     *
     * @var array<string, string>
     */
    private const KEYWORDS = [
        '2.5.4.3'  => 'CN',
        '2.5.4.6'  => 'C',
        '2.5.4.7'  => 'L',
        '2.5.4.8'  => 'ST',
        '2.5.4.9'  => 'STREET',
        '2.5.4.10' => 'O',
        '2.5.4.11' => 'OU',
        '0.9.2342.19200300.100.1.25' => 'DC',
        '0.9.2342.19200300.100.1.1'  => 'UID',
    ];

    /**
     * Devuelve el DN del emisor en formato RFC 2253 (estilo Java X500Principal).
     *
     * @param string $certificate Certificado en PEM o DER (binario).
     */
    public static function fromCertificate(string $certificate): string
    {
        $der = self::toDer($certificate);
        $issuer = self::locateIssuer($der);

        return self::formatRdnSequence($der, $issuer['cs'], $issuer['next']);
    }

    private static function toDer(string $certificate): string
    {
        if (str_contains($certificate, '-----BEGIN')) {
            $body = (string) preg_replace('/-----[^-]+-----|\s+/', '', $certificate);
            $der = base64_decode($body, true);
            if ($der === false || $der === '') {
                throw new SignatureException('El certificado PEM no contiene base64 válido.');
            }
            return $der;
        }

        if ($certificate === '') {
            throw new SignatureException('El certificado está vacío.');
        }

        return $certificate;
    }

    /**
     * Navega Certificate -> tbsCertificate -> issuer (RDNSequence).
     *
     * @return array{tag: int, cs: int, len: int, next: int}
     */
    private static function locateIssuer(string $der): array
    {
        $certificate = self::readTlv($der, 0);          // Certificate ::= SEQUENCE
        $tbs = self::readTlv($der, $certificate['cs']); // tbsCertificate ::= SEQUENCE

        $pos = $tbs['cs'];
        $first = self::readTlv($der, $pos);
        if ($first['tag'] === 0xA0) {                   // [0] EXPLICIT version (opcional)
            $pos = $first['next'];
        }

        $pos = self::readTlv($der, $pos)['next'];       // serialNumber ::= INTEGER
        $pos = self::readTlv($der, $pos)['next'];       // signature ::= AlgorithmIdentifier (SEQUENCE)

        $issuer = self::readTlv($der, $pos);            // issuer ::= Name (RDNSequence = SEQUENCE)
        if ($issuer['tag'] !== 0x30) {
            throw new SignatureException('Estructura del certificado inválida: no se encontró el emisor (RDNSequence).');
        }

        return $issuer;
    }

    /**
     * Serializa una RDNSequence en orden inverso (RFC 2253).
     */
    private static function formatRdnSequence(string $der, int $start, int $end): string
    {
        $rdns = [];
        $pos = $start;

        while ($pos < $end) {
            $set = self::readTlv($der, $pos);           // RelativeDistinguishedName ::= SET OF
            $avas = [];
            $inner = $set['cs'];

            while ($inner < $set['next']) {
                $ava = self::readTlv($der, $inner);     // AttributeTypeAndValue ::= SEQUENCE
                $oidTlv = self::readTlv($der, $ava['cs']);
                $oid = self::decodeOid(substr($der, $oidTlv['cs'], $oidTlv['len']));
                $valueTlv = self::readTlv($der, $oidTlv['next']);

                if (isset(self::KEYWORDS[$oid])) {
                    $value = self::decodeDirectoryString(
                        $valueTlv['tag'],
                        substr($der, $valueTlv['cs'], $valueTlv['len']),
                    );
                    $avas[] = self::KEYWORDS[$oid] . '=' . self::escape($value);
                } else {
                    // OID desconocido: el valor se emite como #<DER en hex minúscula> (tag+long+valor).
                    $valueDer = substr($der, $oidTlv['next'], $valueTlv['next'] - $oidTlv['next']);
                    $avas[] = $oid . '=#' . strtolower(bin2hex($valueDer));
                }

                $inner = $ava['next'];
            }

            $rdns[] = implode('+', $avas);
            $pos = $set['next'];
        }

        return implode(',', array_reverse($rdns));
    }

    /**
     * Lee una estructura TLV (Tag-Length-Value) DER con validación de límites.
     *
     * @return array{tag: int, cs: int, len: int, next: int}
     */
    private static function readTlv(string $der, int $offset): array
    {
        $size = strlen($der);
        if ($offset < 0 || $offset + 2 > $size) {
            throw new SignatureException('DER truncado al leer la cabecera TLV.');
        }

        $tag = ord($der[$offset]);
        $lengthByte = ord($der[$offset + 1]);

        if ($lengthByte < 0x80) {
            $contentStart = $offset + 2;
            $length = $lengthByte;
        } else {
            $numBytes = $lengthByte & 0x7F;
            if ($numBytes === 0 || $numBytes > 4 || $offset + 2 + $numBytes > $size) {
                throw new SignatureException('DER con longitud inválida.');
            }
            $length = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $length = ($length << 8) | ord($der[$offset + 2 + $i]);
            }
            $contentStart = $offset + 2 + $numBytes;
        }

        if ($contentStart + $length > $size) {
            throw new SignatureException('DER truncado: el contenido excede el tamaño del certificado.');
        }

        return ['tag' => $tag, 'cs' => $contentStart, 'len' => $length, 'next' => $contentStart + $length];
    }

    /**
     * Decodifica los bytes de un OBJECT IDENTIFIER a su forma con puntos.
     */
    private static function decodeOid(string $bytes): string
    {
        if ($bytes === '') {
            throw new SignatureException('OID vacío en el DN del emisor.');
        }

        /** @var array<int, int>|false $octets */
        $octets = unpack('C*', $bytes);
        if ($octets === false) {
            throw new SignatureException('No se pudo decodificar el OID del emisor.');
        }
        $octets = array_values($octets);
        $count = count($octets);

        // El primer subidentificador codifica 40*arco1 + arco2 y puede ocupar varios
        // bytes (base 128, bit 0x80 = continúa). Decodificarlo como entero completo.
        $index = 0;
        $first = 0;
        do {
            $first = ($first << 7) | ($octets[$index] & 0x7F);
            $more = ($octets[$index] & 0x80) !== 0;
            $index++;
        } while ($more && $index < $count);

        $arc1 = $first < 40 ? 0 : ($first < 80 ? 1 : 2);
        $arcs = [$arc1, $first - 40 * $arc1];

        // Subidentificadores restantes.
        $value = 0;
        for (; $index < $count; $index++) {
            $value = ($value << 7) | ($octets[$index] & 0x7F);
            if (($octets[$index] & 0x80) === 0) {
                $arcs[] = $value;
                $value = 0;
            }
        }

        return implode('.', $arcs);
    }

    /**
     * Decodifica el valor de un AttributeValue de tipo DirectoryString a UTF-8.
     *
     * Los certificados del SRI/eIDAS usan UTF8String o PrintableString en el emisor,
     * que son ASCII/UTF-8 directos (coinciden con Java). BMPString/UniversalString se
     * transcodifican (mejor que emitir bytes con NUL inválidos en XML); TeletexString
     * se pasa crudo. Estos tipos no aparecen en emisores reales del SRI; para ellos el
     * resultado podría no coincidir byte-a-byte con Java X500Principal.
     */
    private static function decodeDirectoryString(int $tag, string $content): string
    {
        // 0x1E = BMPString (UTF-16BE), 0x1C = UniversalString (UTF-32BE).
        if (($tag === 0x1E || $tag === 0x1C) && function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($content, 'UTF-8', $tag === 0x1E ? 'UTF-16BE' : 'UTF-32BE');
            return is_string($converted) ? $converted : $content;
        }

        // UTF8String (0x0C), PrintableString (0x13), IA5String (0x16), TeletexString (0x14),
        // VisibleString (0x1A), etc.: los bytes ya son UTF-8/ASCII compatibles.
        return $content;
    }

    /**
     * Escapa un valor de DN igual que `X500Principal.getName("RFC2253")` (verificado
     * contra OpenJDK 17): los caracteres `, + " \ < > ; = #` se escapan en cualquier
     * posición, y un espacio al inicio o al final también. (RFC 2253 a secas solo exige
     * escapar `#` al inicio, pero el validador del SRI usa Java, que escapa más.)
     */
    private static function escape(string $value): string
    {
        $length = strlen($value);
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($char === ',' || $char === '+' || $char === '"' || $char === '\\'
                || $char === '<' || $char === '>' || $char === ';'
                || $char === '=' || $char === '#') {
                $out .= '\\' . $char;
            } elseif ($char === ' ' && ($i === 0 || $i === $length - 1)) {
                $out .= '\\' . $char;
            } else {
                $out .= $char;
            }
        }

        return $out;
    }
}
