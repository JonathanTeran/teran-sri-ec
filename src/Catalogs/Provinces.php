<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs;

/**
 * Catálogo de Provincias de Ecuador
 *
 * Códigos utilizados para la clave de acceso y validación de RUC
 * Las primeras 2 posiciones del RUC corresponden al código de provincia
 *
 * Referencia: División política de Ecuador
 * Actualizado: 2026
 */
class Provinces
{
    public const AZUAY = '01';
    public const BOLIVAR = '02';
    public const CANAR = '03';
    public const CARCHI = '04';
    public const COTOPAXI = '05';
    public const CHIMBORAZO = '06';
    public const EL_ORO = '07';
    public const ESMERALDAS = '08';
    public const GUAYAS = '09';
    public const IMBABURA = '10';
    public const LOJA = '11';
    public const LOS_RIOS = '12';
    public const MANABI = '13';
    public const MORONA_SANTIAGO = '14';
    public const NAPO = '15';
    public const PASTAZA = '16';
    public const PICHINCHA = '17';
    public const TUNGURAHUA = '18';
    public const ZAMORA_CHINCHIPE = '19';
    public const GALAPAGOS = '20';
    public const SUCUMBIOS = '21';
    public const ORELLANA = '22';
    public const SANTO_DOMINGO = '23';
    public const SANTA_ELENA = '24';

    /**
     * Regiones geográficas
     */
    public const REGION_SIERRA = 'sierra';
    public const REGION_COSTA = 'costa';
    public const REGION_ORIENTE = 'oriente';
    public const REGION_INSULAR = 'insular';

    /**
     * Catálogo completo de provincias
     */
    private const CATALOG = [
        '01' => ['name' => 'Azuay', 'capital' => 'Cuenca', 'region' => 'sierra'],
        '02' => ['name' => 'Bolívar', 'capital' => 'Guaranda', 'region' => 'sierra'],
        '03' => ['name' => 'Cañar', 'capital' => 'Azogues', 'region' => 'sierra'],
        '04' => ['name' => 'Carchi', 'capital' => 'Tulcán', 'region' => 'sierra'],
        '05' => ['name' => 'Cotopaxi', 'capital' => 'Latacunga', 'region' => 'sierra'],
        '06' => ['name' => 'Chimborazo', 'capital' => 'Riobamba', 'region' => 'sierra'],
        '07' => ['name' => 'El Oro', 'capital' => 'Machala', 'region' => 'costa'],
        '08' => ['name' => 'Esmeraldas', 'capital' => 'Esmeraldas', 'region' => 'costa'],
        '09' => ['name' => 'Guayas', 'capital' => 'Guayaquil', 'region' => 'costa'],
        '10' => ['name' => 'Imbabura', 'capital' => 'Ibarra', 'region' => 'sierra'],
        '11' => ['name' => 'Loja', 'capital' => 'Loja', 'region' => 'sierra'],
        '12' => ['name' => 'Los Ríos', 'capital' => 'Babahoyo', 'region' => 'costa'],
        '13' => ['name' => 'Manabí', 'capital' => 'Portoviejo', 'region' => 'costa'],
        '14' => ['name' => 'Morona Santiago', 'capital' => 'Macas', 'region' => 'oriente'],
        '15' => ['name' => 'Napo', 'capital' => 'Tena', 'region' => 'oriente'],
        '16' => ['name' => 'Pastaza', 'capital' => 'Puyo', 'region' => 'oriente'],
        '17' => ['name' => 'Pichincha', 'capital' => 'Quito', 'region' => 'sierra'],
        '18' => ['name' => 'Tungurahua', 'capital' => 'Ambato', 'region' => 'sierra'],
        '19' => ['name' => 'Zamora Chinchipe', 'capital' => 'Zamora', 'region' => 'oriente'],
        '20' => ['name' => 'Galápagos', 'capital' => 'Puerto Baquerizo Moreno', 'region' => 'insular'],
        '21' => ['name' => 'Sucumbíos', 'capital' => 'Nueva Loja', 'region' => 'oriente'],
        '22' => ['name' => 'Orellana', 'capital' => 'Puerto Francisco de Orellana', 'region' => 'oriente'],
        '23' => ['name' => 'Santo Domingo de los Tsáchilas', 'capital' => 'Santo Domingo', 'region' => 'costa'],
        '24' => ['name' => 'Santa Elena', 'capital' => 'Santa Elena', 'region' => 'costa'],
    ];

    /**
     * Obtiene el nombre de la provincia por su código
     */
    public static function getName(string $code): ?string
    {
        return self::CATALOG[$code]['name'] ?? null;
    }

    /**
     * Obtiene la capital de la provincia por su código
     */
    public static function getCapital(string $code): ?string
    {
        return self::CATALOG[$code]['capital'] ?? null;
    }

    /**
     * Obtiene la región de la provincia
     */
    public static function getRegion(string $code): ?string
    {
        return self::CATALOG[$code]['region'] ?? null;
    }

    /**
     * Verifica si un código de provincia es válido
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CATALOG[$code]);
    }

    /**
     * Extrae el código de provincia de un RUC/Cédula
     */
    public static function extractFromRuc(string $ruc): ?string
    {
        if (strlen($ruc) < 2) {
            return null;
        }
        $code = substr($ruc, 0, 2);
        return self::isValid($code) ? $code : null;
    }

    /**
     * Valida que un RUC pertenezca a una provincia válida
     */
    public static function validateRucProvince(string $ruc): bool
    {
        return self::extractFromRuc($ruc) !== null;
    }

    /**
     * Obtiene todo el catálogo
     *
     * @return array<string, array{name: string, capital: string, region: string}>
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
     * Obtiene provincias por región
     *
     * @return array<string, array{name: string, capital: string, region: string}>
     */
    public static function getByRegion(string $region): array
    {
        return array_filter(
            self::CATALOG,
            fn($province) => $province['region'] === $region
        );
    }

    /**
     * Obtiene todas las provincias de la Sierra
     *
     * @return array<string, array{name: string, capital: string, region: string}>
     */
    public static function getSierraProvinces(): array
    {
        return self::getByRegion(self::REGION_SIERRA);
    }

    /**
     * Obtiene todas las provincias de la Costa
     *
     * @return array<string, array{name: string, capital: string, region: string}>
     */
    public static function getCostaProvinces(): array
    {
        return self::getByRegion(self::REGION_COSTA);
    }

    /**
     * Obtiene todas las provincias del Oriente
     *
     * @return array<string, array{name: string, capital: string, region: string}>
     */
    public static function getOrienteProvinces(): array
    {
        return self::getByRegion(self::REGION_ORIENTE);
    }
}
