<?php

declare(strict_types=1);

namespace Teran\Sri\Signature;

use Teran\Sri\Exceptions\SignatureException;
use DOMDocument;
use DOMElement;

class XadesSignature
{
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    private const ALG_C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const ALG_SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';
    private const ALG_RSA_SHA1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    private const ALG_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const TYPE_SIGNED_PROPS = 'http://uri.etsi.org/01903#SignedProperties';

    private string $p12Content;
    private string $password;
    private array $certs = [];

    public function __construct(string $p12Content, string $password)
    {
        $this->p12Content = $p12Content;
        $this->password = $password;
        $this->loadCertificate();
    }

    private function loadCertificate(): void
    {
        if (!openssl_pkcs12_read($this->p12Content, $this->certs, $this->password)) {
            throw new SignatureException("No se pudo leer el certificado .p12. Verifique la contraseña.");
        }
    }

    public function sign(string $xmlContent): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xmlContent)) {
            throw new SignatureException("Error al cargar el XML para la firma.");
        }

        // Obtener el ID del documento raíz
        $docId = $dom->documentElement->getAttribute('id') ?: 'comprobante';

        // Generar UUIDs únicos
        $uuid = $this->generateUuid();
        $signatureId = "Signature-$uuid";
        $signatureValueId = "SignatureValue-$uuid";
        $signedInfoId = "SignedInfo-$uuid";
        $keyInfoId = "Certificate-$uuid";
        $signedPropertiesId = "SignedProperties-$uuid";
        $objectId = "SignatureObject-$uuid";
        $docRefId = "DocumentRef-$uuid";
        $signedPropsRefId = "SignedPropertiesRef-$uuid";
        $keyInfoRefId = "CertificateRef-$uuid";

        // Extraer información del certificado
        $certInfo = $this->extractCertificateInfo();

        // 1. Crear nodo Signature
        $signature = $dom->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', $signatureId);
        $dom->documentElement->appendChild($signature);

        // 2. Construir SignedInfo
        $signedInfo = $dom->createElement('ds:SignedInfo');
        $signedInfo->setAttribute('Id', $signedInfoId);
        $signature->appendChild($signedInfo);

        // Métodos de canonicalización y firma
        $signedInfo->appendChild($this->createAlgorithmNode($dom, 'ds:CanonicalizationMethod', self::ALG_C14N));
        $signedInfo->appendChild($this->createAlgorithmNode($dom, 'ds:SignatureMethod', self::ALG_RSA_SHA1));

        // Referencia al documento
        $refDoc = $dom->createElement('ds:Reference');
        $refDoc->setAttribute('Id', $docRefId);
        $refDoc->setAttribute('URI', "#$docId");
        $signedInfo->appendChild($refDoc);

        $transforms = $dom->createElement('ds:Transforms');
        $transforms->appendChild($this->createAlgorithmNode($dom, 'ds:Transform', self::ALG_ENVELOPED));
        $refDoc->appendChild($transforms);
        $refDoc->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', self::ALG_SHA1));
        $refDoc->appendChild($dom->createElement('ds:DigestValue', $this->getDigest($dom->C14N())));

        // Construir KeyInfo primero (necesitamos su hash)
        $keyInfo = $this->buildKeyInfo($dom, $keyInfoId, $certInfo);

        // Construir SignedProperties primero (necesitamos su hash)
        $signedProps = $this->buildSignedProperties($dom, $signedPropertiesId, $docRefId, $certInfo);

        // Referencia a SignedProperties
        $refProps = $dom->createElement('ds:Reference');
        $refProps->setAttribute('Id', $signedPropsRefId);
        $refProps->setAttribute('Type', self::TYPE_SIGNED_PROPS);
        $refProps->setAttribute('URI', "#$signedPropertiesId");
        $signedInfo->appendChild($refProps);
        $refProps->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', self::ALG_SHA1));
        $refProps->appendChild($dom->createElement('ds:DigestValue', $this->getDigestWithNamespaces($signedProps, [
            ['prefix' => 'xades', 'uri' => self::NS_XADES],
            ['prefix' => 'ds', 'uri' => self::NS_DS]
        ])));

        // Referencia a KeyInfo
        $refKeyInfo = $dom->createElement('ds:Reference');
        $refKeyInfo->setAttribute('Id', $keyInfoRefId);
        $refKeyInfo->setAttribute('URI', "#$keyInfoId");
        $signedInfo->appendChild($refKeyInfo);
        $refKeyInfo->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', self::ALG_SHA1));
        $refKeyInfo->appendChild($dom->createElement('ds:DigestValue', $this->getDigestWithNamespaces($keyInfo, [
            ['prefix' => 'ds', 'uri' => self::NS_DS]
        ])));

        // 3. SignatureValue
        openssl_sign($signedInfo->C14N(), $signatureValue, $this->certs['pkey'], OPENSSL_ALGO_SHA1);
        $signatureValueNode = $dom->createElement('ds:SignatureValue', base64_encode($signatureValue));
        $signatureValueNode->setAttribute('Id', $signatureValueId);
        $signature->appendChild($signatureValueNode);

        // 4. KeyInfo
        $signature->appendChild($keyInfo);

        // 5. Object con QualifyingProperties
        $object = $dom->createElement('ds:Object');
        $object->setAttribute('Id', $objectId);
        $signature->appendChild($object);

        $qualifyingProps = $dom->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProps->setAttribute('Target', "#$signatureId");
        $object->appendChild($qualifyingProps);
        $qualifyingProps->appendChild($signedProps);

        return $dom->saveXML();
    }

    private function buildKeyInfo(DOMDocument $dom, string $keyInfoId, array $certInfo): DOMElement
    {
        $keyInfo = $dom->createElement('ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        // X509Data
        $x509Data = $dom->createElement('ds:X509Data');
        $keyInfo->appendChild($x509Data);
        $x509Data->appendChild($dom->createElement('ds:X509Certificate', $certInfo['cleanCert']));

        // KeyValue con RSAKeyValue
        $keyValue = $dom->createElement('ds:KeyValue');
        $keyInfo->appendChild($keyValue);

        $rsaKeyValue = $dom->createElement('ds:RSAKeyValue');
        $keyValue->appendChild($rsaKeyValue);
        $rsaKeyValue->appendChild($dom->createElement('ds:Modulus', $certInfo['modulus']));
        $rsaKeyValue->appendChild($dom->createElement('ds:Exponent', $certInfo['exponent']));

        return $keyInfo;
    }

    private function buildSignedProperties(DOMDocument $dom, string $signedPropsId, string $docRefId, array $certInfo): DOMElement
    {
        $signedProps = $dom->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        // SignedSignatureProperties
        $signedSigProps = $dom->createElement('xades:SignedSignatureProperties');
        $signedProps->appendChild($signedSigProps);

        // SigningTime
        $signedSigProps->appendChild($dom->createElement('xades:SigningTime', date('Y-m-d\TH:i:sP')));

        // SigningCertificate
        $signingCert = $dom->createElement('xades:SigningCertificate');
        $signedSigProps->appendChild($signingCert);

        $cert = $dom->createElement('xades:Cert');
        $signingCert->appendChild($cert);

        // CertDigest
        $certDigestNode = $dom->createElement('xades:CertDigest');
        $cert->appendChild($certDigestNode);
        $certDigestNode->appendChild($this->createAlgorithmNode($dom, 'ds:DigestMethod', self::ALG_SHA1));
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

    private function extractCertificateInfo(): array
    {
        $publicCert = $this->certs['cert'];
        $cleanCert = str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r"], '', $publicCert);
        $certDigest = base64_encode(hash('sha1', base64_decode($cleanCert), true));

        // Extraer información del certificado
        $certResource = openssl_x509_read($publicCert);
        $certData = openssl_x509_parse($certResource);

        // Extraer issuer name en formato RFC 2253
        $issuerParts = [];
        foreach (array_reverse($certData['issuer']) as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $issuerParts[] = "$key=$v";
                }
            } else {
                $issuerParts[] = "$key=$value";
            }
        }
        $issuerName = implode(',', $issuerParts);

        // Extraer serial number
        $serialNumber = $certData['serialNumber'] ?? $certData['serialNumberHex'] ?? '0';

        // Extraer modulus y exponent de la clave pública
        $pubKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($publicCert));
        $modulus = base64_encode($pubKeyDetails['rsa']['n']);
        $exponent = base64_encode($pubKeyDetails['rsa']['e']);

        return [
            'cleanCert' => $cleanCert,
            'certDigest' => $certDigest,
            'issuerName' => $issuerName,
            'serialNumber' => $serialNumber,
            'modulus' => $modulus,
            'exponent' => $exponent,
        ];
    }

    private function createAlgorithmNode(DOMDocument $dom, string $name, string $algorithm): DOMElement
    {
        $node = $dom->createElement($name);
        $node->setAttribute('Algorithm', $algorithm);
        return $node;
    }

    private function getDigest(string $content): string
    {
        return base64_encode(hash('sha1', $content, true));
    }

    private function getDigestWithNamespaces(DOMElement $element, array $namespaces): string
    {
        // Para calcular el digest con namespaces heredados, usamos C14N
        return base64_encode(hash('sha1', $element->C14N(), true));
    }

    private function generateUuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
