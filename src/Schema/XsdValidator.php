<?php

declare(strict_types=1);

namespace Teran\Sri\Schema;

use Teran\Sri\Exceptions\ValidationException;
use DOMDocument;

class XsdValidator
{
    /**
     * Valida un XML contra un archivo XSD específico.
     * 
     * @param string $xmlContent
     * @param string $xsdPath
     * @return bool
     * @throws ValidationException
     */
    public static function validate(string $xmlContent, string $xsdPath): bool
    {
        if (!file_exists($xsdPath)) {
            throw new ValidationException("No se encontró el archivo XSD: $xsdPath");
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        if (!$dom->loadXML($xmlContent)) {
            $errors = self::getLibXmlErrors();
            libxml_clear_errors();
            throw new ValidationException("Error al cargar el XML para validación.", $errors);
        }

        if (!$dom->schemaValidate($xsdPath)) {
            $errors = self::getLibXmlErrors();
            libxml_clear_errors();
            throw new ValidationException("El XML no cumple con el esquema XSD.", $errors);
        }

        return true;
    }

    private static function getLibXmlErrors(): array
    {
        $errors = libxml_get_errors();
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = sprintf(
                "Línea %d: %s",
                $error->line,
                trim($error->message)
            );
        }
        return $formattedErrors;
    }
}
