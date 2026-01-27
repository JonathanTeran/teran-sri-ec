<?php

declare(strict_types=1);

namespace Teran\Sri\Signature;

use Teran\Sri\Exceptions\SignatureException;
use DOMDocument;
use DOMElement;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * XAdES-BES Digital Signature for Ecuador SRI Electronic Documents
 *
 * Compatible with certificates from all Ecuadorian providers:
 * - Security Data
 * - Uanataca
 * - ANF AC Ecuador
 * - Banco Central del Ecuador
 * - Consejo de la Judicatura
 * - Eclipsoft
 * - Datilmedia
 *
 * @see https://www.sri.gob.ec/facturacion-electronica
 */
class XadesSignature
{
    // XML Digital Signature Namespaces
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    // Canonicalization Algorithms
    private const ALG_C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const ALG_C14N_EXCLUSIVE = 'http://www.w3.org/2001/10/xml-exc-c14n#';

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

    private string $p12Content;
    private string $password;
    private array $certs = [];
    private array $extraCerts = [];
    private string $digestAlgorithm = 'sha1';
    private bool $validated = false;

    /**
     * Known Ecuadorian certificate providers for debugging
     */
    private const KNOWN_PROVIDERS = [
        'Security Data' => ['securitydata', 'security data'],
        'Uanataca' => ['uanataca'],
        'ANF AC Ecuador' => ['anf', 'anfac'],
        'Banco Central del Ecuador' => ['bce', 'banco central'],
        'Consejo de la Judicatura' => ['consejo de la judicatura', 'judicatura'],
        'Eclipsoft' => ['eclipsoft'],
        'Datilmedia' => ['datilmedia'],
    ];

    public function __construct(string $p12Content, string $password)
    {
        $this->p12Content = $p12Content;
        $this->password = $password;
        $this->loadCertificate();
    }

    /**
     * Load and parse the P12/PFX certificate
     */
    private function loadCertificate(): void
    {
        // 1. Try PHP Native (Fastest)
        if (openssl_pkcs12_read($this->p12Content, $this->certs, $this->password)) {
            $this->validateLoadedCerts();
            return;
        }

        // 2. Fallback: Try using CLI OpenSSL if PHP fails
        // This is necessary for Legacy P12 files on OpenSSL 3.0+ environments (like macOS)
        $tempFile = stream_get_meta_data(tmpfile())['uri'];
        file_put_contents($tempFile, $this->p12Content);
        
        try {
            // Determine OpenSSL Binary locations
            $possibleBins = [
                '/opt/homebrew/opt/openssl@3/bin/openssl', // MacOS Homebrew v3
                '/usr/local/opt/openssl@3/bin/openssl',    // MacOS Intel v3
                '/opt/homebrew/opt/openssl@1.1/bin/openssl', // MacOS Homebrew v1.1
                '/usr/bin/openssl',                        // Linux Standard
                'openssl'                                  // PATH Fallback
            ];
            
            $opensslBin = 'openssl';
            foreach ($possibleBins as $bin) {
                if (file_exists($bin) && is_executable($bin)) {
                    $opensslBin = $bin;
                    break;
                }
            }
            
            // Convert P12 to readable PEM using CLI
            // This bypasses PHP's internal checks which might be too strict
            $tempPem = $tempFile . '.pem';
            $cmd = sprintf(
                '%s pkcs12 -in %s -out %s -nodes -passin pass:%s 2>&1',
                $opensslBin,
                escapeshellarg($tempFile),
                escapeshellarg($tempPem),
                escapeshellarg($this->password)
            );
            
            exec($cmd, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($tempPem)) {
                $pemContent = file_get_contents($tempPem);
                
                // Extract Cert and Key manually from PEM
                if (openssl_x509_read($pemContent)) {
                    $this->certs['cert'] = $pemContent; 
                    
                    // Regex extraction just in case openssl_x509_read doesn't populate both or for private key
                    if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pemContent, $matches)) {
                        $this->certs['cert'] = $matches[0];
                    }
                    if (preg_match('/-----BEGIN PRIVATE KEY-----.*?-----END PRIVATE KEY-----/s', $pemContent, $matches)) {
                        $this->certs['pkey'] = $matches[0];
                    }
                    
                    // Cleanup and validate
                    if (file_exists($tempPem)) unlink($tempPem);
                    if (file_exists($tempFile)) unlink($tempFile);
                    
                    $this->validateLoadedCerts();
                    return;
                }
            }
            
            if (file_exists($tempPem)) unlink($tempPem);
            
        } catch (\Throwable $e) {
            // Fall through to original exception logic if fallback crashes
        }
        
        if (file_exists($tempFile)) unlink($tempFile);

        // If everything fails, throw the original error or a generic one
        $error = openssl_error_string();
        throw new SignatureException(
            "No se pudo leer el certificado .p12 (Fallo nativo y CLI). " .
            "Verifique la contraseña y la integridad del archivo. " .
            "Error OpenSSL: " . ($error ?: 'desconocido')
        );
    }

    /**
     * Validate that the loaded certificates array has the required components
     */
    private function validateLoadedCerts(): void
    {
        // Verify required components exist
        if (empty($this->certs['cert'])) {
            throw new SignatureException("El certificado P12 no contiene un certificado público.");
        }

        if (empty($this->certs['pkey'])) {
            throw new SignatureException("El certificado P12 no contiene una clave privada.");
        }

        // Store extra certificates (intermediate CAs) if present
        if (!empty($this->certs['extracerts'])) {
            $this->extraCerts = $this->certs['extracerts'];
        }
    }

    /**
     * Set the digest algorithm (sha1 or sha256)
     * Note: SRI Ecuador currently requires SHA-1
     */
    public function setDigestAlgorithm(string $algorithm): self
    {
        $allowed = ['sha1', 'sha256'];
        if (!in_array(strtolower($algorithm), $allowed, true)) {
            throw new SignatureException("Algoritmo de digest no soportado: $algorithm. Use: " . implode(', ', $allowed));
        }
        $this->digestAlgorithm = strtolower($algorithm);
        return $this;
    }

    /**
     * Validate the certificate before signing
     */
    public function validateCertificate(): self
    {
        $info = $this->getCertificateInfo();

        // Check if certificate is expired
        if ($info['isExpired']) {
            throw new SignatureException(
                "El certificado ha expirado. " .
                "Fecha de expiración: " . $info['validTo']->format('Y-m-d H:i:s')
            );
        }

        // Check if certificate is not yet valid
        if ($info['validFrom'] > new \DateTimeImmutable()) {
            throw new SignatureException(
                "El certificado aún no es válido. " .
                "Válido desde: " . $info['validFrom']->format('Y-m-d H:i:s')
            );
        }

        // Warn if certificate expires soon (within 30 days)
        $daysUntilExpiry = $info['daysUntilExpiry'];
        if ($daysUntilExpiry !== null && $daysUntilExpiry <= 30) {
            // Just a warning, don't throw - log it if logger is available
            error_log("Advertencia: El certificado expira en $daysUntilExpiry días.");
        }

        $this->validated = true;
        return $this;
    }

    /**
     * Sign the XML document with XAdES-BES
     */
    public function sign(string $xmlContent): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xmlContent)) {
            throw new SignatureException("Error al cargar el XML para la firma. Verifique que sea XML válido.");
        }

        // Get the document ID attribute
        $docId = $dom->documentElement->getAttribute('id') ?: 'comprobante';

        // Generate unique IDs for all signature components
        $uuid = $this->generateUuid();
        $ids = [
            'signature' => "Signature-$uuid",
            'signatureValue' => "SignatureValue-$uuid",
            'signedInfo' => "SignedInfo-$uuid",
            'keyInfo' => "Certificate-$uuid",
            'signedProperties' => "SignedProperties-$uuid",
            'object' => "SignatureObject-$uuid",
            'docRef' => "DocumentRef-$uuid",
            'signedPropsRef' => "SignedPropertiesRef-$uuid",
            'keyInfoRef' => "CertificateRef-$uuid",
        ];

        // Extract certificate information
        $certInfo = $this->extractCertificateInfo();

        // Determine signature algorithm based on key type
        $signatureAlgorithm = $this->getSignatureAlgorithm($certInfo['keyType']);
        $digestAlgorithmUri = $this->getDigestAlgorithmUri();
        $opensslAlgorithm = $this->getOpensslAlgorithm();

        // 1. Create Signature node
        $signature = $dom->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', $ids['signature']);
        $dom->documentElement->appendChild($signature);

        // 2. Build SignedInfo
        $signedInfo = $dom->createElement('ds:SignedInfo');
        $signedInfo->setAttribute('Id', $ids['signedInfo']);
        $signature->appendChild($signedInfo);

        // Canonicalization and Signature methods
        $signedInfo->appendChild($this->createAlgorithmNode($dom, 'ds:CanonicalizationMethod', self::ALG_C14N));
        $signedInfo->appendChild($this->createAlgorithmNode($dom, 'ds:SignatureMethod', $signatureAlgorithm));

        // Reference to document
        $refDoc = $dom->createElement('ds:Reference');
        $refDoc->setAttribute('Id', $ids['docRef']);
        $refDoc->setAttribute('URI', "#$docId");
        $signedInfo->appendChild($refDoc);

        $transforms = $dom->createElement('ds:Transforms');
        $transforms->appendChild($this->createAlgorithmNode($dom, 'ds:Transform', self::ALG_ENVELOPED));
        $refDoc->appendChild($transforms);
        $refDoc->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', $digestAlgorithmUri));
        $refDoc->appendChild($dom->createElement('ds:DigestValue', $this->getDigest($dom->C14N())));

        // Build KeyInfo first (we need its hash)
        $keyInfo = $this->buildKeyInfo($dom, $ids['keyInfo'], $certInfo);

        // Build SignedProperties first (we need its hash)
        $signedProps = $this->buildSignedProperties($dom, $ids['signedProperties'], $ids['docRef'], $certInfo);

        // Reference to SignedProperties
        $refProps = $dom->createElement('ds:Reference');
        $refProps->setAttribute('Id', $ids['signedPropsRef']);
        $refProps->setAttribute('Type', self::TYPE_SIGNED_PROPS);
        $refProps->setAttribute('URI', "#" . $ids['signedProperties']);
        $signedInfo->appendChild($refProps);
        $refProps->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', $digestAlgorithmUri));
        $refProps->appendChild($dom->createElement('ds:DigestValue', $this->getElementDigest($signedProps)));

        // Reference to KeyInfo
        $refKeyInfo = $dom->createElement('ds:Reference');
        $refKeyInfo->setAttribute('Id', $ids['keyInfoRef']);
        $refKeyInfo->setAttribute('URI', "#" . $ids['keyInfo']);
        $signedInfo->appendChild($refKeyInfo);
        $refKeyInfo->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', $digestAlgorithmUri));
        $refKeyInfo->appendChild($dom->createElement('ds:DigestValue', $this->getElementDigest($keyInfo)));

        // 3. SignatureValue - Sign the canonical SignedInfo
        $signedInfoCanonical = $signedInfo->C14N();
        if (!openssl_sign($signedInfoCanonical, $signatureValue, $this->certs['pkey'], $opensslAlgorithm)) {
            $error = openssl_error_string();
            throw new SignatureException("Error al firmar el documento: " . ($error ?: 'error desconocido'));
        }

        $signatureValueNode = $dom->createElement('ds:SignatureValue', base64_encode($signatureValue));
        $signatureValueNode->setAttribute('Id', $ids['signatureValue']);
        $signature->appendChild($signatureValueNode);

        // 4. KeyInfo
        $signature->appendChild($keyInfo);

        // 5. Object with QualifyingProperties
        $object = $dom->createElement('ds:Object');
        $object->setAttribute('Id', $ids['object']);
        $signature->appendChild($object);

        $qualifyingProps = $dom->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProps->setAttribute('Target', "#" . $ids['signature']);
        $object->appendChild($qualifyingProps);
        $qualifyingProps->appendChild($signedProps);

        return $dom->saveXML();
    }

    /**
     * Build the KeyInfo element
     */
    private function buildKeyInfo(DOMDocument $dom, string $keyInfoId, array $certInfo): DOMElement
    {
        $keyInfo = $dom->createElement('ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        // X509Data with certificate
        $x509Data = $dom->createElement('ds:X509Data');
        $keyInfo->appendChild($x509Data);
        
        // 1. Add the signing certificate
        $x509Data->appendChild($dom->createElement('ds:X509Certificate', $certInfo['cleanCert']));

        // 2. Add intermediate certificates if present (Critical for Uanataca/SecurityData)
        foreach ($this->extraCerts as $extraCert) {
            $cleanExtra = $this->cleanCertificate($extraCert);
            // Avoid duplicating the signing certificate if it appears in extracerts
            if ($cleanExtra !== $certInfo['cleanCert']) {
                $x509Data->appendChild($dom->createElement('ds:X509Certificate', $cleanExtra));
            }
        }

        // KeyValue based on key type
        $keyValue = $dom->createElement('ds:KeyValue');
        $keyInfo->appendChild($keyValue);

        if ($certInfo['keyType'] === self::KEY_TYPE_RSA) {
            // RSAKeyValue
            $rsaKeyValue = $dom->createElement('ds:RSAKeyValue');
            $keyValue->appendChild($rsaKeyValue);
            $rsaKeyValue->appendChild($dom->createElement('ds:Modulus', $certInfo['modulus']));
            $rsaKeyValue->appendChild($dom->createElement('ds:Exponent', $certInfo['exponent']));
        } elseif ($certInfo['keyType'] === self::KEY_TYPE_EC) {
            // ECKeyValue (for ECDSA certificates)
            $ecKeyValue = $dom->createElementNS('http://www.w3.org/2009/xmldsig11#', 'dsig11:ECKeyValue');
            $keyValue->appendChild($ecKeyValue);

            if (!empty($certInfo['curve'])) {
                $namedCurve = $dom->createElement('dsig11:NamedCurve');
                $namedCurve->setAttribute('URI', $this->getCurveUri($certInfo['curve']));
                $ecKeyValue->appendChild($namedCurve);
            }

            if (!empty($certInfo['publicKey'])) {
                $ecKeyValue->appendChild($dom->createElement('dsig11:PublicKey', $certInfo['publicKey']));
            }
        }

        return $keyInfo;
    }

    /**
     * Build the SignedProperties element
     */
    private function buildSignedProperties(DOMDocument $dom, string $signedPropsId, string $docRefId, array $certInfo): DOMElement
    {
        $signedProps = $dom->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        // SignedSignatureProperties
        $signedSigProps = $dom->createElement('xades:SignedSignatureProperties');
        $signedProps->appendChild($signedSigProps);

        // SigningTime in ISO 8601 format
        $signedSigProps->appendChild($dom->createElement('xades:SigningTime', date('Y-m-d\TH:i:sP')));

        // SigningCertificate
        $signingCert = $dom->createElement('xades:SigningCertificate');
        $signedSigProps->appendChild($signingCert);

        $cert = $dom->createElement('xades:Cert');
        $signingCert->appendChild($cert);

        // CertDigest
        $certDigestNode = $dom->createElement('xades:CertDigest');
        $cert->appendChild($certDigestNode);
        $certDigestNode->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', $this->getDigestAlgorithmUri()));
        $certDigestNode->appendChild($dom->createElement('ds:DigestValue', $certInfo['certDigest']));

        // IssuerSerial
        $issuerSerial = $dom->createElement('xades:IssuerSerial');
        $cert->appendChild($issuerSerial);
        $issuerSerial->appendChild($dom->createElement('ds:X509IssuerName', $certInfo['issuerName']));
        $issuerSerial->appendChild($dom->createElement('ds:X509SerialNumber', $certInfo['serialNumber']));

        // SignedDataObjectProperties
        $signedDataObjProps = $dom->createElement('xades:SignedDataObjectProperties');
        $signedProps->appendChild($signedDataObjProps);

        $dataObjFormat = $dom->createElement('xades:DataObjectFormat');
        $dataObjFormat->setAttribute('ObjectReference', "#$docRefId");
        $signedDataObjProps->appendChild($dataObjFormat);

        $dataObjFormat->appendChild($dom->createElement('xades:Description', 'Comprobante electrónico'));
        $dataObjFormat->appendChild($dom->createElement('xades:MimeType', 'text/xml'));
        $dataObjFormat->appendChild($dom->createElement('xades:Encoding', 'UTF-8'));

        return $signedProps;
    }

    /**
     * Extract all certificate information needed for signing
     */
    private function extractCertificateInfo(): array
    {
        $publicCert = $this->certs['cert'];
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

        // Build issuer name in RFC 2253 format (reversed order)
        $issuerName = $this->buildDistinguishedName($certData['issuer']);

        // Get serial number (handle both decimal and hex formats)
        $serialNumber = $this->normalizeSerialNumber($certData);

        // Get public key details
        $pubKey = openssl_pkey_get_public($publicCert);
        if ($pubKey === false) {
            throw new SignatureException("No se pudo extraer la clave pública del certificado");
        }

        $pubKeyDetails = openssl_pkey_get_details($pubKey);
        if ($pubKeyDetails === false) {
            throw new SignatureException("No se pudieron obtener los detalles de la clave pública");
        }

        $keyType = $pubKeyDetails['type'];

        $result = [
            'cleanCert' => $cleanCert,
            'certDigest' => $certDigest,
            'issuerName' => $issuerName,
            'serialNumber' => $serialNumber,
            'keyType' => $keyType,
            'subject' => $certData['subject'] ?? [],
            'issuer' => $certData['issuer'] ?? [],
        ];

        // Add key-type specific information
        if ($keyType === self::KEY_TYPE_RSA && isset($pubKeyDetails['rsa'])) {
            $result['modulus'] = base64_encode($pubKeyDetails['rsa']['n']);
            $result['exponent'] = base64_encode($pubKeyDetails['rsa']['e']);
        } elseif ($keyType === self::KEY_TYPE_EC && isset($pubKeyDetails['ec'])) {
            $result['curve'] = $pubKeyDetails['ec']['curve_name'] ?? '';
            // EC public key as base64
            if (isset($pubKeyDetails['ec']['x']) && isset($pubKeyDetails['ec']['y'])) {
                // Uncompressed point format: 04 || X || Y
                $point = chr(0x04) . $pubKeyDetails['ec']['x'] . $pubKeyDetails['ec']['y'];
                $result['publicKey'] = base64_encode($point);
            }
        }

        return $result;
    }

    /**
     * Get detailed certificate information for diagnostics
     */
    public function getCertificateInfo(): array
    {
        $publicCert = $this->certs['cert'];
        $certResource = openssl_x509_read($publicCert);
        $certData = openssl_x509_parse($certResource);
        $pubKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($publicCert));

        // Determine provider
        $provider = $this->detectProvider($certData);

        // Parse dates
        $validFrom = new \DateTimeImmutable('@' . $certData['validFrom_time_t']);
        $validTo = new \DateTimeImmutable('@' . $certData['validTo_time_t']);
        $now = new \DateTimeImmutable();

        $isExpired = $validTo < $now;
        $daysUntilExpiry = $isExpired ? null : (int) $now->diff($validTo)->days;

        // Key type
        $keyTypeName = match ($pubKeyDetails['type']) {
            self::KEY_TYPE_RSA => 'RSA',
            self::KEY_TYPE_EC => 'ECDSA',
            default => 'Unknown'
        };

        return [
            'provider' => $provider,
            'subject' => $certData['subject'] ?? [],
            'subjectCN' => $certData['subject']['CN'] ?? 'N/A',
            'issuer' => $certData['issuer'] ?? [],
            'issuerCN' => $certData['issuer']['CN'] ?? 'N/A',
            'serialNumber' => $this->normalizeSerialNumber($certData),
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'isExpired' => $isExpired,
            'daysUntilExpiry' => $daysUntilExpiry,
            'keyType' => $keyTypeName,
            'keyBits' => $pubKeyDetails['bits'] ?? 0,
            'signatureAlgorithm' => $certData['signatureTypeSN'] ?? 'N/A',
            'hasExtraCerts' => !empty($this->extraCerts),
            'extraCertsCount' => count($this->extraCerts),
        ];
    }

    /**
     * Detect the certificate provider
     */
    private function detectProvider(array $certData): string
    {
        $issuerString = strtolower(json_encode($certData['issuer']));
        $subjectString = strtolower(json_encode($certData['subject']));

        foreach (self::KNOWN_PROVIDERS as $providerName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($issuerString, $keyword) || str_contains($subjectString, $keyword)) {
                    return $providerName;
                }
            }
        }

        return 'Desconocido';
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
     */
    private function buildDistinguishedName(array $dn): string
    {
        $parts = [];

        // Process in reverse order for RFC 2253 compliance
        foreach (array_reverse($dn) as $key => $value) {
            // Handle array values (multiple values for same attribute)
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = $this->escapeDistinguishedNameValue($key, $v);
                }
            } else {
                $parts[] = $this->escapeDistinguishedNameValue($key, $value);
            }
        }

        return implode(',', $parts);
    }

    /**
     * Escape special characters in DN values
     */
    private function escapeDistinguishedNameValue(string $key, string $value): string
    {
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
     */
    private function normalizeSerialNumber(array $certData): string
    {
        // 1. Try to use the parsed serialNumber if it's already a clean numeric string
        if (isset($certData['serialNumber']) && is_numeric($certData['serialNumber']) && !str_contains($certData['serialNumber'], 'E')) {
            return (string) $certData['serialNumber'];
        }

        // 2. Use Hexadecimal representation if available (most reliable source)
        if (isset($certData['serialNumberHex'])) {
            $hex = $certData['serialNumberHex'];
            // Remove any colons, spaces, or 0x prefix
            $hex = str_replace([':', ' ', '0x'], '', $hex);
            
            // Convert using BCMath (Arbitrary Precision Mathematics)
            if (function_exists('bchexdec')) {
                return bchexdec($hex);
            }
            
            // Fallback: Custom Hex to Dec conversion logic using BCMath
            if (extension_loaded('bcmath')) {
                $len = strlen($hex);
                $dec = '0';
                for ($i = 0; $i < $len; $i++) {
                    $dec = bcadd(bcmul($dec, '16'), (string)hexdec($hex[$i]));
                }
                return $dec;
            }

            // Fallback: GMP
            if (function_exists('gmp_strval')) {
                return gmp_strval(gmp_init($hex, 16), 10);
            }
        }

        // 3. Last resort (may lose precision for massive numbers > PHP_INT_MAX)
        return isset($certData['serialNumber']) ? (string)$certData['serialNumber'] : '0';
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
        $node = $dom->createElement($name);
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

    /**
     * Calculate digest of a DOM element using C14N
     */
    private function getElementDigest(DOMElement $element): string
    {
        return base64_encode(hash($this->digestAlgorithm, $element->C14N(), true));
    }

    /**
     * Generate a UUID for signature component IDs
     */
    private function generateUuid(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get the list of intermediate certificates (if any)
     *
     * @return array<string>
     */
    public function getIntermediateCertificates(): array
    {
        return $this->extraCerts;
    }

    /**
     * Check if the certificate is valid for signing
     */
    public function isValid(): bool
    {
        try {
            $info = $this->getCertificateInfo();
            return !$info['isExpired'];
        } catch (\Throwable $e) {
            return false;
        }
    }
}
