<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Catálogo de Formas de Pago SRI Ecuador
 *
 * Referencia: Ficha Técnica Comprobantes Electrónicos SRI
 * Actualizado: 2026
 */
class PaymentMethods
{
    /** Sin utilización del sistema financiero (Efectivo) */
    public const EFECTIVO = '01';

    /** Compensación de deudas */
    public const COMPENSACION_DEUDAS = '15';

    /** Tarjeta de débito */
    public const TARJETA_DEBITO = '16';

    /** Dinero electrónico */
    public const DINERO_ELECTRONICO = '17';

    /** Tarjeta prepago */
    public const TARJETA_PREPAGO = '18';

    /** Tarjeta de crédito */
    public const TARJETA_CREDITO = '19';

    /** Otros con utilización del sistema financiero */
    public const OTROS_SISTEMA_FINANCIERO = '20';

    /** Endoso de títulos */
    public const ENDOSO_TITULOS = '21';

    /**
     * Catálogo completo de formas de pago
     */
    private const CATALOG = [
        '01' => 'Sin utilización del sistema financiero (Efectivo)',
        '15' => 'Compensación de deudas',
        '16' => 'Tarjeta de débito',
        '17' => 'Dinero electrónico',
        '18' => 'Tarjeta prepago',
        '19' => 'Tarjeta de crédito',
        '20' => 'Otros con utilización del sistema financiero',
        '21' => 'Endoso de títulos',
    ];

    /**
     * Obtiene el nombre de la forma de pago por su código
     */
    public static function getName(string $code): ?string
    {
        return self::CATALOG[$code] ?? null;
    }

    /**
     * Verifica si un código de forma de pago es válido
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CATALOG[$code]);
    }

    /**
     * Obtiene todo el catálogo
     *
     * @return array<string, string>
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
}
