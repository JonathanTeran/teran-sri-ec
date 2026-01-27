<?php

declare(strict_types=1);

namespace Teran\Sri\Generators;

use DOMElement;

class GuiaRemisionGenerator extends XmlGenerator
{
    public function generate(array $data): string
    {
        $root = $this->dom->createElement('guiaRemision');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $this->dom->appendChild($root);

        // 1. Info Tributaria
        $this->createInfoTributaria($root, $data['infoTributaria']);

        // 2. Info Guía Remisión
        $this->createInfoGuiaRemision($root, $data['infoGuiaRemision']);

        // 3. Destinatarios
        $this->createDestinatarios($root, $data['destinatarios']);

        // 4. Info Adicional
        $this->addInfoAdicional($root, $data['infoAdicional'] ?? []);

        return $this->dom->saveXML();
    }

    private function createInfoGuiaRemision(DOMElement $root, array $data): void
    {
        $node = $this->dom->createElement('infoGuiaRemision');
        $root->appendChild($node);

        $simpleFields = [
            'dirEstablecimiento', 'dirPartida', 'razonSocialTransportista',
            'tipoIdentificacionTransportista', 'rucTransportista', 'rise',
            'obligadoContabilidad', 'contribuyenteEspecial', 'fechaIniTransporte',
            'fechaFinTransporte', 'placa'
        ];

        foreach ($simpleFields as $field) {
            if (isset($data[$field])) {
                $node->appendChild($this->dom->createElement($field, (string)$data[$field]));
            }
        }
    }

    private function createDestinatarios(DOMElement $root, array $destinatarios): void
    {
        $node = $this->dom->createElement('destinatarios');
        $root->appendChild($node);

        foreach ($destinatarios as $dest) {
            $destItem = $this->dom->createElement('destinatario');
            $node->appendChild($destItem);

            $simpleFields = [
                'identificacionDestinatario', 'razonSocialDestinatario',
                'dirDestinatario', 'motivoTraslado', 'docAduaneroUnico',
                'codEstabDestino', 'ruta', 'codDocSustento', 'numDocSustento',
                'numAutDocSustento', 'fechaEmisionDocSustento'
            ];

            foreach ($simpleFields as $field) {
                if (isset($dest[$field])) {
                    $destItem->appendChild($this->dom->createElement($field, (string)$dest[$field]));
                }
            }

            // Detalles del destinatario
            if (isset($dest['detalles'])) {
                $detallesNode = $this->dom->createElement('detalles');
                $destItem->appendChild($detallesNode);

                foreach ($dest['detalles'] as $det) {
                    $detItem = $this->dom->createElement('detalle');
                    $detallesNode->appendChild($detItem);

                    $detFields = [
                        'codigoInterno', 'codigoAdicional', 'descripcion', 'cantidad'
                    ];

                    foreach ($detFields as $f) {
                        if (isset($det[$f])) {
                            $detItem->appendChild($this->dom->createElement($f, (string)$det[$f]));
                        }
                    }

                    // Detalles adicionales del item
                    if (isset($det['detallesAdicionales'])) {
                        $detAddNode = $this->dom->createElement('detallesAdicionales');
                        $detItem->appendChild($detAddNode);
                        foreach ($det['detallesAdicionales'] as $detAdd) {
                            $detAddItem = $this->dom->createElement('detAdicional');
                            $detAddItem->setAttribute('nombre', $detAdd['nombre']);
                            $detAddItem->setAttribute('valor', $detAdd['valor']);
                            $detAddNode->appendChild($detAddItem);
                        }
                    }
                }
            }
        }
    }
}
