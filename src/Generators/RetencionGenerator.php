<?php

declare(strict_types=1);

namespace Teran\Sri\Generators;

use DOMElement;

class RetencionGenerator extends XmlGenerator
{
    public function generate(array $data): string
    {
        $root = $this->dom->createElement('comprobanteRetencion');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '2.0.0');
        $this->dom->appendChild($root);

        // 1. Info Tributaria
        $this->createInfoTributaria($root, $data['infoTributaria']);

        // 2. Info Comprobante Retención
        $this->createInfoCompRetencion($root, $data['infoCompRetencion']);

        // 3. Documento Sustento (nuevo en v2.0.0)
        if (isset($data['docsSustento'])) {
            $this->createDocsSustento($root, $data['docsSustento']);
        } else {
            // Formato anterior con impuestos directos
            $this->createImpuestos($root, $data['impuestos'] ?? []);
        }

        // 4. Info Adicional
        $this->addInfoAdicional($root, $data['infoAdicional'] ?? []);

        return $this->dom->saveXML();
    }

    private function createInfoCompRetencion(DOMElement $root, array $data): void
    {
        $node = $this->dom->createElement('infoCompRetencion');
        $root->appendChild($node);

        $simpleFields = [
            'fechaEmision', 'dirEstablecimiento', 'contribuyenteEspecial',
            'obligadoContabilidad', 'tipoIdentificacionSujetoRetenido',
            'tipoSujetoRetenido', 'parteRel', 'razonSocialSujetoRetenido',
            'identificacionSujetoRetenido', 'periodoFiscal'
        ];

        foreach ($simpleFields as $field) {
            if (isset($data[$field])) {
                $node->appendChild($this->dom->createElement($field, (string)$data[$field]));
            }
        }
    }

    private function createDocsSustento(DOMElement $root, array $docsSustento): void
    {
        $node = $this->dom->createElement('docsSustento');
        $root->appendChild($node);

        foreach ($docsSustento as $doc) {
            $docItem = $this->dom->createElement('docSustento');
            $node->appendChild($docItem);

            $simpleFields = [
                'codSustento', 'codDocSustento', 'numDocSustento',
                'fechaEmisionDocSustento', 'fechaRegistroContable',
                'numAutDocSustento', 'pagoLocExt', 'tipoRegi',
                'paisEfecPago', 'aplicConvDobTwordsri', 'pagExtSujRetNorLeg',
                'pagoRegFis', 'totalComprobantesReembolso',
                'totalBaseImponibleReembolso', 'totalImpuestoReembolso',
                'totalSinImpuestos', 'importeTotal'
            ];

            foreach ($simpleFields as $field) {
                if (isset($doc[$field])) {
                    $docItem->appendChild($this->dom->createElement($field, (string)$doc[$field]));
                }
            }

            // Impuestos del documento sustento
            if (isset($doc['impuestosDocSustento'])) {
                $impNode = $this->dom->createElement('impuestosDocSustento');
                $docItem->appendChild($impNode);
                foreach ($doc['impuestosDocSustento'] as $imp) {
                    $impItem = $this->dom->createElement('impuestoDocSustento');
                    $impNode->appendChild($impItem);
                    foreach ($imp as $k => $v) {
                        $impItem->appendChild($this->dom->createElement($k, (string)$v));
                    }
                }
            }

            // Retenciones
            if (isset($doc['retenciones'])) {
                $retNode = $this->dom->createElement('retenciones');
                $docItem->appendChild($retNode);
                foreach ($doc['retenciones'] as $ret) {
                    $retItem = $this->dom->createElement('retencion');
                    $retNode->appendChild($retItem);
                    foreach ($ret as $k => $v) {
                        $retItem->appendChild($this->dom->createElement($k, (string)$v));
                    }
                }
            }

            // Pagos
            if (isset($doc['pagos'])) {
                $pagosNode = $this->dom->createElement('pagos');
                $docItem->appendChild($pagosNode);
                foreach ($doc['pagos'] as $pago) {
                    $pagoItem = $this->dom->createElement('pago');
                    $pagosNode->appendChild($pagoItem);
                    foreach ($pago as $k => $v) {
                        $pagoItem->appendChild($this->dom->createElement($k, (string)$v));
                    }
                }
            }
        }
    }

    private function createImpuestos(DOMElement $root, array $impuestos): void
    {
        if (empty($impuestos)) {
            return;
        }

        $node = $this->dom->createElement('impuestos');
        $root->appendChild($node);

        foreach ($impuestos as $imp) {
            $item = $this->dom->createElement('impuesto');
            $node->appendChild($item);

            $fields = [
                'codigo', 'codigoRetencion', 'baseImponible',
                'porcentajeRetener', 'valorRetenido', 'codDocSustento',
                'numDocSustento', 'fechaEmisionDocSustento'
            ];

            foreach ($fields as $f) {
                if (isset($imp[$f])) {
                    $item->appendChild($this->dom->createElement($f, (string)$imp[$f]));
                }
            }
        }
    }
}
