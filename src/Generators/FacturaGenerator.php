<?php

declare(strict_types=1);

namespace Teran\Sri\Generators;

use DOMElement;

class FacturaGenerator extends XmlGenerator
{
    public function generate(array $data): string
    {
        $root = $this->dom->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '2.1.0');
        $this->dom->appendChild($root);

        // 1. Info Tributaria
        $this->createInfoTributaria($root, $data['infoTributaria']);

        // 2. Info Factura
        $this->createInfoFactura($root, $data['infoFactura']);

        // 3. Detalles
        $this->createDetalles($root, $data['detalles']);

        // 4. Info Adicional
        $this->addInfoAdicional($root, $data['infoAdicional'] ?? []);

        return $this->dom->saveXML();
    }

    private function createInfoFactura(DOMElement $root, array $data): void
    {
        $node = $this->dom->createElement('infoFactura');
        $root->appendChild($node);

        $simpleFields = [
            'fechaEmision', 'dirEstablecimiento', 'contribuyenteEspecial', 
            'obligadoContabilidad', 'comercioExterior', 'codigoPrincipal',
            'tipoIdentificacionComprador', 'guiaRemision', 'razonSocialComprador',
            'identificacionComprador', 'direccionComprador', 'totalSinImpuestos',
            'totalDescuento'
        ];

        foreach ($simpleFields as $field) {
            if (isset($data[$field])) {
                $node->appendChild($this->dom->createElement($field, (string)$data[$field]));
            }
        }

        // Total con Impuestos
        if (isset($data['totalConImpuestos'])) {
            $totalImpNode = $this->dom->createElement('totalConImpuestos');
            $node->appendChild($totalImpNode);
            foreach ($data['totalConImpuestos'] as $imp) {
                $item = $this->dom->createElement('totalImpuesto');
                $totalImpNode->appendChild($item);
                foreach ($imp as $k => $v) {
                    $item->appendChild($this->dom->createElement($k, (string)$v));
                }
            }
        }

        $node->appendChild($this->dom->createElement('propina', (string)($data['propina'] ?? '0.00')));
        $node->appendChild($this->dom->createElement('importetotal', (string)$data['importetotal']));
        $node->appendChild($this->dom->createElement('moneda', $data['moneda'] ?? 'DOLAR'));

        // Pagos
        if (isset($data['pagos'])) {
            $pagosNode = $this->dom->createElement('pagos');
            $node->appendChild($pagosNode);
            foreach ($data['pagos'] as $pago) {
                $item = $this->dom->createElement('pago');
                $pagosNode->appendChild($item);
                foreach ($pago as $k => $v) {
                    $item->appendChild($this->dom->createElement($k, (string)$v));
                }
            }
        }
    }

    private function createDetalles(DOMElement $root, array $detalles): void
    {
        $node = $this->dom->createElement('detalles');
        $root->appendChild($node);

        foreach ($detalles as $det) {
            $item = $this->dom->createElement('detalle');
            $node->appendChild($item);

            $simpleFields = [
                'codigoPrincipal', 'codigoAuxiliar', 'descripcion', 
                'unidadMedida', 'cantidad', 'precioUnitario', 'descuento', 'precioTotalSinImpuesto'
            ];

            foreach ($simpleFields as $f) {
                if (isset($det[$f])) {
                    $item->appendChild($this->dom->createElement($f, (string)$det[$f]));
                }
            }

            // Impuestos del detalle
            if (isset($det['impuestos'])) {
                $impNode = $this->dom->createElement('impuestos');
                $item->appendChild($impNode);
                foreach ($det['impuestos'] as $imp) {
                    $impItem = $this->dom->createElement('impuesto');
                    $impNode->appendChild($impItem);
                    foreach ($imp as $k => $v) {
                        $impItem->appendChild($this->dom->createElement($k, (string)$v));
                    }
                }
            }
        }
    }
}
