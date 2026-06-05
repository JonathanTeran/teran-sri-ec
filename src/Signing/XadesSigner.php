<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\SignatureException;
use DOMDocument;
use DOMElement;

/**
 * XAdES-BES Digital Signature for Ecuador SRI Electronic Documents (v2 port).
 *
 * This is a port of `src/Signature/XadesSignature.php` adapted to consume a
 * pre-loaded `Certificate` value object (from `CertificateLoader`) and an
 * injectable `ClockInterface` for deterministic signing time in tests.
 *
 * The XAdES construction logic (IDs, References, digests, C14N, KeyInfo,
 * SignedProperties, serial-number/DN handling, algorithms) is preserved verbatim
 * from the production-proven 1.x implementation.
 */
final class XadesSigner
{
    // XML Digital Signature Namespaces
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    // Canonicalization Algorithms
    private const ALG_C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    // Digest Algorithms
    private const ALG_SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';
    private const ALG_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    // Signature Algorithms RSA
    private const ALG_RSA_SHA1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    private const ALG_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    // Signature Algorithms ECDSA
    private const ALG_ECDSA_SHA1 = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha1';
    private const ALG_ECDSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';

    // XAdES Types
    private const ALG_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const TYPE_SIGNED_PROPS = 'http://uri.etsi.org/01903#SignedProperties';

    // Key Types
    private const KEY_TYPE_RSA = OPENSSL_KEYTYPE_RSA;
    private const KEY_TYPE_EC = OPENSSL_KEYTYPE_EC;

    private string $digestAlgorithm;

    public function __construct(
        private readonly SignatureOptions $options = new SignatureOptions(),
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
        $this->digestAlgorithm = $this->options->digestAlgorithm;
    }

    /**
     * Sign the XML document with XAdES-BES.
     */
    public function sign(string $xmlContent, Certificate $cert): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xmlContent, LIBXML_NONET)) {
            throw new SignatureException("Error al cargar el XML para la firma. Verifique que sea XML válido.");
        }

        if ($dom->documentElement === null) {
            throw new SignatureException("El XML no tiene elemento raíz.");
        }

        // Get the document ID attribute
        $docId = $dom->documentElement->getAttribute('id') ?: 'comprobante';

        // Capture signing time once — used both for ID-suffix derivation and for
        // the <etsi:SigningTime> element so the two are guaranteed to be identical.
        $signingTime = $this->clock->now();
        $suffix = substr(sha1($signingTime->format('Y-m-d\TH:i:sP') . $cert->certPem), 0, 6);
        $ids = [
            'signature' => 'Signature' . $suffix,
            'signatureValue' => 'SignatureValue' . $suffix,
            'signedInfo' => 'Signature-SignedInfo' . $suffix,
            'keyInfo' => 'Certificate' . $suffix,
            'signedProperties' => 'Signature' . $suffix . '-SignedProperties' . $suffix,
            'object' => 'Signature' . $suffix . '-Object' . $suffix,
            'docRef' => 'Reference-ID-' . $suffix,
            'signedPropsRef' => 'SignedPropertiesID' . $suffix,
            'keyInfoRef' => 'CertificateRef-' . $suffix,
        ];

        // Extract certificate information
        $certInfo = $this->extractCertificateInfo($cert);

        // Determine signature algorithm based on key type
        $signatureAlgorithm = $this->getSignatureAlgorithm($certInfo['keyType']);
        $digestAlgorithmUri = $this->getDigestAlgorithmUri();
        $opensslAlgorithm = $this->getOpensslAlgorithm();

        // 1. Create Signature node and append to document (to provide correct namespace scope)
        $signature = $dom->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttributeNs('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DS);
        $signature->setAttributeNs('http://www.w3.org/2000/xmlns/', 'xmlns:etsi', self::NS_XADES);
        $signature->setAttribute('Id', $ids['signature']);
        $dom->documentElement->appendChild($signature);

        // 2. Build complete structure with placeholders

        // SignedInfo
        $signedInfo = $dom->createElementNS(self::NS_DS, 'ds:SignedInfo');
        $signedInfo->setAttribute('Id', $ids['signedInfo']);
        $signature->appendChild($signedInfo);

        $signedInfo->appendChild($this->createAlgorithmNode($dom, 'ds:CanonicalizationMethod', self::ALG_C14N));
        $signedInfo->appendChild($this->createAlgorithmNode($dom, 'ds:SignatureMethod', $signatureAlgorithm));

        // Placeholder References
        $refProps = $dom->createElementNS(self::NS_DS, 'ds:Reference');
        $refProps->setAttribute('Id', $ids['signedPropsRef']);
        $refProps->setAttribute('Type', self::TYPE_SIGNED_PROPS);
        $refProps->setAttribute('URI', "#" . $ids['signedProperties']);
        $refProps->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', $digestAlgorithmUri));
        $signedPropsDigestNode = $dom->createElementNS(self::NS_DS, 'ds:DigestValue', '');
        $refProps->appendChild($signedPropsDigestNode);
        $signedInfo->appendChild($refProps);

        $refDoc = $dom->createElementNS(self::NS_DS, 'ds:Reference');
        $refDoc->setAttribute('Id', $ids['docRef']);
        $refDoc->setAttribute('URI', "#$docId");
        $transforms = $dom->createElementNS(self::NS_DS, 'ds:Transforms');
        $transforms->appendChild($this->createAlgorithmNode($dom, 'ds:Transform', self::ALG_ENVELOPED));
        $refDoc->appendChild($transforms);
        $refDoc->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', $digestAlgorithmUri));
        $docDigestNode = $dom->createElementNS(self::NS_DS, 'ds:DigestValue', '');
        $refDoc->appendChild($docDigestNode);
        $signedInfo->appendChild($refDoc);

        // SignatureValue Placeholder
        $signatureValueNode = $dom->createElementNS(self::NS_DS, 'ds:SignatureValue', '');
        $signatureValueNode->setAttribute('Id', $ids['signatureValue']);
        $signature->appendChild($signatureValueNode);

        // KeyInfo and Object
        $keyInfo = $this->buildKeyInfo($dom, $ids['keyInfo'], $certInfo, $cert);
        $signature->appendChild($keyInfo);

        $object = $dom->createElementNS(self::NS_DS, 'ds:Object');
        $object->setAttribute('Id', $ids['object']);
        $signature->appendChild($object);

        $qualifyingProps = $dom->createElementNS(self::NS_XADES, 'etsi:QualifyingProperties');
        $qualifyingProps->setAttribute('Target', "#" . $ids['signature']);
        $object->appendChild($qualifyingProps);

        $signedProps = $this->buildSignedProperties($dom, $ids['signedProperties'], $ids['docRef'], $certInfo, $signingTime);
        $qualifyingProps->appendChild($signedProps);

        // 3. CALCULATION PHASE

        // A. Document Digest (Detach signature temporarily)
        if ($signature->parentNode === null) {
            throw new SignatureException("El nodo Signature no tiene padre.");
        }
        $signature->parentNode->removeChild($signature);
        $docDigestNode->nodeValue = $this->getDigest($dom->C14N());
        $dom->documentElement->appendChild($signature);

        // B. Component Digests
        $signedPropsDigestNode->nodeValue = base64_encode(hash($this->digestAlgorithm, $signedProps->C14N(), true));

        // C. SignatureValue
        $signedInfoCanonical = $signedInfo->C14N();
        $signatureValueRaw = '';
        if (!openssl_sign($signedInfoCanonical, $signatureValueRaw, $cert->privateKeyPem, $opensslAlgorithm)) {
            $error = openssl_error_string();
            throw new SignatureException("Error al firmar el documento: " . ($error ?: 'error desconocido'));
        }
        // $signatureValueRaw is guaranteed to be string after openssl_sign succeeds.
        if (!is_string($signatureValueRaw)) {
            throw new SignatureException("openssl_sign no devolvió un string.");
        }
        $signatureValueNode->nodeValue = trim(chunk_split(base64_encode($signatureValueRaw), 76, "\n"));

        // Disable formatOutput to avoid whitespace issues in signature verification
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        // Return signed XML with proper indentation and FULL closing tags (not self-closing)
        // CRITICAL: LIBXML_NOEMPTYTAG ensures <tag></tag> instead of <tag/> which SRI requires
        $finalXml = $dom->saveXML(null, LIBXML_NOEMPTYTAG);

        if ($finalXml === false) {
            throw new SignatureException("Error al serializar el XML firmado.");
        }

        return $finalXml;
    }

    /**
     * Build the KeyInfo element
     *
     * @param array{cleanCert: string, certDigest: string, issuerName: string, serialNumber: string, keyType: int, subject: array<string, mixed>, issuer: array<string, mixed>, modulus?: string, exponent?: string, curve?: string, publicKey?: string} $certInfo
     */
    private function buildKeyInfo(DOMDocument $dom, string $keyInfoId, array $certInfo, Certificate $cert): DOMElement
    {
        $keyInfo = $dom->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        // X509Data with certificate
        $x509Data = $dom->createElementNS(self::NS_DS, 'ds:X509Data');
        $keyInfo->appendChild($x509Data);

        // 1. Add the signing certificate (Chunk split formatted)
        $cleanCert = $certInfo['cleanCert'];
        $certContent = trim(chunk_split($cleanCert, 76, "\n"));
        $x509Data->appendChild($dom->createElementNS(self::NS_DS, 'ds:X509Certificate', $certContent));

        // 2. Add intermediate certificates if present
        foreach ($cert->extraCerts as $extraCert) {
            $cleanExtra = $this->cleanCertificate($extraCert);
            if ($cleanExtra !== $cleanCert) {
                $extraContent = chunk_split($cleanExtra, 76, "\n");
                $x509Data->appendChild($dom->createElementNS(self::NS_DS, 'ds:X509Certificate', $extraContent));
            }
        }

        // KeyValue based on key type
        $keyValue = $dom->createElementNS(self::NS_DS, 'ds:KeyValue');
        $keyInfo->appendChild($keyValue);

        if ($certInfo['keyType'] === self::KEY_TYPE_RSA) {
            // RSAKeyValue
            $rsaKeyValue = $dom->createElementNS(self::NS_DS, 'ds:RSAKeyValue');
            $keyValue->appendChild($rsaKeyValue);

            // Modulus with chunk split (trimmed)
            $modulusContent = trim(chunk_split($certInfo['modulus'] ?? '', 76, "\n"));
            $rsaKeyValue->appendChild($dom->createElementNS(self::NS_DS, 'ds:Modulus', $modulusContent));
            $rsaKeyValue->appendChild($dom->createElementNS(self::NS_DS, 'ds:Exponent', $certInfo['exponent'] ?? ''));
        } elseif ($certInfo['keyType'] === self::KEY_TYPE_EC) {
            // ECKeyValue (for ECDSA certificates) - Namespace is specific
            $nsEc = 'http://www.w3.org/2009/xmldsig11#';
            $ecKeyValue = $dom->createElementNS($nsEc, 'dsig11:ECKeyValue');
            $keyValue->appendChild($ecKeyValue);

            $curveName = $certInfo['curve'] ?? '';
            if ($curveName !== '') {
                $namedCurve = $dom->createElementNS($nsEc, 'dsig11:NamedCurve');
                $namedCurve->setAttribute('URI', $this->getCurveUri($curveName));
                $ecKeyValue->appendChild($namedCurve);
            }

            $publicKeyStr = $certInfo['publicKey'] ?? '';
            if ($publicKeyStr !== '') {
                $publicKeyContent = chunk_split($publicKeyStr, 76, "\n");
                $ecKeyValue->appendChild($dom->createElementNS($nsEc, 'dsig11:PublicKey', $publicKeyContent));
            }
        }

        return $keyInfo;
    }

    /**
     * Build the SignedProperties element
     *
     * @param array{cleanCert: string, certDigest: string, issuerName: string, serialNumber: string, keyType: int, subject: array<string, mixed>, issuer: array<string, mixed>, modulus?: string, exponent?: string, curve?: string, publicKey?: string} $certInfo
     */
    private function buildSignedProperties(DOMDocument $dom, string $signedPropsId, string $docRefId, array $certInfo, \DateTimeImmutable $signingTime): DOMElement
    {
        $signedProps = $dom->createElementNS(self::NS_XADES, 'etsi:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        // SignedSignatureProperties
        $signedSigProps = $dom->createElementNS(self::NS_XADES, 'etsi:SignedSignatureProperties');
        $signedProps->appendChild($signedSigProps);

        // SigningTime in ISO 8601 format (passed from sign() to guarantee consistency with IDs)
        $signedSigProps->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:SigningTime', $signingTime->format('Y-m-d\TH:i:sP')));

        // SigningCertificate
        $signingCert = $dom->createElementNS(self::NS_XADES, 'etsi:SigningCertificate');
        $signedSigProps->appendChild($signingCert);

        $cert = $dom->createElementNS(self::NS_XADES, 'etsi:Cert');
        $signingCert->appendChild($cert);

        // CertDigest
        $certDigestNode = $dom->createElementNS(self::NS_XADES, 'etsi:CertDigest');
        $cert->appendChild($certDigestNode);

        $digestMethod = $this->createAlgorithmNode($dom, 'ds:DigestMethod', $this->getDigestAlgorithmUri());
        $certDigestNode->appendChild($digestMethod);

        $digestValue = $dom->createElementNS(self::NS_DS, 'ds:DigestValue', $certInfo['certDigest']);
        $certDigestNode->appendChild($digestValue);

        // IssuerSerial
        $issuerSerial = $dom->createElementNS(self::NS_XADES, 'etsi:IssuerSerial');
        $cert->appendChild($issuerSerial);

        $issuerName = $dom->createElementNS(self::NS_DS, 'ds:X509IssuerName', $certInfo['issuerName']);
        $issuerSerial->appendChild($issuerName);

        $serialNumber = $dom->createElementNS(self::NS_DS, 'ds:X509SerialNumber', $certInfo['serialNumber']);
        $issuerSerial->appendChild($serialNumber);

        // SignedDataObjectProperties
        $signedDataObjProps = $dom->createElementNS(self::NS_XADES, 'etsi:SignedDataObjectProperties');
        $signedProps->appendChild($signedDataObjProps);

        $dataObjFormat = $dom->createElementNS(self::NS_XADES, 'etsi:DataObjectFormat');
        $dataObjFormat->setAttribute('ObjectReference', "#$docRefId");
        $signedDataObjProps->appendChild($dataObjFormat);

        // Description from injected options (no third-party branding)
        $dataObjFormat->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:Description', $this->options->description));
        $dataObjFormat->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:MimeType', 'text/xml'));

        return $signedProps;
    }

    /**
     * Extract all certificate information needed for signing.
     *
     * @return array{cleanCert: string, certDigest: string, issuerName: string, serialNumber: string, keyType: int, subject: array<string, mixed>, issuer: array<string, mixed>, modulus?: string, exponent?: string, curve?: string, publicKey?: string}
     */
    /** @var array<string, array{cleanCert: string, certDigest: string, issuerName: string, serialNumber: string, keyType: int, subject: array<string, mixed>, issuer: array<string, mixed>, modulus?: string, exponent?: string, curve?: string, publicKey?: string}> Caché del parseo del cert (no cambia entre documentos). */
    private array $certInfoCache = [];

    /**
     * @return array{cleanCert: string, certDigest: string, issuerName: string, serialNumber: string, keyType: int, subject: array<string, mixed>, issuer: array<string, mixed>, modulus?: string, exponent?: string, curve?: string, publicKey?: string}
     */
    private function extractCertificateInfo(Certificate $cert): array
    {
        // El certificado y el algoritmo de digest no cambian entre documentos, así que
        // memoizamos el parseo costoso (openssl_x509_parse, pkey_get_details, DN, serial,
        // digest). Esto acelera notablemente la firma de muchos comprobantes con el mismo cert.
        return $this->certInfoCache[md5($cert->certPem)] ??= $this->computeCertificateInfo($cert);
    }

    /**
     * @return array{cleanCert: string, certDigest: string, issuerName: string, serialNumber: string, keyType: int, subject: array<string, mixed>, issuer: array<string, mixed>, modulus?: string, exponent?: string, curve?: string, publicKey?: string}
     */
    private function computeCertificateInfo(Certificate $cert): array
    {
        $publicCert = $cert->certPem;
        $cleanCert = $this->cleanCertificate($publicCert);

        // Calculate certificate digest
        $certDer = base64_decode($cleanCert);
        $certDigest = base64_encode(hash($this->digestAlgorithm, $certDer, true));

        // Parse certificate
        $certResource = openssl_x509_read($publicCert);
        if ($certResource === false) {
            throw new SignatureException("No se pudo leer el certificado X.509");
        }

        $certData = openssl_x509_parse($certResource);
        if ($certData === false) {
            throw new SignatureException("No se pudo parsear el certificado X.509");
        }

        /** @var array<string, mixed> $issuerData */
        $issuerData = is_array($certData['issuer'] ?? null) ? $certData['issuer'] : [];

        // Build issuer name in RFC 2253 format (reversed order)
        $issuerName = $this->buildDistinguishedName($issuerData);

        // Get serial number (handle both decimal and hex formats)
        /** @var array<string, mixed> $certDataForSerial */
        $certDataForSerial = $certData;
        $serialNumber = $this->normalizeSerialNumber($certDataForSerial);

        // Get public key details
        $pubKey = openssl_pkey_get_public($publicCert);
        if ($pubKey === false) {
            throw new SignatureException("No se pudo extraer la clave pública del certificado");
        }

        $pubKeyDetails = openssl_pkey_get_details($pubKey);
        if ($pubKeyDetails === false) {
            throw new SignatureException("No se pudieron obtener los detalles de la clave pública");
        }

        // PHPStan's openssl_pkey_get_details stub returns array with 'type' as mixed;
        // it is always an int OPENSSL_KEYTYPE_* constant when the call succeeds.
        $keyType = is_int($pubKeyDetails['type']) ? $pubKeyDetails['type'] : OPENSSL_KEYTYPE_RSA;

        /** @var array<string, mixed> $subjectData */
        $subjectData = is_array($certData['subject'] ?? null) ? $certData['subject'] : [];

        $result = [
            'cleanCert'    => $cleanCert,
            'certDigest'   => $certDigest,
            'issuerName'   => $issuerName,
            'serialNumber' => $serialNumber,
            'keyType'      => $keyType,
            'subject'      => $subjectData,
            'issuer'       => $issuerData,
        ];

        // Add key-type specific information
        if ($keyType === self::KEY_TYPE_RSA && isset($pubKeyDetails['rsa'])) {
            /** @var array{n: string, e: string, d?: string, p?: string, q?: string, dmp1?: string, dmq1?: string, iqmp?: string} $rsa */
            $rsa = $pubKeyDetails['rsa'];
            $result['modulus']  = base64_encode($rsa['n']);
            $result['exponent'] = base64_encode($rsa['e']);
        } elseif ($keyType === self::KEY_TYPE_EC && isset($pubKeyDetails['ec'])) {
            /** @var array{curve_name?: string, x?: string, y?: string, d?: string} $ec */
            $ec = $pubKeyDetails['ec'];
            $result['curve'] = $ec['curve_name'] ?? '';
            // EC public key as base64
            if (isset($ec['x'], $ec['y'])) {
                // Uncompressed point format: 04 || X || Y
                $point = chr(0x04) . $ec['x'] . $ec['y'];
                $result['publicKey'] = base64_encode($point);
            }
        }

        return $result;
    }

    /**
     * Clean certificate PEM format to get only base64 content
     */
    private function cleanCertificate(string $cert): string
    {
        return str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r", " "],
            '',
            $cert
        );
    }

    /**
     * Build Distinguished Name string in RFC 2253 format
     *
     * @param array<string, mixed> $dn
     */
    private function buildDistinguishedName(array $dn): string
    {
        $parts = [];

        // Process in reverse order for RFC 2253 compliance
        foreach (array_reverse($dn) as $key => $value) {
            // Handle array values (multiple values for same attribute)
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (!is_string($v) && !is_int($v) && !is_float($v)) {
                        continue;
                    }
                    $res = $this->escapeDistinguishedNameValue((string) $key, (string) $v);
                    if ($res !== '') {
                        $parts[] = $res;
                    }
                }
            } elseif (is_string($value) || is_int($value) || is_float($value)) {
                $res = $this->escapeDistinguishedNameValue((string) $key, (string) $value);
                if ($res !== '') {
                    $parts[] = $res;
                }
            }
        }

        return implode(',', $parts);
    }

    /**
     * Escape special characters in DN values
     */
    private function escapeDistinguishedNameValue(string $key, string $value): string
    {
        // Match OpenSSL CLI format for organizationIdentifier to ensure SRI validator match
        if ($key === 'organizationIdentifier') {
            // Return plain string format: organizationIdentifier=VALUE
            return "organizationIdentifier=" . $value;
        }

        // Escape special characters per RFC 2253
        $escaped = str_replace(
            ['\\', ',', '+', '"', '<', '>', ';', '='],
            ['\\\\', '\\,', '\\+', '\\"', '\\<', '\\>', '\\;', '\\='],
            $value
        );

        return "$key=$escaped";
    }

    /**
     * Normalize serial number to decimal format
     *
     * @param array<string, mixed> $certData
     */
    private function normalizeSerialNumber(array $certData): string
    {
        // 1. Try to use the parsed serialNumber if it's already a clean numeric string
        $serialRaw = isset($certData['serialNumber']) && (is_string($certData['serialNumber']) || is_numeric($certData['serialNumber'])) ? (string) $certData['serialNumber'] : '';
        if ($serialRaw !== '' && is_numeric($serialRaw) && !str_contains($serialRaw, 'E')) {
            return $serialRaw;
        }

        // 2. Use Hexadecimal representation if available (most reliable source)
        if (isset($certData['serialNumberHex']) && is_string($certData['serialNumberHex'])) {
            $hex = $certData['serialNumberHex'];
            // Remove any colons, spaces, or 0x prefix
            $hex = str_replace([':', ' ', '0x'], '', $hex);

            // Convert using BCMath (Arbitrary Precision Mathematics)
            if (function_exists('bchexdec')) {
                /** @phpstan-ignore-next-line  bchexdec() is a user-space function (not a PHP built-in); its return type is unknowable to PHPStan */
                return (string) bchexdec($hex);
            }

            // Fallback: Custom Hex to Dec conversion logic using BCMath
            if (extension_loaded('bcmath')) {
                $len = strlen($hex);
                $dec = '0';
                for ($i = 0; $i < $len; $i++) {
                    $digitVal = (int) hexdec($hex[$i]);
                    // bcmul/bcadd return numeric-string when given numeric-string args.
                    $dec = bcadd(bcmul($dec, '16', 0), (string) $digitVal, 0);
                }
                return $dec;
            }

            // Fallback: GMP
            if (function_exists('gmp_strval')) {
                return gmp_strval(gmp_init($hex, 16), 10);
            }
        }

        // 3. Last resort (may lose precision for massive numbers > PHP_INT_MAX)
        return $serialRaw !== '' ? $serialRaw : '0';
    }

    /**
     * Get the appropriate signature algorithm URI based on key type
     */
    private function getSignatureAlgorithm(int $keyType): string
    {
        if ($keyType === self::KEY_TYPE_EC) {
            return $this->digestAlgorithm === 'sha256' ? self::ALG_ECDSA_SHA256 : self::ALG_ECDSA_SHA1;
        }

        // Default to RSA
        return $this->digestAlgorithm === 'sha256' ? self::ALG_RSA_SHA256 : self::ALG_RSA_SHA1;
    }

    /**
     * Get the digest algorithm URI
     */
    private function getDigestAlgorithmUri(): string
    {
        return $this->digestAlgorithm === 'sha256' ? self::ALG_SHA256 : self::ALG_SHA1;
    }

    /**
     * Get the OpenSSL algorithm constant
     */
    private function getOpensslAlgorithm(): int
    {
        return $this->digestAlgorithm === 'sha256' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
    }

    /**
     * Get curve URI for ECDSA
     */
    private function getCurveUri(string $curveName): string
    {
        $curves = [
            'prime256v1' => 'urn:oid:1.2.840.10045.3.1.7',
            'secp256r1' => 'urn:oid:1.2.840.10045.3.1.7',
            'secp384r1' => 'urn:oid:1.3.132.0.34',
            'secp521r1' => 'urn:oid:1.3.132.0.35',
        ];

        return $curves[$curveName] ?? "urn:oid:$curveName";
    }

    /**
     * Create an algorithm node with Algorithm attribute
     */
    private function createAlgorithmNode(DOMDocument $dom, string $name, string $algorithm): DOMElement
    {
        $node = $dom->createElementNS(self::NS_DS, $name);
        $node->setAttribute('Algorithm', $algorithm);
        return $node;
    }

    /**
     * Calculate digest of content
     */
    private function getDigest(string $content): string
    {
        return base64_encode(hash($this->digestAlgorithm, $content, true));
    }


}
