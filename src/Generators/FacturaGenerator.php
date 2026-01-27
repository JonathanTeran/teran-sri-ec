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
        $root->setAttribute('version', '1.1.0');
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
            'obligadoContabilidad', 'comercioExterior',
            'tipoIdentificacionComprador', 'guiaRemision', 'razonSocialComprador',
            'identificacionComprador', 'direccionComprador', 'totalSinImpuestos',
            'totalDescuento'
        ];

        foreach ($simpleFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                // Apply 2 decimals for monetary fields
                if (in_array($field, ['totalSinImpuestos', 'totalDescuento'])) {
                    $value = $this->formatValue($value, 2);
                }
                $node->appendChild($this->dom->createElement($field, (string)$value));
            }
        }

        // Total con Impuestos
        if (isset($data['totalConImpuestos'])) {
            $totalImpNode = $this->dom->createElement('totalConImpuestos');
            $node->appendChild($totalImpNode);
            foreach ($data['totalConImpuestos'] as $imp) {
                $item = $this->dom->createElement('totalImpuesto');
                $totalImpNode->appendChild($item);
                // Enforce strict XSD order: codigo, codigoPorcentaje, descuentoAdicional, baseImponible, tarifa, valor, valorDevolucionIva
                $fieldsOrder = [
                    'codigo', 'codigoPorcentaje', 'descuentoAdicional', 
                    'baseImponible', 'tarifa', 'valor', 'valorDevolucionIva'
                ];

                foreach ($fieldsOrder as $k) {
                    // Force descuentoAdicional to 0.00 if missing, to match reference XML structure
                    $v = $imp[$k] ?? null;
                    if ($k === 'descuentoAdicional' && $v === null) {
                        $v = '0.00'; // Default
                    }

                    if ($v !== null) {
                        if (in_array($k, ['baseImponible', 'valor', 'descuentoAdicional', 'tarifa', 'valorDevolucionIva'])) {
                            $v = $this->formatValue($v, 2);
                        }
                        $item->appendChild($this->dom->createElement($k, (string)$v));
                    }
                }
            }
        }

        $node->appendChild($this->dom->createElement('propina', $this->formatValue($data['propina'] ?? 0, 2)));
        $node->appendChild($this->dom->createElement('importeTotal', $this->formatValue($data['importetotal'], 2)));
        $node->appendChild($this->dom->createElement('moneda', $data['moneda'] ?? 'DOLAR'));

        // Pagos
        if (isset($data['pagos'])) {
            $pagosNode = $this->dom->createElement('pagos');
            $node->appendChild($pagosNode);
            foreach ($data['pagos'] as $pago) {
                $item = $this->dom->createElement('pago');
                $pagosNode->appendChild($item);
                // Enforce strict XSD order: formaPago, total, plazo, unidadTiempo
                $fieldsOrder = ['formaPago', 'total', 'plazo', 'unidadTiempo'];
                foreach ($fieldsOrder as $k) {
                    $val = $pago[$k] ?? null;
                    
                    // Match reference: if unit of time is missing but payment is not cash (01)
                    if ($k === 'unidadTiempo' && $val === null && ($pago['formaPago'] ?? '') !== '01') {
                        $val = 'dias';
                    }

                    if ($val !== null) {
                        if ($k === 'total') {
                             $val = $this->formatValue($val, 2);
                        }
                        $item->appendChild($this->dom->createElement($k, (string)$val));
                    }
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
                    $val = $det[$f];
                    // Quantities and Unit Prices use 6 decimals
                    if (in_array($f, ['cantidad', 'precioUnitario'])) {
                        $val = $this->formatValue($val, 6);
                    } elseif (in_array($f, ['descuento', 'precioTotalSinImpuesto'])) {
                        $val = $this->formatValue($val, 2);
                    }
                    $item->appendChild($this->dom->createElement($f, (string)$val));
                }
            }

            // Detalles Adicionales
            if (isset($det['detallesAdicionales'])) {
                $daNode = $this->dom->createElement('detallesAdicionales');
                $item->appendChild($daNode);
                foreach ($det['detallesAdicionales'] as $da) {
                    $daItem = $this->dom->createElement('detAdicional');
                    $daItem->setAttribute('nombre', $da['nombre']);
                    $daItem->setAttribute('valor', $da['valor']);
                    $daNode->appendChild($daItem);
                }
            }

            // Impuestos del detalle
            if (isset($det['impuestos'])) {
                $impNode = $this->dom->createElement('impuestos');
                $item->appendChild($impNode);
                foreach ($det['impuestos'] as $imp) {
                    $impItem = $this->dom->createElement('impuesto');
                    $impNode->appendChild($impItem);
                    // Enforce strict XSD order: codigo, codigoPorcentaje, tarifa, baseImponible, valor
                    $fieldsOrder = ['codigo', 'codigoPorcentaje', 'tarifa', 'baseImponible', 'valor'];
                    foreach ($fieldsOrder as $k) {
                        if (isset($imp[$k])) {
                            $v = $imp[$k];
                            // Tax numerical values
                            if (in_array($k, ['tarifa', 'baseImponible', 'valor'])) {
                                $v = $this->formatValue($v, 2);
                            }
                            $impItem->appendChild($this->dom->createElement($k, (string)$v));
                        }
                    }
                }
            }
        }
    }
}
