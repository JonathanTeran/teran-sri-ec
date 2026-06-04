<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Documents\Motivo;
use Teran\Sri\Documents\NotaDebito;
use Teran\Sri\Documents\Pago;
use DOMDocument;
use DOMElement;

final class NotaDebitoXmlSerializer
{
    private const VERSION = '1.0.0';
    private const COD_DOC = '05';
    private const SCALE_MONEY = 2;

    public function serialize(NotaDebito $doc, string $claveAcceso): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('notaDebito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $doc, $claveAcceso);
        $this->infoNotaDebito($b, $root, $doc);
        $this->motivos($b, $root, $doc);

        return $dom->saveXML();
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, NotaDebito $doc, string $claveAcceso): void
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

    private function infoNotaDebito(DomBuilder $b, DOMElement $root, NotaDebito $doc): void
    {
        $node = $b->child($root, 'infoNotaDebito');

        // Mirror the 1.x generator's simpleFields list (same order, same optional logic)
        $b->child($node, 'fechaEmision', $doc->fechaEmision);
        if ($doc->dirEstablecimiento !== null) {
            $b->child($node, 'dirEstablecimiento', $doc->dirEstablecimiento);
        }
        $b->child($node, 'tipoIdentificacionComprador', $doc->tipoIdentificacionComprador);
        $b->child($node, 'razonSocialComprador', $doc->razonSocialComprador);
        $b->child($node, 'identificacionComprador', $doc->identificacionComprador);
        if ($doc->contribuyenteEspecial !== null) {
            $b->child($node, 'contribuyenteEspecial', $doc->contribuyenteEspecial);
        }
        $b->child($node, 'obligadoContabilidad', $doc->obligadoContabilidad);
        if ($doc->rise !== null) {
            $b->child($node, 'rise', $doc->rise);
        }
        $b->child($node, 'codDocModificado', $doc->codDocModificado);
        $b->child($node, 'numDocModificado', $doc->numDocModificado);
        $b->child($node, 'fechaEmisionDocSustento', $doc->fechaEmisionDocSustento);
        $b->child($node, 'totalSinImpuestos', $doc->totalSinImpuestos->format(self::SCALE_MONEY));

        // Impuestos
        if ($doc->impuestos !== []) {
            $impNode = $b->child($node, 'impuestos');
            foreach ($doc->impuestos as $imp) {
                /** @var Impuesto $imp */
                $i = $b->child($impNode, 'impuesto');
                $b->child($i, 'codigo', $imp->codigo);
                $b->child($i, 'codigoPorcentaje', $imp->codigoPorcentaje);
                $b->child($i, 'baseImponible', $imp->baseImponible->format(self::SCALE_MONEY));
                $b->child($i, 'valor', $imp->valor->format(self::SCALE_MONEY));
            }
        }

        $b->child($node, 'valorTotal', $doc->valorTotal->format(self::SCALE_MONEY));

        // Pagos
        if ($doc->pagos !== []) {
            $pagosNode = $b->child($node, 'pagos');
            foreach ($doc->pagos as $pago) {
                /** @var Pago $pago */
                $p = $b->child($pagosNode, 'pago');
                $b->child($p, 'formaPago', $pago->formaPago->value);
                $b->child($p, 'total', $pago->total->format(self::SCALE_MONEY));
            }
        }
    }

    private function motivos(DomBuilder $b, DOMElement $root, NotaDebito $doc): void
    {
        $node = $b->child($root, 'motivos');
        foreach ($doc->motivos as $motivo) {
            /** @var Motivo $motivo */
            $m = $b->child($node, 'motivo');
            $b->child($m, 'razon', $motivo->razon);
            $b->child($m, 'valor', $motivo->valor->format(self::SCALE_MONEY));
        }
    }
}
