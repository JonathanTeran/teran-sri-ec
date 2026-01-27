<?php

declare(strict_types=1);

namespace Teran\Sri\Generators;

use DOMElement;

class NotaDebitoGenerator extends XmlGenerator
{
    public function generate(array $data): string
    {
        $root = $this->dom->createElement('notaDebito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.0.0');
        $this->dom->appendChild($root);

        // 1. Info Tributaria
        $this->createInfoTributaria($root, $data['infoTributaria']);

        // 2. Info Nota Débito
        $this->createInfoNotaDebito($root, $data['infoNotaDebito']);

        // 3. Motivos
        $this->createMotivos($root, $data['motivos']);

        // 4. Info Adicional
        $this->addInfoAdicional($root, $data['infoAdicional'] ?? []);

        return $this->dom->saveXML();
    }

    private function createInfoNotaDebito(DOMElement $root, array $data): void
    {
        $node = $this->dom->createElement('infoNotaDebito');
        $root->appendChild($node);

        $simpleFields = [
            'fechaEmision', 'dirEstablecimiento', 'tipoIdentificacionComprador',
            'razonSocialComprador', 'identificacionComprador', 'contribuyenteEspecial',
            'obligadoContabilidad', 'rise', 'codDocModificado', 'numDocModificado',
            'fechaEmisionDocSustento', 'totalSinImpuestos'
        ];

        foreach ($simpleFields as $field) {
            if (isset($data[$field])) {
                $node->appendChild($this->dom->createElement($field, (string)$data[$field]));
            }
        }

        // Impuestos
        if (isset($data['impuestos'])) {
            $impuestosNode = $this->dom->createElement('impuestos');
            $node->appendChild($impuestosNode);
            foreach ($data['impuestos'] as $imp) {
                $impItem = $this->dom->createElement('impuesto');
                $impuestosNode->appendChild($impItem);
                foreach ($imp as $k => $v) {
                    $impItem->appendChild($this->dom->createElement($k, (string)$v));
                }
            }
        }

        if (isset($data['valorTotal'])) {
            $node->appendChild($this->dom->createElement('valorTotal', (string)$data['valorTotal']));
        }

        // Pagos
        if (isset($data['pagos'])) {
            $pagosNode = $this->dom->createElement('pagos');
            $node->appendChild($pagosNode);
            foreach ($data['pagos'] as $pago) {
                $pagoItem = $this->dom->createElement('pago');
                $pagosNode->appendChild($pagoItem);
                foreach ($pago as $k => $v) {
                    $pagoItem->appendChild($this->dom->createElement($k, (string)$v));
                }
            }
        }
    }

    private function createMotivos(DOMElement $root, array $motivos): void
    {
        $node = $this->dom->createElement('motivos');
        $root->appendChild($node);

        foreach ($motivos as $motivo) {
            $item = $this->dom->createElement('motivo');
            $node->appendChild($item);

            if (isset($motivo['razon'])) {
                $item->appendChild($this->dom->createElement('razon', (string)$motivo['razon']));
            }
            if (isset($motivo['valor'])) {
                $item->appendChild($this->dom->createElement('valor', (string)$motivo['valor']));
            }
        }
    }
}
