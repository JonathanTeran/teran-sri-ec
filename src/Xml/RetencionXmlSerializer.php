<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\Documents\DocSustento;
use Teran\Sri\Documents\Retencion;
use DOMDocument;
use DOMElement;

final class RetencionXmlSerializer
{
    private const VERSION = '2.0.0';
    private const COD_DOC = '07';

    public function serialize(Retencion $doc, string $claveAcceso): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('comprobanteRetencion');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $doc, $claveAcceso);
        $this->infoCompRetencion($b, $root, $doc);
        $this->docsSustento($b, $root, $doc);

        return $dom->saveXML();
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, Retencion $doc, string $claveAcceso): void
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

    private function infoCompRetencion(DomBuilder $b, DOMElement $root, Retencion $doc): void
    {
        $node = $b->child($root, 'infoCompRetencion');

        // Mirror the 1.x generator's simpleFields list with isset logic:
        // 'fechaEmision', 'dirEstablecimiento', 'contribuyenteEspecial',
        // 'obligadoContabilidad', 'tipoIdentificacionSujetoRetenido',
        // 'tipoSujetoRetenido', 'parteRel', 'razonSocialSujetoRetenido',
        // 'identificacionSujetoRetenido', 'periodoFiscal'
        $b->child($node, 'fechaEmision', $doc->fechaEmision);
        $b->child($node, 'dirEstablecimiento', $doc->dirEstablecimiento);
        if ($doc->contribuyenteEspecial !== null) {
            $b->child($node, 'contribuyenteEspecial', $doc->contribuyenteEspecial);
        }
        if ($doc->obligadoContabilidad !== null) {
            $b->child($node, 'obligadoContabilidad', $doc->obligadoContabilidad);
        }
        $b->child($node, 'tipoIdentificacionSujetoRetenido', $doc->tipoIdentificacionSujetoRetenido);
        if ($doc->tipoSujetoRetenido !== null) {
            $b->child($node, 'tipoSujetoRetenido', $doc->tipoSujetoRetenido);
        }
        if ($doc->parteRel !== null) {
            $b->child($node, 'parteRel', $doc->parteRel);
        }
        $b->child($node, 'razonSocialSujetoRetenido', $doc->razonSocialSujetoRetenido);
        $b->child($node, 'identificacionSujetoRetenido', $doc->identificacionSujetoRetenido);
        $b->child($node, 'periodoFiscal', $doc->periodoFiscal);
    }

    private function docsSustento(DomBuilder $b, DOMElement $root, Retencion $doc): void
    {
        $docsNode = $b->child($root, 'docsSustento');

        foreach ($doc->docsSustento as $docS) {
            $docItem = $b->child($docsNode, 'docSustento');

            // Mirror the 1.x generator's simpleFields list with isset logic:
            // 'codSustento', 'codDocSustento', 'numDocSustento',
            // 'fechaEmisionDocSustento', 'fechaRegistroContable',
            // 'numAutDocSustento', 'pagoLocExt', 'tipoRegi',
            // 'paisEfecPago', 'aplicConvDobTwordsri', 'pagExtSujRetNorLeg',
            // 'pagoRegFis', 'totalComprobantesReembolso',
            // 'totalBaseImponibleReembolso', 'totalImpuestoReembolso',
            // 'totalSinImpuestos', 'importeTotal'
            $b->child($docItem, 'codSustento', $docS->codSustento);
            $b->child($docItem, 'codDocSustento', $docS->codDocSustento);
            $b->child($docItem, 'numDocSustento', $docS->numDocSustento);
            $b->child($docItem, 'fechaEmisionDocSustento', $docS->fechaEmisionDocSustento);
            if ($docS->fechaRegistroContable !== null) {
                $b->child($docItem, 'fechaRegistroContable', $docS->fechaRegistroContable);
            }
            if ($docS->numAutDocSustento !== null) {
                $b->child($docItem, 'numAutDocSustento', $docS->numAutDocSustento);
            }
            if ($docS->pagoLocExt !== null) {
                $b->child($docItem, 'pagoLocExt', $docS->pagoLocExt);
            }
            if ($docS->tipoRegi !== null) {
                $b->child($docItem, 'tipoRegi', $docS->tipoRegi);
            }
            if ($docS->paisEfecPago !== null) {
                $b->child($docItem, 'paisEfecPago', $docS->paisEfecPago);
            }
            if ($docS->aplicConvDobTwordsri !== null) {
                $b->child($docItem, 'aplicConvDobTwordsri', $docS->aplicConvDobTwordsri);
            }
            if ($docS->pagExtSujRetNorLeg !== null) {
                $b->child($docItem, 'pagExtSujRetNorLeg', $docS->pagExtSujRetNorLeg);
            }
            if ($docS->pagoRegFis !== null) {
                $b->child($docItem, 'pagoRegFis', $docS->pagoRegFis);
            }
            if ($docS->totalComprobantesReembolso !== null) {
                $b->child($docItem, 'totalComprobantesReembolso', $docS->totalComprobantesReembolso);
            }
            if ($docS->totalBaseImponibleReembolso !== null) {
                $b->child($docItem, 'totalBaseImponibleReembolso', $docS->totalBaseImponibleReembolso);
            }
            if ($docS->totalImpuestoReembolso !== null) {
                $b->child($docItem, 'totalImpuestoReembolso', $docS->totalImpuestoReembolso);
            }
            $b->child($docItem, 'totalSinImpuestos', $docS->totalSinImpuestos);
            $b->child($docItem, 'importeTotal', $docS->importeTotal);

            // impuestosDocSustento — mirror 1.x: foreach key=>value on each row
            if (!empty($docS->impuestosDocSustento)) {
                $impNode = $b->child($docItem, 'impuestosDocSustento');
                foreach ($docS->impuestosDocSustento as $imp) {
                    $impItem = $b->child($impNode, 'impuestoDocSustento');
                    // Pass-through de claves crudas para paridad con el generador 1.x; las claves deben ser nombres XML válidos (NCName).
                    // Coerce '' to null so DomBuilder produces <el></el> like 1.x, instead of throwing.
                    foreach ($imp as $k => $v) {
                        $b->child($impItem, (string) $k, $v !== '' ? (string) $v : null);
                    }
                }
            }

            // retenciones — mirror 1.x: foreach key=>value on each row
            if (!empty($docS->retenciones)) {
                $retNode = $b->child($docItem, 'retenciones');
                foreach ($docS->retenciones as $ret) {
                    $retItem = $b->child($retNode, 'retencion');
                    // Pass-through de claves crudas para paridad con el generador 1.x; las claves deben ser nombres XML válidos (NCName).
                    // Coerce '' to null so DomBuilder produces <el></el> like 1.x, instead of throwing.
                    foreach ($ret as $k => $v) {
                        $b->child($retItem, (string) $k, $v !== '' ? (string) $v : null);
                    }
                }
            }

            // pagos — mirror 1.x: foreach key=>value on each row
            if (!empty($docS->pagos)) {
                $pagosNode = $b->child($docItem, 'pagos');
                foreach ($docS->pagos as $pago) {
                    $pagoItem = $b->child($pagosNode, 'pago');
                    // Pass-through de claves crudas para paridad con el generador 1.x; las claves deben ser nombres XML válidos (NCName).
                    // Coerce '' to null so DomBuilder produces <el></el> like 1.x, instead of throwing.
                    foreach ($pago as $k => $v) {
                        $b->child($pagoItem, (string) $k, $v !== '' ? (string) $v : null);
                    }
                }
            }
        }
    }
}
