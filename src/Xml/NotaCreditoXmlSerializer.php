<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Documents\NotaCredito;
use DOMDocument;
use DOMElement;

final class NotaCreditoXmlSerializer
{
    private const VERSION = '1.1.0';
    private const COD_DOC = '04';
    private const SCALE_MONEY = 2;
    private const SCALE_QUANTITY = 6;

    public function serialize(NotaCredito $doc, string $claveAcceso): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('notaCredito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $doc, $claveAcceso);
        $this->infoNotaCredito($b, $root, $doc);
        $this->detalles($b, $root, $doc);

        return $dom->saveXML();
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, NotaCredito $doc, string $claveAcceso): void
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

    private function infoNotaCredito(DomBuilder $b, DOMElement $root, NotaCredito $doc): void
    {
        $node = $b->child($root, 'infoNotaCredito');

        // Mirror the 1.x generator's simpleFields list (only emit if non-empty, like 1.x's isset check)
        $b->child($node, 'fechaEmision', $doc->fechaEmision);
        $b->child($node, 'tipoIdentificacionComprador', $doc->tipoIdentificacionComprador);
        $b->child($node, 'razonSocialComprador', $doc->razonSocialComprador);
        $b->child($node, 'identificacionComprador', $doc->identificacionComprador);
        $b->child($node, 'obligadoContabilidad', $doc->obligadoContabilidad);
        $b->child($node, 'codDocModificado', $doc->codDocModificado);
        $b->child($node, 'numDocModificado', $doc->numDocModificado);
        $b->child($node, 'fechaEmisionDocSustento', $doc->fechaEmisionDocSustento);
        $b->child($node, 'totalSinImpuestos', $doc->totalSinImpuestos->format(self::SCALE_MONEY));
        $b->child($node, 'valorModificacion', $doc->valorModificacion->format(self::SCALE_MONEY));
        $b->child($node, 'moneda', $doc->moneda);

        // totalConImpuestos
        $tci = $b->child($node, 'totalConImpuestos');
        foreach ($doc->totalConImpuestos as $imp) {
            /** @var Impuesto $imp */
            $ti = $b->child($tci, 'totalImpuesto');
            $b->child($ti, 'codigo', $imp->codigo);
            $b->child($ti, 'codigoPorcentaje', $imp->codigoPorcentaje);
            $b->child($ti, 'baseImponible', $imp->baseImponible->format(self::SCALE_MONEY));
            $b->child($ti, 'valor', $imp->valor->format(self::SCALE_MONEY));
        }

        $b->child($node, 'motivo', $doc->motivo);
    }

    private function detalles(DomBuilder $b, DOMElement $root, NotaCredito $doc): void
    {
        $node = $b->child($root, 'detalles');
        foreach ($doc->detalles as $det) {
            /** @var Detalle $det */
            $d = $b->child($node, 'detalle');
            // 1.x NC generator emits 'codigoInterno' (not 'codigoPrincipal')
            $b->child($d, 'codigoInterno', $det->codigoPrincipal);
            if ($det->codigoAuxiliar !== null) {
                $b->child($d, 'codigoAdicional', $det->codigoAuxiliar);
            }
            $b->child($d, 'descripcion', $det->descripcion);
            $b->child($d, 'cantidad', $det->cantidad->format(self::SCALE_QUANTITY));
            $b->child($d, 'precioUnitario', $det->precioUnitario->format(self::SCALE_QUANTITY));
            $b->child($d, 'descuento', $det->descuento->format(self::SCALE_MONEY));
            $b->child($d, 'precioTotalSinImpuesto', $det->precioTotalSinImpuesto->format(self::SCALE_MONEY));

            $imps = $b->child($d, 'impuestos');
            foreach ($det->impuestos as $imp) {
                /** @var Impuesto $imp */
                $i = $b->child($imps, 'impuesto');
                $b->child($i, 'codigo', $imp->codigo);
                $b->child($i, 'codigoPorcentaje', $imp->codigoPorcentaje);
                $b->child($i, 'baseImponible', $imp->baseImponible->format(self::SCALE_MONEY));
                $b->child($i, 'valor', $imp->valor->format(self::SCALE_MONEY));
            }
        }
    }
}
