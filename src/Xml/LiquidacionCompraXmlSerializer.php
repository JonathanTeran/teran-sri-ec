<?php

declare(strict_types=1);

namespace Teran\Sri\Xml;

use Teran\Sri\InfoAdicional;
use Teran\Sri\Documents\LiquidacionCompra;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Money\Money;
use DOMDocument;
use DOMElement;

final class LiquidacionCompraXmlSerializer
{
    private const VERSION = '1.1.0';
    private const COD_DOC = '03';
    private const SCALE_MONEY = 2;
    private const SCALE_QUANTITY = 6;

    public function serialize(LiquidacionCompra $doc, string $claveAcceso, ?string $rucProveedor = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $b = new DomBuilder($dom);

        $root = $dom->createElement('liquidacionCompra');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', self::VERSION);
        $dom->appendChild($root);

        $this->infoTributaria($b, $root, $doc, $claveAcceso);
        $this->infoLiquidacionCompra($b, $root, $doc);
        $this->detalles($b, $root, $doc);

        // infoAdicional al final (orden del XSD). Con $rucProveedor agrega el campo
        // «RUC Proveedor» de la Resolución NAC-DGERCGC26-00000027.
        InfoAdicional::escribir($dom, $root, InfoAdicional::normalizar($doc->infoAdicional, $rucProveedor));

        $xml = $dom->saveXML();
        return $xml !== false ? $xml : '';
    }

    private function infoTributaria(DomBuilder $b, DOMElement $root, LiquidacionCompra $doc, string $claveAcceso): void
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

    private function infoLiquidacionCompra(DomBuilder $b, DOMElement $root, LiquidacionCompra $doc): void
    {
        $node = $b->child($root, 'infoLiquidacionCompra');
        $b->child($node, 'fechaEmision', $doc->fechaEmision);
        if ($doc->dirEstablecimiento !== null) {
            $b->child($node, 'dirEstablecimiento', $doc->dirEstablecimiento);
        }
        if ($doc->contribuyenteEspecial !== null) {
            $b->child($node, 'contribuyenteEspecial', $doc->contribuyenteEspecial);
        }
        $b->child($node, 'obligadoContabilidad', $doc->obligadoContabilidad);
        $b->child($node, 'tipoIdentificacionProveedor', $doc->tipoIdentificacionProveedor);
        $b->child($node, 'razonSocialProveedor', $doc->razonSocialProveedor);
        $b->child($node, 'identificacionProveedor', $doc->identificacionProveedor);
        if ($doc->direccionProveedor !== null) {
            $b->child($node, 'direccionProveedor', $doc->direccionProveedor);
        }
        $b->child($node, 'totalSinImpuestos', $doc->totalSinImpuestos->format(self::SCALE_MONEY));
        $b->child($node, 'totalDescuento', $doc->totalDescuento->format(self::SCALE_MONEY));

        $tci = $b->child($node, 'totalConImpuestos');
        foreach ($doc->totalConImpuestos as $imp) {
            /** @var Impuesto $imp */
            $ti = $b->child($tci, 'totalImpuesto');
            $b->child($ti, 'codigo', $imp->codigo);
            $b->child($ti, 'codigoPorcentaje', $imp->codigoPorcentaje);
            $b->child($ti, 'baseImponible', $imp->baseImponible->format(self::SCALE_MONEY));
            $b->child($ti, 'valor', $imp->valor->format(self::SCALE_MONEY));
        }

        $b->child($node, 'importeTotal', $doc->importeTotal->format(self::SCALE_MONEY));
        $b->child($node, 'moneda', $doc->moneda);

        $pagos = $b->child($node, 'pagos');
        foreach ($doc->pagos as $pago) {
            /** @var Pago $pago */
            $p = $b->child($pagos, 'pago');
            $b->child($p, 'formaPago', $pago->formaPago->value);
            $b->child($p, 'total', $pago->total->format(self::SCALE_MONEY));
            if ($pago->plazo !== null) {
                $b->child($p, 'plazo', (string) $pago->plazo);
                $b->child($p, 'unidadTiempo', $pago->unidadTiempo ?? 'dias');
            }
        }
    }

    private function detalles(DomBuilder $b, DOMElement $root, LiquidacionCompra $doc): void
    {
        $node = $b->child($root, 'detalles');
        foreach ($doc->detalles as $det) {
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
