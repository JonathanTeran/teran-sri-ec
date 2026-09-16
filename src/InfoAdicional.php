<?php

declare(strict_types=1);

namespace Teran\Sri;

use DOMDocument;
use DOMElement;
use Teran\Sri\Exceptions\ValidationException;

/**
 * Información adicional (`<infoAdicional>`) de los comprobantes electrónicos.
 *
 * Reúne en un solo lugar las reglas del esquema y de la normativa:
 *
 * - XSD offline: hasta 15 `<campoAdicional>`, con `nombre` y valor de 1 a 300
 *   caracteres.
 * - Resolución NAC-DGERCGC26-00000027 (R.O. 5.º Supl. 335, 28-jul-2026) y
 *   Ficha Técnica de Comprobantes Electrónicos Offline v2.34, Anexo 26: quien
 *   emite con un sistema de un tercero debe incluir en TODOS los comprobantes
 *
 *       <campoAdicional nombre="RUC Proveedor">RUC del proveedor</campoAdicional>
 *
 *   obligatorio desde el 26-sep-2026 (60 días calendario desde la publicación).
 *
 * Lo usan los generadores de la API 1.x y los serializadores de la 2.0, así
 * que ambas producen exactamente el mismo bloque.
 */
final class InfoAdicional
{
    /** Valor del atributo `nombre` exigido por el Anexo 26 de la ficha técnica v2.34. */
    public const CAMPO_RUC_PROVEEDOR = 'RUC Proveedor';

    /** `maxOccurs` de `campoAdicional` en los XSD offline. */
    public const MAX_CAMPOS = 15;

    /** `maxLength` de `campoAdicionalNombre` y `campoAdicionalValor`. */
    public const MAX_LONGITUD = 300;

    /**
     * Normaliza la información adicional a un mapa `nombre => valor`.
     *
     * Acepta un mapa (`['Email' => 'a@b.com']`) o una lista de pares
     * (`[['nombre' => 'Email', 'valor' => 'a@b.com']]`). Descarta los valores
     * vacíos (el XSD exige al menos un carácter). Si se indica `$rucProveedor`,
     * agrega el campo «RUC Proveedor» al final; si ya venía con ese nombre (sin
     * distinguir mayúsculas ni espacios), lo reemplaza para no duplicarlo.
     *
     * @return array<string, string>
     *
     * @throws ValidationException si el RUC es inválido, hay más de 15 campos o
     *                             un nombre o valor supera los 300 caracteres.
     */
    public static function normalizar(mixed $campos, ?string $rucProveedor = null): array
    {
        $mapa = [];

        foreach (self::pares($campos) as [$nombre, $valor]) {
            if ($nombre === '' || $valor === '') {
                continue;
            }

            $mapa[$nombre] = $valor;
        }

        if ($rucProveedor !== null && trim($rucProveedor) !== '') {
            $ruc = self::validarRucProveedor($rucProveedor);

            foreach (array_keys($mapa) as $existente) {
                if (self::clave((string) $existente) === self::clave(self::CAMPO_RUC_PROVEEDOR)) {
                    unset($mapa[$existente]);
                }
            }

            $mapa[self::CAMPO_RUC_PROVEEDOR] = $ruc;
        }

        if (count($mapa) > self::MAX_CAMPOS) {
            throw new ValidationException(sprintf(
                'infoAdicional: el SRI admite como máximo %d campos adicionales y se enviaron %d.',
                self::MAX_CAMPOS,
                count($mapa),
            ));
        }

        foreach ($mapa as $nombre => $valor) {
            if (mb_strlen((string) $nombre) > self::MAX_LONGITUD || mb_strlen($valor) > self::MAX_LONGITUD) {
                throw new ValidationException(sprintf(
                    "infoAdicional: el campo '%s' supera los %d caracteres permitidos (nombre o valor).",
                    mb_substr((string) $nombre, 0, 40),
                    self::MAX_LONGITUD,
                ));
            }
        }

        return $mapa;
    }

    /**
     * Valida el RUC del proveedor del sistema: 13 dígitos terminados en 001.
     * Acepta separadores (espacios, guiones) y devuelve solo los dígitos.
     *
     * @throws ValidationException
     */
    public static function validarRucProveedor(string $ruc): string
    {
        $digitos = preg_replace('/\D+/', '', $ruc) ?? '';

        if (preg_match('/^\d{10}001$/', $digitos) !== 1) {
            throw new ValidationException(
                "RUC del proveedor del sistema inválido '{$ruc}': debe tener 13 dígitos y terminar en 001 (Resolución NAC-DGERCGC26-00000027)."
            );
        }

        return $digitos;
    }

    /**
     * Escribe `<infoAdicional>` al final de `$root`. No escribe nada si no hay
     * campos (el nodo es opcional y un `<infoAdicional/>` vacío no valida).
     *
     * @param array<string, string> $campos mapa ya normalizado
     */
    public static function escribir(DOMDocument $dom, DOMElement $root, array $campos): void
    {
        if ($campos === []) {
            return;
        }

        $node = $dom->createElement('infoAdicional');
        $root->appendChild($node);

        foreach ($campos as $nombre => $valor) {
            $campo = $dom->createElement('campoAdicional');
            $campo->appendChild($dom->createTextNode($valor));
            $campo->setAttribute('nombre', (string) $nombre);
            $node->appendChild($campo);
        }
    }

    /**
     * @return iterable<int, array{0: string, 1: string}>
     */
    private static function pares(mixed $campos): iterable
    {
        if (!is_array($campos)) {
            return [];
        }

        $pares = [];

        foreach ($campos as $clave => $valor) {
            if (is_array($valor)) {
                $nombre = $valor['nombre'] ?? $valor['name'] ?? null;
                $contenido = $valor['valor'] ?? $valor['value'] ?? null;
            } else {
                $nombre = is_string($clave) ? $clave : null;
                $contenido = $valor;
            }

            if (!is_scalar($nombre) || !(is_scalar($contenido) || $contenido === null)) {
                continue;
            }

            $pares[] = [trim((string) $nombre), trim((string) ($contenido ?? ''))];
        }

        return $pares;
    }

    private static function clave(string $nombre): string
    {
        return mb_strtolower(trim($nombre));
    }
}
