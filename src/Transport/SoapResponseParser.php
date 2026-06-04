<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;
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
        $estado = $this->text($xp, '//*[local-name()="estado"]') ?? 'DEVUELTA';
        $mensajes = $this->messages($xp, '//*[local-name()="mensajes"]/*[local-name()="mensaje"]');
        return new ReceptionOutcome($estado, $mensajes);
    }

    public function parseAuthorization(string $responseXml): AuthorizationOutcome
    {
        $xp = $this->xpath($responseXml);
        $auth = $xp->query('//*[local-name()="autorizacion"]')->item(0);
        if (!$auth instanceof DOMNode) {
            return new AuthorizationOutcome('NO AUTORIZADO');
        }
        return new AuthorizationOutcome(
            estado: $this->childText($auth, 'estado') ?? 'NO AUTORIZADO',
            numeroAutorizacion: $this->childText($auth, 'numeroAutorizacion'),
            fechaAutorizacion: $this->childText($auth, 'fechaAutorizacion'),
            comprobante: $this->childText($auth, 'comprobante'),
            mensajes: $this->messages(new DOMXPath($auth->ownerDocument), './/*[local-name()="mensajes"]/*[local-name()="mensaje"]', $auth),
        );
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function text(DOMXPath $xp, string $query): ?string
    {
        $n = $xp->query($query)->item(0);
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
        $out = [];
        foreach ($nodes as $m) {
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
