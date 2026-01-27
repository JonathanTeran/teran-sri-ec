<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Configuración de Parámetros SRI Ecuador
 *
 * Valores regulatorios configurables para 2026
 * Referencia: Resolución NAC-DGERCGC25-00000017
 * Fuente: https://www.sri.gob.ec/datos-abiertos
 *
 * Actualizado: 2026
 */
class SriConfig
{
    // ========================================
    // TASAS DE IMPUESTOS 2026
    // ========================================

    /** Tasa de IVA estándar */
    public const IVA_RATE = 15.0;

    /** Tasa de IVA para construcción */
    public const IVA_RATE_CONSTRUCCION = 5.0;

    /** Tasa IVA cero */
    public const IVA_RATE_ZERO = 0.0;

    /** IVA No objeto */
    public const IVA_NO_OBJETO = 6;

    /** IVA Exento */
    public const IVA_EXENTO = 7;

    // ========================================
    // PARÁMETROS ECONÓMICOS 2026
    // ========================================

    /** Salario Básico Unificado (SBU) */
    public const SBU = 482.00;

    /** Aporte Personal IESS */
    public const IESS_APORTE_PERSONAL = 9.45;

    /** Aporte Patronal IESS */
    public const IESS_APORTE_PATRONAL = 11.15;

    // ========================================
    // CONSUMIDOR FINAL
    // ========================================

    /** RUC del Consumidor Final */
    public const CONSUMIDOR_FINAL_RUC = '9999999999999';

    /** Límite de venta a Consumidor Final sin identificación */
    public const CONSUMIDOR_FINAL_LIMIT = 50.00;

    // ========================================
    // REGLAS DE ANULACIÓN
    // ========================================

    /** Días límite para anular comprobantes */
    public const ANNULMENT_DAY_LIMIT = 7;

    // ========================================
    // ADUANAS
    // ========================================

    /** FODINFA (Fondo de Desarrollo para la Infancia) */
    public const FODINFA_RATE = 0.5;

    /** IVA para importaciones */
    public const CUSTOMS_IVA_RATE = 15.0;

    // ========================================
    // TIPOS DE COMPROBANTE
    // ========================================

    /** Factura */
    public const DOC_TYPE_FACTURA = '01';

    /** Liquidación de Compra */
    public const DOC_TYPE_LIQ_COMPRA = '03';

    /** Nota de Crédito */
    public const DOC_TYPE_NOTA_CREDITO = '04';

    /** Nota de Débito */
    public const DOC_TYPE_NOTA_DEBITO = '05';

    /** Guía de Remisión */
    public const DOC_TYPE_GUIA_REMISION = '06';

    /** Comprobante de Retención */
    public const DOC_TYPE_RETENCION = '07';

    // ========================================
    // URLs DE WEBSERVICES SRI
    // ========================================

    /** URL de Recepción - Ambiente de Pruebas */
    public const SRI_RECEPTION_URL_TEST = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';

    /** URL de Autorización - Ambiente de Pruebas */
    public const SRI_AUTHORIZATION_URL_TEST = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    /** URL de Recepción - Ambiente de Producción */
    public const SRI_RECEPTION_URL_PROD = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';

    /** URL de Autorización - Ambiente de Producción */
    public const SRI_AUTHORIZATION_URL_PROD = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    /** URL de consulta de RUC */
    public const SRI_RUC_API_URL = 'https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/obtenerPorNumerosRuc';

    // ========================================
    // CALENDARIO TRIBUTARIO
    // ========================================

    /**
     * Mapa de días de vencimiento según noveno dígito del RUC
     */
    private const DEADLINE_DAY_MAP = [
        '1' => 10,
        '2' => 12,
        '3' => 14,
        '4' => 16,
        '5' => 18,
        '6' => 20,
        '7' => 22,
        '8' => 24,
        '9' => 26,
        '0' => 28,
    ];

    /**
     * Día de vencimiento para Contribuyentes Especiales
     */
    public const SPECIAL_CONTRIBUTOR_DAY = 9;

    /**
     * Mes de declaración IR para personas naturales
     */
    public const IR_NATURAL_MONTH = 3; // Marzo

    /**
     * Mes de declaración IR para sociedades
     */
    public const IR_SOCIEDADES_MONTH = 4; // Abril

    /**
     * Mapa de fechas límite para RDEP (mes-día)
     */
    private const RDEP_DEADLINE_MAP = [
        '1' => '01-21',
        '2' => '01-23',
        '3' => '01-25',
        '4' => '01-27',
        '5' => '01-29',
        '6' => '01-31',
        '7' => '02-03',
        '8' => '02-05',
        '9' => '02-07',
        '0' => '02-07',
    ];

    /**
     * Obtiene la URL de recepción según el ambiente
     */
    public static function getReceptionUrl(bool $production = false): string
    {
        return $production ? self::SRI_RECEPTION_URL_PROD : self::SRI_RECEPTION_URL_TEST;
    }

    /**
     * Obtiene la URL de autorización según el ambiente
     */
    public static function getAuthorizationUrl(bool $production = false): string
    {
        return $production ? self::SRI_AUTHORIZATION_URL_PROD : self::SRI_AUTHORIZATION_URL_TEST;
    }

    /**
     * Obtiene el día de vencimiento según el noveno dígito del RUC
     */
    public static function getDeadlineDay(string $ruc): ?int
    {
        if (strlen($ruc) < 9) {
            return null;
        }
        $ninthDigit = $ruc[8];
        return self::DEADLINE_DAY_MAP[$ninthDigit] ?? null;
    }

    /**
     * Obtiene la fecha límite de declaración de IVA para un período
     *
     * @param string $ruc RUC del contribuyente
     * @param int $year Año del período
     * @param int $month Mes del período (1-12)
     * @param bool $isSpecialContributor Si es contribuyente especial
     * @return \DateTimeInterface|null
     */
    public static function getIvaDeadline(
        string $ruc,
        int $year,
        int $month,
        bool $isSpecialContributor = false
    ): ?\DateTimeInterface {
        // El mes siguiente al período
        $deadlineMonth = $month === 12 ? 1 : $month + 1;
        $deadlineYear = $month === 12 ? $year + 1 : $year;

        $day = $isSpecialContributor
            ? self::SPECIAL_CONTRIBUTOR_DAY
            : self::getDeadlineDay($ruc);

        if ($day === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable(
                sprintf('%04d-%02d-%02d', $deadlineYear, $deadlineMonth, $day)
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene la fecha límite para la declaración del RDEP
     *
     * @param string $ruc RUC del contribuyente
     * @param int $year Año fiscal del que se reporta
     * @return \DateTimeInterface|null
     */
    public static function getRdepDeadline(string $ruc, int $year): ?\DateTimeInterface
    {
        if (strlen($ruc) < 9) {
            return null;
        }

        $ninthDigit = $ruc[8];
        $monthDay = self::RDEP_DEADLINE_MAP[$ninthDigit] ?? null;

        if ($monthDay === null) {
            return null;
        }

        // RDEP se presenta en enero-febrero del año siguiente
        try {
            return new \DateTimeImmutable(
                sprintf('%04d-%s', $year + 1, $monthDay)
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calcula el aporte personal al IESS
     */
    public static function calculateAportePersonal(float $salario): float
    {
        return round($salario * (self::IESS_APORTE_PERSONAL / 100), 2);
    }

    /**
     * Calcula el aporte patronal al IESS
     */
    public static function calculateAportePatronal(float $salario): float
    {
        return round($salario * (self::IESS_APORTE_PATRONAL / 100), 2);
    }

    /**
     * Calcula el FODINFA para importaciones
     */
    public static function calculateFodinfa(float $valorCif): float
    {
        return round($valorCif * (self::FODINFA_RATE / 100), 2);
    }

    /**
     * Calcula el IVA para importaciones
     */
    public static function calculateCustomsIva(float $baseImponible): float
    {
        return round($baseImponible * (self::CUSTOMS_IVA_RATE / 100), 2);
    }

    /**
     * Verifica si una venta requiere identificación del cliente
     */
    public static function requiresCustomerIdentification(float $totalVenta): bool
    {
        return $totalVenta > self::CONSUMIDOR_FINAL_LIMIT;
    }

    /**
     * Obtiene el nombre del tipo de comprobante
     */
    public static function getDocTypeName(string $code): ?string
    {
        $names = [
            self::DOC_TYPE_FACTURA => 'Factura',
            self::DOC_TYPE_LIQ_COMPRA => 'Liquidación de Compra',
            self::DOC_TYPE_NOTA_CREDITO => 'Nota de Crédito',
            self::DOC_TYPE_NOTA_DEBITO => 'Nota de Débito',
            self::DOC_TYPE_GUIA_REMISION => 'Guía de Remisión',
            self::DOC_TYPE_RETENCION => 'Comprobante de Retención',
        ];
        return $names[$code] ?? null;
    }
}
