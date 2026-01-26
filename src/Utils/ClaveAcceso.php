<?php

declare(strict_types=1);

namespace Teran\Sri\Utils;

use InvalidArgumentException;

/**
 * Generador de Clave de Acceso SRI de 49 dígitos.
 * Implementa el algoritmo Módulo 11 según la ficha técnica del SRI.
 */
class ClaveAcceso
{
    /**
     * Genera la clave de acceso completa (48 dígitos + dígito verificador).
     * 
     * @param string $fecha Formato ddmmyyyy
     * @param string $tipoComprobante (01: Factura, 04: Nota de Crédito, etc.)
     * @param string $ruc
     * @param string $ambiente (1: Pruebas, 2: Producción)
     * @param string $serie (Establecimiento + Punto de Emisión, 6 dígitos)
     * @param string $numero (9 dígitos)
     * @param string $codigoNum (8 dígitos aleatorios o fijos)
     * @param string $tipoEmision (1: Normal)
     * @return string
     */
    public static function generar(
        string $fecha,
        string $tipoComprobante,
        string $ruc,
        string $ambiente,
        string $serie,
        string $numero,
        string $codigoNum,
        string $tipoEmision = '1'
    ): string {
        $fecha = str_replace('/', '', $fecha);
        $clave48 = $fecha . $tipoComprobante . $ruc . $ambiente . $serie . $numero . $codigoNum . $tipoEmision;

        if (strlen($clave48) !== 48) {
            throw new InvalidArgumentException("La base de la clave de acceso debe tener 48 dígitos. Actual: " . strlen($clave48));
        }

        $dv = self::calcularDigitoVerificador($clave48);
        
        return $clave48 . $dv;
    }

    /**
     * Calcula el dígito verificador usando el algoritmo Módulo 11.
     * 
     * @param string $cadena Los 48 dígitos iniciales.
     * @return int
     */
    public static function calcularDigitoVerificador(string $cadena): int
    {
        $invertida = strrev($cadena);
        $suma = 0;
        $factor = 2;

        for ($i = 0; $i < strlen($invertida); $i++) {
            $suma += (int)$invertida[$i] * $factor;
            $factor++;
            if ($factor > 7) {
                $factor = 2;
            }
        }

        $residuo = $suma % 11;
        $dv = 11 - $residuo;

        if ($dv === 11) {
            return 0;
        }

        if ($dv === 10) {
            return 1;
        }

        return $dv;
    }
}
