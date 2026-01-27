<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Catálogo de Tipos de Identificación SRI Ecuador
 *
 * Referencia: Ficha Técnica Comprobantes Electrónicos SRI
 * Actualizado: 2026
 */
class IdentificationTypes
{
    /** RUC - Registro Único de Contribuyentes */
    public const RUC = '04';

    /** Cédula de Identidad */
    public const CEDULA = '05';

    /** Pasaporte */
    public const PASAPORTE = '06';

    /** Consumidor Final */
    public const CONSUMIDOR_FINAL = '07';

    /** Identificación del Exterior */
    public const EXTERIOR = '08';

    /**
     * Catálogo completo de tipos de identificación
     */
    private const CATALOG = [
        '04' => [
            'name' => 'RUC',
            'length' => 13,
            'validation' => 'ruc',
        ],
        '05' => [
            'name' => 'Cédula de Identidad',
            'length' => 10,
            'validation' => 'cedula',
        ],
        '06' => [
            'name' => 'Pasaporte',
            'length' => 0, // Variable
            'validation' => 'none',
        ],
        '07' => [
            'name' => 'Consumidor Final',
            'length' => 13,
            'validation' => 'none',
        ],
        '08' => [
            'name' => 'Identificación del Exterior',
            'length' => 0, // Variable
            'validation' => 'none',
        ],
    ];

    /** RUC del Consumidor Final */
    public const CONSUMIDOR_FINAL_RUC = '9999999999999';

    /** Límite para ventas a Consumidor Final sin identificación */
    public const CONSUMIDOR_FINAL_LIMIT = 50.00;

    /**
     * Obtiene el nombre del tipo de identificación por su código
     */
    public static function getName(string $code): ?string
    {
        return self::CATALOG[$code]['name'] ?? null;
    }

    /**
     * Obtiene la longitud esperada de un tipo de identificación
     * 0 significa longitud variable
     */
    public static function getLength(string $code): ?int
    {
        return self::CATALOG[$code]['length'] ?? null;
    }

    /**
     * Obtiene el tipo de validación requerida
     */
    public static function getValidationType(string $code): ?string
    {
        return self::CATALOG[$code]['validation'] ?? null;
    }

    /**
     * Verifica si un código de tipo de identificación es válido
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CATALOG[$code]);
    }

    /**
     * Verifica si requiere validación específica (cedula o ruc)
     */
    public static function requiresValidation(string $code): bool
    {
        $validation = self::getValidationType($code);
        return $validation !== null && $validation !== 'none';
    }

    /**
     * Obtiene todo el catálogo
     *
     * @return array<string, array{name: string, length: int, validation: string}>
     */
    public static function getAll(): array
    {
        return self::CATALOG;
    }

    /**
     * Obtiene solo los códigos válidos
     *
     * @return array<string>
     */
    public static function getCodes(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * Detecta el tipo de identificación basado en el formato
     */
    public static function detectType(string $identification): ?string
    {
        $len = strlen($identification);

        // Consumidor Final
        if ($identification === self::CONSUMIDOR_FINAL_RUC) {
            return self::CONSUMIDOR_FINAL;
        }

        // RUC (13 dígitos, termina en 001)
        if ($len === 13 && preg_match('/^\d{10}001$/', $identification)) {
            return self::RUC;
        }

        // Cédula (10 dígitos numéricos)
        if ($len === 10 && ctype_digit($identification)) {
            return self::CEDULA;
        }

        // Si tiene letras, probablemente es pasaporte o exterior
        if (preg_match('/[A-Za-z]/', $identification)) {
            return self::PASAPORTE;
        }

        return null;
    }
}
