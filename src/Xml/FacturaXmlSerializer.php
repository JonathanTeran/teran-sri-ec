<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\Documents\Factura;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Money\Money;
use DOMDocument;
use DOMElement;

final class FacturaXmlSerializer
{
    private const VERSION = '2.1.0';
    private const COD_DOC = '01';
    private const SCALE_MONEY = 2;
    private const SCALE_QUANTITY = 6;

    public function serialize(Factura $factura, string $claveAcceso): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $factura, $claveAcceso);
        $this->infoFactura($b, $root, $factura);
        $this->detalles($b, $root, $factura);

        return $dom->saveXML();
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, Factura $f, string $claveAcceso): void
    {
        $info = $f->infoTributaria;
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

    private function infoFactura(DomBuilder $b, DOMElement $root, Factura $f): void
    {
        $node = $b->child($root, 'infoFactura');
        $b->child($node, 'fechaEmision', $f->fechaEmision);
        $b->child($node, 'obligadoContabilidad', $f->obligadoContabilidad);
        $b->child($node, 'tipoIdentificacionComprador', $f->tipoIdentificacionComprador);
        $b->child($node, 'razonSocialComprador', $f->razonSocialComprador);
        $b->child($node, 'identificacionComprador', $f->identificacionComprador);
        $b->child($node, 'totalSinImpuestos', $f->totalSinImpuestos->format(self::SCALE_MONEY));
        $b->child($node, 'totalDescuento', $f->totalDescuento->format(self::SCALE_MONEY));

        $tci = $b->child($node, 'totalConImpuestos');
        foreach ($f->totalConImpuestos as $imp) {
            /** @var Impuesto $imp */
            $ti = $b->child($tci, 'totalImpuesto');
            $b->child($ti, 'codigo', $imp->codigo);
            $b->child($ti, 'codigoPorcentaje', $imp->codigoPorcentaje);
            $b->child($ti, 'baseImponible', $imp->baseImponible->format(self::SCALE_MONEY));
            $b->child($ti, 'valor', $imp->valor->format(self::SCALE_MONEY));
        }

        $b->child($node, 'propina', '0.00');
        $b->child($node, 'importeTotal', $f->importeTotal->format(self::SCALE_MONEY));
        $b->child($node, 'moneda', 'DOLAR');

        $pagos = $b->child($node, 'pagos');
        foreach ($f->pagos as $pago) {
            /** @var Pago $pago */
            $p = $b->child($pagos, 'pago');
            $b->child($p, 'formaPago', $pago->formaPago->value);
            $b->child($p, 'total', $pago->total->format(self::SCALE_MONEY));
        }
    }

    private function detalles(DomBuilder $b, DOMElement $root, Factura $f): void
    {
        $node = $b->child($root, 'detalles');
        foreach ($f->detalles as $det) {
            /** @var Detalle $det */
            $d = $b->child($node, 'detalle');
            $b->child($d, 'codigoPrincipal', $det->codigoPrincipal);
            if ($det->codigoAuxiliar !== null) {
                $b->child($d, 'codigoAuxiliar', $det->codigoAuxiliar);
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
                $tarifaStr = ($imp->tarifa === null || $imp->tarifa === '') ? '0' : $imp->tarifa;
                $b->child($i, 'tarifa', Money::of($tarifaStr)->format(self::SCALE_MONEY));
                $b->child($i, 'baseImponible', $imp->baseImponible->format(self::SCALE_MONEY));
                $b->child($i, 'valor', $imp->valor->format(self::SCALE_MONEY));
            }
        }
    }
}
