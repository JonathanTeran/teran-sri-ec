<?php

declare(strict_types=1);

namespace Teran\Sri\Signature;

use Teran\Sri\Exceptions\SignatureException;
use DOMDocument;
use DOMElement;

class XadesSignature
{
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

        // Identificadores únicos
        $randId = bin2hex(random_bytes(4));
        $signatureId = "Signature-$randId";
        $signatureValueId = "SignatureValue-$randId";
        $signedInfoId = "SignedInfo-$randId";
        $keyInfoId = "KeyInfo-$randId";
        $signedPropertiesId = "SignedProperties-$randId";
        $objectId = "Object-$randId";

        $publicCert = $this->certs['cert'];
        $cleanCert = str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r"], '', $publicCert);
        $certDigest = base64_encode(hash('sha1', base64_decode($cleanCert), true));

        // 1. Crear nodo Signature
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
        $signature->setAttribute('Id', $signatureId);
        $dom->documentElement->appendChild($signature);

        // 2. SignedInfo
        $signedInfo = $dom->createElement('ds:SignedInfo');
        $signedInfo->setAttribute('Id', $signedInfoId);
        $signature->appendChild($signedInfo);

        $signedInfo->appendChild($this->createMethod($dom, 'ds:CanonicalizationMethod', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'));
        $signedInfo->appendChild($this->createMethod($dom, 'ds:SignatureMethod', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1'));

        // Referencia al Documento (URI="")
        $refDoc = $dom->createElement('ds:Reference');
        $refDoc->setAttribute('URI', '');
        $signedInfo->appendChild($refDoc);
        $transforms = $dom->createElement('ds:Transforms');
        $transforms->appendChild($this->createMethod($dom, 'ds:Transform', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature'));
        $refDoc->appendChild($transforms);
        $refDoc->appendChild($this->createMethod($dom, 'ds:DigestMethod', 'http://www.w3.org/2000/09/xmldsig#sha1'));
        $refDoc->appendChild($dom->createElement('ds:DigestValue', base64_encode(hash('sha1', $dom->C14N(), true))));

        // 3. Object (QualifyingProperties)
        $object = $dom->createElement('ds:Object');
        $object->setAttribute('Id', $objectId);
        $signature->appendChild($object);

        $qualifyingProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'etsi:QualifyingProperties');
        $qualifyingProps->setAttribute('Target', "#$signatureId");
        $object->appendChild($qualifyingProps);

        $signedProps = $dom->createElement('etsi:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropertiesId);
        $qualifyingProps->appendChild($signedProps);

        $signedSigProps = $dom->createElement('etsi:SignedSignatureProperties');
        $signedProps->appendChild($signedSigProps);
        $signedSigProps->appendChild($dom->createElement('etsi:SigningTime', date('Y-m-d\TH:i:sP')));
        
        $signingCert = $dom->createElement('etsi:SigningCertificate');
        $signedSigProps->appendChild($signingCert);
        $cert = $dom->createElement('etsi:Cert');
        $signingCert->appendChild($cert);
        $certDigestNode = $dom->createElement('etsi:CertDigest');
        $cert->appendChild($certDigestNode);
        $certDigestNode->appendChild($this->createMethod($dom, 'etsi:DigestMethod', 'http://www.w3.org/2000/09/xmldsig#sha1'));
        $certDigestNode->appendChild($dom->createElement('etsi:DigestValue', $certDigest));

        // Referencia a SignedProperties
        $refProps = $dom->createElement('ds:Reference');
        $refProps->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $refProps->setAttribute('URI', "#$signedPropertiesId");
        $signedInfo->appendChild($refProps);
        $refProps->appendChild($this->createMethod($dom, 'ds:DigestMethod', 'http://www.w3.org/2000/09/xmldsig#sha1'));
        $refProps->appendChild($dom->createElement('ds:DigestValue', base64_encode(hash('sha1', $signedProps->C14N(), true))));

        // 4. KeyInfo
        $keyInfo = $dom->createElement('ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);
        $signature->appendChild($keyInfo);
        $x509Data = $dom->createElement('ds:X509Data');
        $keyInfo->appendChild($x509Data);
        $x509Data->appendChild($dom->createElement('ds:X509Certificate', $cleanCert));

        // 5. SignatureValue
        openssl_sign($signedInfo->C14N(), $signatureValue, $this->certs['pkey'], OPENSSL_ALGO_SHA1);
        $signatureValueNode = $dom->createElement('ds:SignatureValue', base64_encode($signatureValue));
        $signatureValueNode->setAttribute('Id', $signatureValueId);
        
        // Insertar SignatureValue después de SignedInfo
        $signature->insertBefore($signatureValueNode, $keyInfo);

        return $dom->saveXML();
    }

    private function createMethod(DOMDocument $dom, string $name, string $algo): DOMElement
    {
        $node = $dom->createElement($name);
        $node->setAttribute('Algorithm', $algo);
        return $node;
    }
}
