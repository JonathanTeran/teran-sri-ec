<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Teran\Sri\SRI;
use Teran\Sri\Strategies\ComprobanteInterface;

/**
 * Ejemplo de implementación de una Factura básica
 */
class FacturaSencilla implements ComprobanteInterface
{
    public function getTipo(): string { return '01'; }

    public function getXsdPath(): string { 
        return __DIR__ . '/resources/xsd/factura_v2.1.0.xsd'; 
    }

    public function getDatosClave(): array {
        return [
            'fecha' => '26012026',
            'ruc' => '1790011001001',
            'serie' => '001001',
            'numero' => '000000001',
            'codigoNum' => '12345678'
        ];
    }

    public function generarXml(): string {
        // Aquí iría la generación real del XML basada en la ficha técnica
        return '<?xml version="1.0" encoding="UTF-8"?>
        <factura id="comprobante" version="2.1.0">
            <infoTributaria>
                <ambiente>1</ambiente>
                <tipoEmision>1</tipoEmision>
                <razonSocial>RAZON SOCIAL</razonSocial>
                <nombreComercial>NOMBRE COMERCIAL</nombreComercial>
                <ruc>1790011001001</ruc>
                <claveAcceso>AUTO_GENERATED</claveAcceso>
                <codDoc>01</codDoc>
                <estab>001</estab>
                <ptoEmi>001</ptoEmi>
                <secuencial>000000001</secuencial>
                <dirMatriz>DIRECCION MATRIZ</dirMatriz>
            </infoTributaria>
            <infoFactura>
                <fechaEmision>26/01/2026</fechaEmision>
                <dirEstablecimiento>DIRECCION ESTAB</dirEstablecimiento>
                <obligadoContabilidad>NO</obligadoContabilidad>
                <tipoIdentificacionComprador>05</tipoIdentificacionComprador>
                <razonSocialComprador>CONSUMIDOR FINAL</razonSocialComprador>
                <identificacionComprador>9999999999999</identificacionComprador>
                <totalSinImpuestos>1.00</totalSinImpuestos>
                <totalDescuento>0.00</totalDescuento>
                <totalConImpuestos>
                    <totalImpuesto>
                        <codigo>2</codigo>
                        <codigoPorcentaje>0</codigoPorcentaje>
                        <baseImponible>1.00</baseImponible>
                        <valor>0.00</valor>
                    </totalImpuesto>
                </totalConImpuestos>
                <propina>0.00</propina>
                <importetotal>1.00</importetotal>
                <moneda>DOLAR</moneda>
            </infoFactura>
            <detalles>
                <detalle>
                    <codigoPrincipal>PROD01</codigoPrincipal>
                    <descripcion>PRODUCTO PRUEBA</descripcion>
                    <cantidad>1.00</cantidad>
                    <precioUnitario>1.00</precioUnitario>
                    <descuento>0.00</descuento>
                    <precioTotalSinImpuesto>1.00</precioTotalSinImpuesto>
                    <impuestos>
                        <impuesto>
                            <codigo>2</codigo>
                            <codigoPorcentaje>0</codigoPorcentaje>
                            <tarifa>0.00</tarifa>
                            <baseImponible>1.00</baseImponible>
                            <valor>0.00</valor>
                        </impuesto>
                    </impuestos>
                </detalle>
            </detalles>
        </factura>';
    }
}

// Ejemplo de uso:
/*
$sri = new SRI('pruebas');
$p12 = file_get_contents('ruta/a/firma.p12');
$password = 'tu_clave';

try {
    $factura = new FacturaSencilla();
    $resultado = $sri->setFirma($p12, $password)->procesar($factura);
    print_r($resultado);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
*/
