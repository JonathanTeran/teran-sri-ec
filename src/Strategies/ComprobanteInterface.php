<?php

declare(strict_types=1);

namespace Teran\Sri\Strategies;

interface ComprobanteInterface
{
    /**
     * Devuelve el código de tipo de comprobante (ej: 01 para Factura).
     */
    public function getTipo(): string;

    /**
     * Genera el XML del comprobante.
     */
    public function generarXml(): string;

    /**
     * Devuelve la ruta al archivo XSD para validación.
     */
    public function getXsdPath(): string;

    /**
     * Devuelve los datos necesarios para generar la clave de acceso.
     */
    public function getDatosClave(): array;
}
