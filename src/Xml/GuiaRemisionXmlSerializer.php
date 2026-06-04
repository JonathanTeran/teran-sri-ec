<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\Documents\Destinatario;
use Teran\Sri\Documents\GuiaRemision;
use DOMDocument;
use DOMElement;

final class GuiaRemisionXmlSerializer
{
    private const VERSION = '1.1.0';
    private const COD_DOC = '06';

    public function serialize(GuiaRemision $doc, string $claveAcceso): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('guiaRemision');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $doc, $claveAcceso);
        $this->infoGuiaRemision($b, $root, $doc);
        $this->destinatarios($b, $root, $doc);

        return $dom->saveXML();
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, GuiaRemision $doc, string $claveAcceso): void
    {
        $info = $doc->infoTributaria;
        $node = $b->child($root, 'infoTributaria');
        $b->child($node, 'ambiente', $info->ambiente->value);
        $b->child($node, 'tipoEmision', $info->tipoEmision->value);
        $b->child($node, 'razonSocial', $info->razonSocial);
        if ($info->nombreComercial !== null) {
            $b->child($node, 'nombreComercial', $info->nombreComercial);
        }
        $b->child($node, 'ruc', $info->ruc);
        $b->child($node, 'claveAcceso', $claveAcceso);
        $b->child($node, 'codDoc', self::COD_DOC);
        $b->child($node, 'estab', $info->estab);
        $b->child($node, 'ptoEmi', $info->ptoEmi);
        $b->child($node, 'secuencial', $info->secuencial);
        $b->child($node, 'dirMatriz', $info->dirMatriz);
    }

    private function infoGuiaRemision(DomBuilder $b, DOMElement $root, GuiaRemision $doc): void
    {
        $node = $b->child($root, 'infoGuiaRemision');

        // Mirror the 1.x generator's simpleFields list with isset logic:
        // 'dirEstablecimiento', 'dirPartida', 'razonSocialTransportista',
        // 'tipoIdentificacionTransportista', 'rucTransportista', 'rise',
        // 'obligadoContabilidad', 'contribuyenteEspecial', 'fechaIniTransporte',
        // 'fechaFinTransporte', 'placa'
        $b->child($node, 'dirEstablecimiento', $doc->dirEstablecimiento);
        $b->child($node, 'dirPartida', $doc->dirPartida);
        $b->child($node, 'razonSocialTransportista', $doc->razonSocialTransportista);
        $b->child($node, 'tipoIdentificacionTransportista', $doc->tipoIdentificacionTransportista);
        $b->child($node, 'rucTransportista', $doc->rucTransportista);
        if ($doc->rise !== null) {
            $b->child($node, 'rise', $doc->rise);
        }
        if ($doc->obligadoContabilidad !== null) {
            $b->child($node, 'obligadoContabilidad', $doc->obligadoContabilidad);
        }
        if ($doc->contribuyenteEspecial !== null) {
            $b->child($node, 'contribuyenteEspecial', $doc->contribuyenteEspecial);
        }
        $b->child($node, 'fechaIniTransporte', $doc->fechaIniTransporte);
        $b->child($node, 'fechaFinTransporte', $doc->fechaFinTransporte);
        $b->child($node, 'placa', $doc->placa);
    }

    private function destinatarios(DomBuilder $b, DOMElement $root, GuiaRemision $doc): void
    {
        $destsNode = $b->child($root, 'destinatarios');

        foreach ($doc->destinatarios as $dest) {
            $destItem = $b->child($destsNode, 'destinatario');

            // Mirror the 1.x generator's simpleFields list with isset logic:
            // 'identificacionDestinatario', 'razonSocialDestinatario',
            // 'dirDestinatario', 'motivoTraslado', 'docAduaneroUnico',
            // 'codEstabDestino', 'ruta', 'codDocSustento', 'numDocSustento',
            // 'numAutDocSustento', 'fechaEmisionDocSustento'
            $b->child($destItem, 'identificacionDestinatario', $dest->identificacionDestinatario);
            $b->child($destItem, 'razonSocialDestinatario', $dest->razonSocialDestinatario);
            $b->child($destItem, 'dirDestinatario', $dest->dirDestinatario);
            $b->child($destItem, 'motivoTraslado', $dest->motivoTraslado);
            if ($dest->docAduaneroUnico !== null) {
                $b->child($destItem, 'docAduaneroUnico', $dest->docAduaneroUnico);
            }
            if ($dest->codEstabDestino !== null) {
                $b->child($destItem, 'codEstabDestino', $dest->codEstabDestino);
            }
            if ($dest->ruta !== null) {
                $b->child($destItem, 'ruta', $dest->ruta);
            }
            if ($dest->codDocSustento !== null) {
                $b->child($destItem, 'codDocSustento', $dest->codDocSustento);
            }
            if ($dest->numDocSustento !== null) {
                $b->child($destItem, 'numDocSustento', $dest->numDocSustento);
            }
            if ($dest->numAutDocSustento !== null) {
                $b->child($destItem, 'numAutDocSustento', $dest->numAutDocSustento);
            }
            if ($dest->fechaEmisionDocSustento !== null) {
                $b->child($destItem, 'fechaEmisionDocSustento', $dest->fechaEmisionDocSustento);
            }

            // Detalles del destinatario
            if (!empty($dest->detalles)) {
                $detallesNode = $b->child($destItem, 'detalles');
                foreach ($dest->detalles as $det) {
                    $detItem = $b->child($detallesNode, 'detalle');

                    // Mirror the 1.x generator's detFields list with isset logic:
                    // 'codigoInterno', 'codigoAdicional', 'descripcion', 'cantidad'
                    if (isset($det['codigoInterno'])) {
                        $b->child($detItem, 'codigoInterno', (string) $det['codigoInterno']);
                    }
                    if (isset($det['codigoAdicional'])) {
                        $b->child($detItem, 'codigoAdicional', (string) $det['codigoAdicional']);
                    }
                    if (isset($det['descripcion'])) {
                        $b->child($detItem, 'descripcion', (string) $det['descripcion']);
                    }
                    if (isset($det['cantidad'])) {
                        $b->child($detItem, 'cantidad', (string) $det['cantidad']);
                    }

                    // Detalles adicionales del item
                    if (isset($det['detallesAdicionales']) && is_array($det['detallesAdicionales'])) {
                        $detAddNode = $b->child($detItem, 'detallesAdicionales');
                        foreach ($det['detallesAdicionales'] as $detAdd) {
                            $detAddEl = $b->child($detAddNode, 'detAdicional');
                            $detAddEl->setAttribute('nombre', (string) $detAdd['nombre']);
                            $detAddEl->setAttribute('valor', (string) $detAdd['valor']);
                        }
                    }
                }
            }
        }
    }
}
