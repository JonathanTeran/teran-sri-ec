<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Catálogo de Sustentos Tributarios SRI Ecuador
 *
 * Utilizado en el ATS (Anexo Transaccional Simplificado)
 * Define el tratamiento fiscal de las compras
 *
 * Referencia: Catálogo ATS SRI
 * Actualizado: 2026
 */
class TaxSupport
{
    /** Crédito Tributario para declaración de IVA */
    public const CREDITO_TRIBUTARIO_IVA = '01';

    /** Costo o Gasto para declaración de IR */
    public const COSTO_GASTO_IR = '02';

    /** Activo Fijo - Crédito Tributario para declaración de IVA */
    public const ACTIVO_FIJO_CREDITO_IVA = '03';

    /** Activo Fijo - Costo o Gasto para declaración de IR */
    public const ACTIVO_FIJO_COSTO_IR = '04';

    /** Liquidación Gastos de Viaje, Hospedaje y Alimentación */
    public const GASTOS_VIAJE = '05';

    /** Inventario - Crédito Tributario para declaración de IVA */
    public const INVENTARIO_CREDITO_IVA = '06';

    /** Inventario - Costo o Gasto para declaración de IR */
    public const INVENTARIO_COSTO_IR = '07';

    /** Valor pagado para solicitar Reembolso de Gastos */
    public const REEMBOLSO_GASTOS = '08';

    /** Reembolso por Siniestros */
    public const REEMBOLSO_SINIESTROS = '09';

    /** Distribución de Dividendos, Beneficios o Utilidades */
    public const DIVIDENDOS = '10';

    /** Casos Especiales cuyo sustento no aplica */
    public const CASOS_ESPECIALES = '00';

    /**
     * Catálogo completo de sustentos tributarios
     */
    private const CATALOG = [
        '01' => [
            'name' => 'Crédito Tributario para declaración de IVA',
            'credito_iva' => true,
            'costo_gasto_ir' => false,
        ],
        '02' => [
            'name' => 'Costo o Gasto para declaración de IR',
            'credito_iva' => false,
            'costo_gasto_ir' => true,
        ],
        '03' => [
            'name' => 'Activo Fijo - Crédito Tributario para declaración de IVA',
            'credito_iva' => true,
            'costo_gasto_ir' => false,
        ],
        '04' => [
            'name' => 'Activo Fijo - Costo o Gasto para declaración de IR',
            'credito_iva' => false,
            'costo_gasto_ir' => true,
        ],
        '05' => [
            'name' => 'Liquidación Gastos de Viaje, Hospedaje y Alimentación',
            'credito_iva' => true,
            'costo_gasto_ir' => true,
        ],
        '06' => [
            'name' => 'Inventario - Crédito Tributario para declaración de IVA',
            'credito_iva' => true,
            'costo_gasto_ir' => false,
        ],
        '07' => [
            'name' => 'Inventario - Costo o Gasto para declaración de IR',
            'credito_iva' => false,
            'costo_gasto_ir' => true,
        ],
        '08' => [
            'name' => 'Valor pagado para solicitar Reembolso de Gastos',
            'credito_iva' => false,
            'costo_gasto_ir' => false,
        ],
        '09' => [
            'name' => 'Reembolso por Siniestros',
            'credito_iva' => false,
            'costo_gasto_ir' => false,
        ],
        '10' => [
            'name' => 'Distribución de Dividendos, Beneficios o Utilidades',
            'credito_iva' => false,
            'costo_gasto_ir' => false,
        ],
        '00' => [
            'name' => 'Casos Especiales cuyo sustento no aplica a las opciones anteriores',
            'credito_iva' => false,
            'costo_gasto_ir' => false,
        ],
    ];

    /**
     * Obtiene el nombre del sustento tributario por su código
     */
    public static function getName(string $code): ?string
    {
        return self::CATALOG[$code]['name'] ?? null;
    }

    /**
     * Verifica si un código de sustento tributario es válido
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CATALOG[$code]);
    }

    /**
     * Verifica si el sustento permite crédito tributario de IVA
     */
    public static function allowsCreditoIva(string $code): bool
    {
        return self::CATALOG[$code]['credito_iva'] ?? false;
    }

    /**
     * Verifica si el sustento permite costo/gasto para IR
     */
    public static function allowsCostoGastoIr(string $code): bool
    {
        return self::CATALOG[$code]['costo_gasto_ir'] ?? false;
    }

    /**
     * Obtiene el sustento por defecto para compras generales
     */
    public static function getDefaultForPurchase(): string
    {
        return self::CREDITO_TRIBUTARIO_IVA;
    }

    /**
     * Obtiene el sustento por defecto para activos fijos
     */
    public static function getDefaultForFixedAsset(): string
    {
        return self::ACTIVO_FIJO_CREDITO_IVA;
    }

    /**
     * Obtiene el sustento por defecto para inventario
     */
    public static function getDefaultForInventory(): string
    {
        return self::INVENTARIO_CREDITO_IVA;
    }

    /**
     * Obtiene todo el catálogo
     *
     * @return array<string, array{name: string, credito_iva: bool, costo_gasto_ir: bool}>
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
     * Obtiene todos los sustentos que permiten crédito tributario IVA
     *
     * @return array<string>
     */
    public static function getWithCreditoIva(): array
    {
        return array_keys(
            array_filter(self::CATALOG, fn($s) => $s['credito_iva'])
        );
    }

    /**
     * Obtiene todos los sustentos que permiten costo/gasto IR
     *
     * @return array<string>
     */
    public static function getWithCostoGastoIr(): array
    {
        return array_keys(
            array_filter(self::CATALOG, fn($s) => $s['costo_gasto_ir'])
        );
    }
}
