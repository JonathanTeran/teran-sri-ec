<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Catálogo de Tipos de Contribuyente SRI Ecuador
 *
 * Referencia: Catálogos SRI
 * Actualizado: 2026
 */
class ContributorTypes
{
    /** Persona Natural */
    public const PERSONA_NATURAL = '01';

    /** Persona Natural Obligada a Llevar Contabilidad */
    public const PERSONA_NATURAL_OBLIGADO = '02';

    /** Sociedad */
    public const SOCIEDAD = '03';

    /** Contribuyente Especial */
    public const CONTRIBUYENTE_ESPECIAL = '04';

    /** RISE (Régimen Simplificado) */
    public const RISE = '05';

    /** RIMPE Emprendedor */
    public const RIMPE_EMPRENDEDOR = '06';

    /** RIMPE Negocio Popular */
    public const RIMPE_NEGOCIO_POPULAR = '07';

    /** Sector Público */
    public const SECTOR_PUBLICO = '08';

    /**
     * Catálogo completo de tipos de contribuyente
     */
    private const CATALOG = [
        '01' => [
            'name' => 'Persona Natural',
            'obligado_contabilidad' => false,
            'special_contributor' => false,
            'rimpe' => false,
            'retention_agent' => false,
        ],
        '02' => [
            'name' => 'Persona Natural Obligada a Llevar Contabilidad',
            'obligado_contabilidad' => true,
            'special_contributor' => false,
            'rimpe' => false,
            'retention_agent' => false,
        ],
        '03' => [
            'name' => 'Sociedad',
            'obligado_contabilidad' => true,
            'special_contributor' => false,
            'rimpe' => false,
            'retention_agent' => false,
        ],
        '04' => [
            'name' => 'Contribuyente Especial',
            'obligado_contabilidad' => true,
            'special_contributor' => true,
            'rimpe' => false,
            'retention_agent' => true,
        ],
        '05' => [
            'name' => 'RISE (Régimen Simplificado)',
            'obligado_contabilidad' => false,
            'special_contributor' => false,
            'rimpe' => false,
            'retention_agent' => false,
        ],
        '06' => [
            'name' => 'RIMPE Emprendedor',
            'obligado_contabilidad' => false,
            'special_contributor' => false,
            'rimpe' => true,
            'retention_agent' => false,
        ],
        '07' => [
            'name' => 'RIMPE Negocio Popular',
            'obligado_contabilidad' => false,
            'special_contributor' => false,
            'rimpe' => true,
            'retention_agent' => false,
        ],
        '08' => [
            'name' => 'Sector Público',
            'obligado_contabilidad' => true,
            'special_contributor' => false,
            'rimpe' => false,
            'retention_agent' => true,
        ],
    ];

    /**
     * Obtiene el nombre del tipo de contribuyente por su código
     */
    public static function getName(string $code): ?string
    {
        return self::CATALOG[$code]['name'] ?? null;
    }

    /**
     * Verifica si un código de tipo de contribuyente es válido
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CATALOG[$code]);
    }

    /**
     * Verifica si el contribuyente está obligado a llevar contabilidad
     */
    public static function isObligadoContabilidad(string $code): bool
    {
        return self::CATALOG[$code]['obligado_contabilidad'] ?? false;
    }

    /**
     * Verifica si es contribuyente especial
     */
    public static function isSpecialContributor(string $code): bool
    {
        return self::CATALOG[$code]['special_contributor'] ?? false;
    }

    /**
     * Verifica si es RIMPE (Emprendedor o Negocio Popular)
     */
    public static function isRimpe(string $code): bool
    {
        return self::CATALOG[$code]['rimpe'] ?? false;
    }

    /**
     * Verifica si es agente de retención
     */
    public static function isRetentionAgent(string $code): bool
    {
        return self::CATALOG[$code]['retention_agent'] ?? false;
    }

    /**
     * Obtiene el código de retención de renta aplicable para RIMPE
     */
    public static function getRimpeRetentionCode(string $code): ?string
    {
        if ($code === self::RIMPE_EMPRENDEDOR) {
            return RetentionCodes::RENTA_RIMPE_EMPRENDEDOR;
        }
        if ($code === self::RIMPE_NEGOCIO_POPULAR) {
            return RetentionCodes::RENTA_RIMPE_NEGOCIO_POPULAR;
        }
        return null;
    }

    /**
     * Obtiene todo el catálogo
     *
     * @return array<string, array{name: string, obligado_contabilidad: bool, special_contributor: bool, rimpe: bool, retention_agent: bool}>
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
     * Obtiene todos los tipos RIMPE
     *
     * @return array<string>
     */
    public static function getRimpeTypes(): array
    {
        return [self::RIMPE_EMPRENDEDOR, self::RIMPE_NEGOCIO_POPULAR];
    }
}
