<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;
use Teran\Sri\Exceptions\CommunicationException;
use DOMDocument;
use DOMXPath;
use DOMNode;

/**
 * Parser namespace-agnóstico (local-name) de las respuestas SOAP del SRI offline.
 */
final class SoapResponseParser
{
    public function parseReception(string $responseXml): ReceptionOutcome
    {
        $xp = $this->xpath($responseXml);
        $this->throwOnFault($xp);
        $estado = $this->text($xp, '//*[local-name()="estado"]') ?? 'DEVUELTA';
        $mensajes = $this->messages($xp, '//*[local-name()="mensajes"]/*[local-name()="mensaje"]');
        return new ReceptionOutcome($estado, $mensajes);
    }

    public function parseAuthorization(string $responseXml): AuthorizationOutcome
    {
        $xp = $this->xpath($responseXml);
        $this->throwOnFault($xp);
        $authResult = $xp->query('//*[local-name()="autorizacion"]');
        $auth = ($authResult !== false) ? $authResult->item(0) : null;
        if (!$auth instanceof DOMNode) {
            return new AuthorizationOutcome('EN PROCESO');
        }
        $ownerDoc = $auth->ownerDocument;
        $ownerXp = ($ownerDoc !== null) ? new DOMXPath($ownerDoc) : $xp;
        return new AuthorizationOutcome(
            estado: $this->childText($auth, 'estado') ?? 'EN PROCESO',
            numeroAutorizacion: $this->childText($auth, 'numeroAutorizacion'),
            fechaAutorizacion: $this->childText($auth, 'fechaAutorizacion'),
            comprobante: $this->childText($auth, 'comprobante'),
            mensajes: $this->messages($ownerXp, './/*[local-name()="mensajes"]/*[local-name()="mensaje"]', $auth),
        );
    }

    private function throwOnFault(DOMXPath $xp): void
    {
        $faultResult = $xp->query('//*[local-name()="Fault"]');
        $faultNode = ($faultResult !== false) ? $faultResult->item(0) : null;
        if ($faultNode === null) {
            return;
        }
        $faultText = $this->text($xp, '//*[local-name()="faultstring"]') ?? 'unknown SOAP fault';
        throw new CommunicationException('SRI SOAP Fault: ' . $faultText);
    }

    private function xpath(string $xml): DOMXPath
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return new DOMXPath($dom);
    }

    private function text(DOMXPath $xp, string $query): ?string
    {
        $result = $xp->query($query);
        $n = ($result !== false) ? $result->item(0) : null;
        return $n instanceof DOMNode ? trim($n->textContent) : null;
    }

    private function childText(DOMNode $node, string $localName): ?string
    {
        foreach ($node->childNodes as $c) {
            if ($c->localName === $localName) {
                $v = trim($c->textContent);
                return $v === '' ? null : $v;
            }
        }
        return null;
    }

    /** @return Message[] */
    private function messages(DOMXPath $xp, string $query, ?DOMNode $context = null): array
    {
        $nodes = $context ? $xp->query($query, $context) : $xp->query($query);
        if ($nodes === false) {
            return [];
        }
        $out = [];
        foreach ($nodes as $m) {
            if (!$m instanceof DOMNode) {
                continue;
            }
            $out[] = new Message(
                identificador: $this->childText($m, 'identificador') ?? '',
                mensaje: $this->childText($m, 'mensaje') ?? '',
                tipo: $this->childText($m, 'tipo') ?? '',
                informacionAdicional: $this->childText($m, 'informacionAdicional') ?? '',
            );
        }
        return $out;
    }
}
