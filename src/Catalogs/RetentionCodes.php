<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Catálogo de Códigos de Retención SRI Ecuador
 *
 * Tabla 19: Retenciones Impuesto a la Renta
 * Tabla 21: Retenciones IVA
 *
 * Referencia: Resolución NAC-DGERCGC25-00000017
 * Actualizado: 2026
 */
class RetentionCodes
{
    // ========================================
    // RETENCIONES IMPUESTO A LA RENTA (Tabla 19)
    // ========================================

    /** RIMPE Negocio Popular (0%) */
    public const RENTA_RIMPE_NEGOCIO_POPULAR = '332B';

    /** RIMPE Emprendedor (1%) */
    public const RENTA_RIMPE_EMPRENDEDOR = '343A';

    /** Transferencia Bienes Muebles (1.75%) */
    public const RENTA_BIENES_MUEBLES = '312';

    /** Otras Retenciones (2.75%) */
    public const RENTA_OTRAS = '3440';

    /** Honorarios Profesionales (10%) */
    public const RENTA_HONORARIOS_PROFESIONALES = '303';

    /** Servicios Predomina Intelecto (8%) */
    public const RENTA_SERVICIOS_INTELECTO = '304';

    // ========================================
    // RETENCIONES IVA (Tabla 21)
    // ========================================

    /** Retención IVA 30% (Bienes) */
    public const IVA_30_BIENES = '1';

    /** Retención IVA 70% (Servicios) */
    public const IVA_70_SERVICIOS = '2';

    /** Retención IVA 100% (Profesionales/Arriendo) */
    public const IVA_100_PROFESIONALES = '3';

    /** No Procede Retención IVA (0%) */
    public const IVA_NO_PROCEDE = '9';

    /**
     * Catálogo de Retenciones de Renta 2026
     */
    private const RENTA_CATALOG = [
        '332B' => [
            'name' => 'RIMPE Negocio Popular',
            'percentage' => 0.0,
            'description' => 'Retención a contribuyentes RIMPE Negocio Popular',
        ],
        '343A' => [
            'name' => 'RIMPE Emprendedor',
            'percentage' => 1.0,
            'description' => 'Retención a contribuyentes RIMPE Emprendedor',
        ],
        '312' => [
            'name' => 'Transferencia Bienes Muebles',
            'percentage' => 1.75,
            'description' => 'Compra de bienes muebles de naturaleza corporal',
        ],
        '3440' => [
            'name' => 'Otras Retenciones',
            'percentage' => 2.75,
            'description' => 'Otras retenciones aplicables a la transferencia de bienes o servicios no especificados',
        ],
        '303' => [
            'name' => 'Honorarios Profesionales',
            'percentage' => 10.0,
            'description' => 'Honorarios, comisiones y dietas a personas naturales',
        ],
        '304' => [
            'name' => 'Servicios Predomina Intelecto',
            'percentage' => 8.0,
            'description' => 'Servicios donde predomina el intelecto no relacionados con el título profesional',
        ],
        '307' => [
            'name' => 'Servicios Predomina Mano de Obra',
            'percentage' => 2.0,
            'description' => 'Servicios donde predomina la mano de obra',
        ],
        '308' => [
            'name' => 'Servicios Entre Sociedades',
            'percentage' => 2.0,
            'description' => 'Servicios prestados por sociedades',
        ],
        '309' => [
            'name' => 'Servicios Publicidad y Comunicación',
            'percentage' => 1.75,
            'description' => 'Servicios de publicidad y comunicación',
        ],
        '310' => [
            'name' => 'Transporte Privado de Pasajeros o Servicio Público',
            'percentage' => 1.0,
            'description' => 'Transporte privado de pasajeros o público o privado de carga',
        ],
        '311' => [
            'name' => 'Transferencia Bienes Inmuebles',
            'percentage' => 1.0,
            'description' => 'A través de liquidaciones de compra',
        ],
        '320' => [
            'name' => 'Arrendamiento Bienes Inmuebles',
            'percentage' => 8.0,
            'description' => 'Arrendamiento de bienes inmuebles',
        ],
        '322' => [
            'name' => 'Seguros y Reaseguros',
            'percentage' => 1.75,
            'description' => 'Seguros y reaseguros (primas y cesiones)',
        ],
        '323' => [
            'name' => 'Rendimientos Financieros',
            'percentage' => 2.0,
            'description' => 'Rendimientos financieros',
        ],
        '325' => [
            'name' => 'Loterías, Rifas, Apuestas',
            'percentage' => 15.0,
            'description' => 'Loterías, rifas, apuestas y similares',
        ],
        '327' => [
            'name' => 'Venta Combustibles',
            'percentage' => 0.2,
            'description' => 'Por venta de combustibles a comercializadores',
        ],
        '328' => [
            'name' => 'Compra Local Banano Productor',
            'percentage' => 1.0,
            'description' => 'Compra local de banano a productor',
        ],
        '332' => [
            'name' => 'Impuesto Único al Banano',
            'percentage' => 2.0,
            'description' => 'Pagos de bienes o servicios no sujetos a retención',
        ],
        '332A' => [
            'name' => 'Pago al Exterior Tarjeta Crédito/Débito',
            'percentage' => 0.0,
            'description' => 'Pagos al exterior con tarjeta de crédito o débito',
        ],
        '340' => [
            'name' => 'Otras Compras Bienes y Servicios',
            'percentage' => 1.0,
            'description' => 'Otras compras de bienes y servicios no sujetas a retención',
        ],
        '343' => [
            'name' => 'Enajenación Derechos Representativos de Capital',
            'percentage' => 1.0,
            'description' => 'Enajenación de derechos representativos de capital y otros derechos',
        ],
        '403' => [
            'name' => 'Sin Convenio Doble Tributación',
            'percentage' => 25.0,
            'description' => 'Pagos al exterior sin convenio de doble tributación',
        ],
        '405' => [
            'name' => 'Pagos al Exterior Paraísos Fiscales',
            'percentage' => 35.0,
            'description' => 'Pagos al exterior a países considerados paraísos fiscales',
        ],
    ];

    /**
     * Catálogo de Retenciones de IVA 2026
     */
    private const IVA_CATALOG = [
        '1' => [
            'name' => 'Retención IVA 30% (Bienes)',
            'percentage' => 30.0,
            'description' => 'Retención del 30% del IVA en compra de bienes',
        ],
        '2' => [
            'name' => 'Retención IVA 70% (Servicios)',
            'percentage' => 70.0,
            'description' => 'Retención del 70% del IVA en prestación de servicios',
        ],
        '3' => [
            'name' => 'Retención IVA 100%',
            'percentage' => 100.0,
            'description' => 'Retención del 100% del IVA (profesionales, arriendo, liquidaciones de compra)',
        ],
        '9' => [
            'name' => 'No Procede Retención IVA',
            'percentage' => 0.0,
            'description' => 'No procede retención de IVA',
        ],
    ];

    /**
     * Obtiene información de un código de retención de renta
     *
     * @return array{name: string, percentage: float, description: string}|null
     */
    public static function getRenta(string $code): ?array
    {
        return self::RENTA_CATALOG[$code] ?? null;
    }

    /**
     * Obtiene información de un código de retención de IVA
     *
     * @return array{name: string, percentage: float, description: string}|null
     */
    public static function getIva(string $code): ?array
    {
        return self::IVA_CATALOG[$code] ?? null;
    }

    /**
     * Obtiene el porcentaje de retención de renta
     */
    public static function getRentaPercentage(string $code): ?float
    {
        return self::RENTA_CATALOG[$code]['percentage'] ?? null;
    }

    /**
     * Obtiene el porcentaje de retención de IVA
     */
    public static function getIvaPercentage(string $code): ?float
    {
        return self::IVA_CATALOG[$code]['percentage'] ?? null;
    }

    /**
     * Verifica si un código de retención de renta es válido
     */
    public static function isValidRenta(string $code): bool
    {
        return isset(self::RENTA_CATALOG[$code]);
    }

    /**
     * Verifica si un código de retención de IVA es válido
     */
    public static function isValidIva(string $code): bool
    {
        return isset(self::IVA_CATALOG[$code]);
    }

    /**
     * Obtiene todo el catálogo de retenciones de renta
     *
     * @return array<string, array{name: string, percentage: float, description: string}>
     */
    public static function getAllRenta(): array
    {
        return self::RENTA_CATALOG;
    }

    /**
     * Obtiene todo el catálogo de retenciones de IVA
     *
     * @return array<string, array{name: string, percentage: float, description: string}>
     */
    public static function getAllIva(): array
    {
        return self::IVA_CATALOG;
    }

    /**
     * Calcula el valor de retención de renta
     */
    public static function calculateRenta(string $code, float $baseImponible): ?float
    {
        $percentage = self::getRentaPercentage($code);
        if ($percentage === null) {
            return null;
        }
        return round($baseImponible * ($percentage / 100), 2);
    }

    /**
     * Calcula el valor de retención de IVA
     */
    public static function calculateIva(string $code, float $valorIva): ?float
    {
        $percentage = self::getIvaPercentage($code);
        if ($percentage === null) {
            return null;
        }
        return round($valorIva * ($percentage / 100), 2);
    }
}
