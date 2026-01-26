<?php

declare(strict_types=1);

namespace Teran\Sri\Schema;

use Teran\Sri\Exceptions\ValidationException;

class BusinessValidator
{
    /**
     * Valida un RUC ecuatoriano de 13 dígitos.
     */
    public static function validarRuc(string $ruc): bool
    {
        if (strlen($ruc) !== 13 || !ctype_digit($ruc)) {
            return false;
        }

        $tercerDigito = (int)$ruc[2];
        if ($tercerDigito < 0 || ($tercerDigito > 6 && $tercerDigito !== 9)) {
            return false;
        }

        // Validación básica de establecimiento
        if (substr($ruc, 10, 3) === '000') {
            return false;
        }

        return true;
    }

    /**
     * Valida longitudes de campos comunes según ficha técnica 2026.
     */
    public static function validarCampos(array $datos): void
    {
        $reglas = [
            'razonSocial' => 300,
            'nombreComercial' => 300,
            'dirMatriz' => 300,
            'secuencial' => 9,
        ];

        $errores = [];
        foreach ($reglas as $campo => $longitud) {
            if (isset($datos[$campo]) && mb_strlen((string)$datos[$campo]) > $longitud) {
                $errores[] = "El campo $campo excede la longitud máxima de $longitud.";
            }
        }

        if (!empty($errores)) {
            throw new ValidationException("Error en validación de campos locales.", $errores);
        }
    }
}
