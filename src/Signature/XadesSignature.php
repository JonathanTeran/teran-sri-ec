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

        // 2. Fallback: Try using CLI OpenSSL if PHP fails (Common on MacOS/Homebrew setups)
        // We need to write the content to a temp file first since CLI needs a file
        $tempFile = stream_get_meta_data(tmpfile())['uri'];
        file_put_contents($tempFile, $this->p12Content);

        try {
            // Determine OpenSSL Binary
            $opensslBin = 'openssl';
            if (file_exists('/opt/homebrew/opt/openssl@3/bin/openssl')) {
                $opensslBin = '/opt/homebrew/opt/openssl@3/bin/openssl';
            } elseif (file_exists('/usr/local/opt/openssl@3/bin/openssl')) {
                $opensslBin = '/usr/local/opt/openssl@3/bin/openssl';
            } elseif (file_exists('/opt/homebrew/opt/openssl@1.1/bin/openssl')) { // Fallback to legacy reader
                $opensslBin = '/opt/homebrew/opt/openssl@1.1/bin/openssl';
            }

            // Convert P12 to readable PEM using CLI
            // This bypasses PHP's internal checks which might be too strict (e.g. legacy algos)
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
                
                // Extract Cert and Key from PEM manually
                if (openssl_x509_read($pemContent)) {
                    $this->certs['cert'] = $pemContent; // Contains both often, but let's separate if needed
                    
                    // Basic separation logic
                    if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pemContent, $matches)) {
                        $this->certs['cert'] = $matches[0];
                    }
                    if (preg_match('/-----BEGIN PRIVATE KEY-----.*?-----END PRIVATE KEY-----/s', $pemContent, $matches)) {
                        $this->certs['pkey'] = $matches[0];
                    }
                    
                    unlink($tempPem);
                    unlink($tempFile);
                    
                    $this->validateLoadedCerts();
                    return;
                }
            }
            
            if (file_exists($tempPem)) unlink($tempPem);
        } catch (\Throwable $e) {
            // Silence, fall through to throw original error
        }
        
        if (file_exists($tempFile)) unlink($tempFile);

        $error = openssl_error_string();
        throw new SignatureException(
            "No se pudo leer el certificado .p12 (ni con PHP nativo ni CLI). " .
            "Verifique la contraseña y que el archivo no esté corrupto. " .
            "Error OpenSSL: " . ($error ?: 'desconocido')
        );
    }

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
        // Match reference ID style: SignatureXXXXXX (numeric/hex suffix)
        $suffix = substr($uuid, 0, 6);
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
        $certInfo = $this->extractCertificateInfo();

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

        // REMOVED REF KEYINFO: Not required for XAdES-BES and causes structure errors in some validators
        // $refKeyInfo = $dom->createElementNS(self::NS_DS, 'ds:Reference');
        // ...

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
        $keyInfo = $this->buildKeyInfo($dom, $ids['keyInfo'], $certInfo);
        $signature->appendChild($keyInfo);

        $object = $dom->createElementNS(self::NS_DS, 'ds:Object');
        $object->setAttribute('Id', $ids['object']);
        $signature->appendChild($object);

        $qualifyingProps = $dom->createElementNS(self::NS_XADES, 'etsi:QualifyingProperties');
        $qualifyingProps->setAttribute('Target', "#" . $ids['signature']);
        $object->appendChild($qualifyingProps);

        $signedProps = $this->buildSignedProperties($dom, $ids['signedProperties'], $ids['docRef'], $certInfo);
        $qualifyingProps->appendChild($signedProps);

        // 3. CALCULATION PHASE
        
        // A. Document Digest (Detach signature temporarily)
        $signature->parentNode->removeChild($signature);
        $docDigestNode->nodeValue = $this->getDigest($dom->C14N());
        $dom->documentElement->appendChild($signature);

        // B. Component Digests
        $signedPropsDigestNode->nodeValue = base64_encode(hash($this->digestAlgorithm, $signedProps->C14N(), true));
        // KeyInfo digest removed because we removed the reference


        // C. SignatureValue
        $signedInfoCanonical = $signedInfo->C14N();
        if (!openssl_sign($signedInfoCanonical, $signatureValue, $this->certs['pkey'], $opensslAlgorithm)) {
            $error = openssl_error_string();
            throw new SignatureException("Error al firmar el documento: " . ($error ?: 'error desconocido'));
        }
        $signatureValueNode->nodeValue = trim(chunk_split(base64_encode($signatureValue), 76, "\n"));

        // CLEANUP REMOVED: Modifying the DOM after calculating digests (KeyInfo, SignedProperties)
        // invalidates the hashes because the final XML differs from what was digested.
        // PHP DOMDocument handles namespaces sufficiently well.


        // Disable formatOutput to avoid whitespace issues in signature verification
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        // Return signed XML with proper indentation and FULL closing tags (not self-closing)
        // CRITICAL: LIBXML_NOEMPTYTAG ensures <tag></tag> instead of <tag/> which SRI requires
        $finalXml = $dom->saveXML(null, LIBXML_NOEMPTYTAG);
        
        // EMERGENCY DEBUG: Save the exact byte string
        file_put_contents(base_path('storage/app/final_signed.xml'), $finalXml);
        
        return $finalXml;
    }

    /**
     * Build the KeyInfo element
     */
    private function buildKeyInfo(DOMDocument $dom, string $keyInfoId, array $certInfo): DOMElement
    {
        $keyInfo = $dom->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        // X509Data with certificate
        $x509Data = $dom->createElementNS(self::NS_DS, 'ds:X509Data');
        $keyInfo->appendChild($x509Data);
        
        // 1. Add the signing certificate (Chunk split formatted)
        $certContent = trim(chunk_split($certInfo['cleanCert'], 76, "\n"));
        $x509Data->appendChild($dom->createElementNS(self::NS_DS, 'ds:X509Certificate', $certContent));

        // 2. Add intermediate certificates if present
        foreach ($this->extraCerts as $extraCert) {
            $cleanExtra = $this->cleanCertificate($extraCert);
            if ($cleanExtra !== $certInfo['cleanCert']) {
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
            $modulusContent = trim(chunk_split($certInfo['modulus'], 76, "\n"));
            $rsaKeyValue->appendChild($dom->createElementNS(self::NS_DS, 'ds:Modulus', $modulusContent));
            $rsaKeyValue->appendChild($dom->createElementNS(self::NS_DS, 'ds:Exponent', $certInfo['exponent']));
        } elseif ($certInfo['keyType'] === self::KEY_TYPE_EC) {
            // ECKeyValue (for ECDSA certificates) - Namespace is specific
            $nsEc = 'http://www.w3.org/2009/xmldsig11#';
            $ecKeyValue = $dom->createElementNS($nsEc, 'dsig11:ECKeyValue');
            $keyValue->appendChild($ecKeyValue);

            if (!empty($certInfo['curve'])) {
                $namedCurve = $dom->createElementNS($nsEc, 'dsig11:NamedCurve');
                $namedCurve->setAttribute('URI', $this->getCurveUri($certInfo['curve']));
                $ecKeyValue->appendChild($namedCurve);
            }

            if (!empty($certInfo['publicKey'])) {
                $publicKeyContent = chunk_split($certInfo['publicKey'], 76, "\n");
                $ecKeyValue->appendChild($dom->createElementNS($nsEc, 'dsig11:PublicKey', $publicKeyContent));
            }
        }

        return $keyInfo;
    }

    /**
     * Build the SignedProperties element
     */
    private function buildSignedProperties(DOMDocument $dom, string $signedPropsId, string $docRefId, array $certInfo): DOMElement
    {
        $signedProps = $dom->createElementNS(self::NS_XADES, 'etsi:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        // SignedSignatureProperties
        $signedSigProps = $dom->createElementNS(self::NS_XADES, 'etsi:SignedSignatureProperties');
        $signedProps->appendChild($signedSigProps);

        // SigningTime in ISO 8601 format
        $signedSigProps->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:SigningTime', date('Y-m-d\TH:i:sP')));

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

        $dataObjFormat->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:Description', 'DOCUMENTO EMITIDO CON ECUAFACT. LA FACTURACION ELECTRONICA DEL ECUADOR. Visitenos en http://www.ecuanexus.com '));
        $dataObjFormat->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:MimeType', 'text/xml'));
        // Removed etsi:Encoding to match reference order and structure exactly
        // $dataObjFormat->appendChild($dom->createElementNS(self::NS_XADES, 'etsi:Encoding', 'UTF-8'));
         
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
            $escaped = '';
            // Handle array values (multiple values for same attribute)
            if (is_array($value)) {
                foreach ($value as $v) {
                    $res = $this->escapeDistinguishedNameValue($key, $v);
                    if ($res !== '') $parts[] = $res;
                }
            } else {
                $res = $this->escapeDistinguishedNameValue($key, $value);
                if ($res !== '') $parts[] = $res;
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
             // (Escaping applied if needed, but usually this OID is alphanumeric)
             return "organizationIdentifier=" . $value;
        }

        // Escape special characters per RFC 2253


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
        // $name comes with prefix 'ds:'
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

    /**
     * Calculate digest of a DOM element using C14N
     */
    private function getElementDigest(DOMElement $element): string
    {
        // Workaround for PHP DOMNode::C14N() returning empty string on some environments/isolated nodes
        // We create a temporary document, import the node and C14N the root
        $tempDom = new DOMDocument('1.0', 'UTF-8');
        $tempDom->preserveWhiteSpace = false;
        $tempDom->formatOutput = false;
        
        $imported = $tempDom->importNode($element, true);
        $tempDom->appendChild($imported);
        
        $canonical = $tempDom->C14N();
        
        return base64_encode(hash($this->digestAlgorithm, $canonical, true));
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
