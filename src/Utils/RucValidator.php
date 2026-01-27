<?php

declare(strict_types=1);

namespace Teran\Sri\Utils;

use Teran\Sri\Schema\BusinessValidator;

class RucValidator
{
    private const SRI_URL = 'https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/existePorNumeroRuc?numeroRuc=';

    public function validate(string $ruc): bool
    {
        // 1. Validación local primero (rápida)
        if (!BusinessValidator::validarRuc($ruc)) {
            return false;
        }

        // 2. Intentar validar online (opcional, si falla usamos la validación local)
        if ($this->checkOnline($ruc)) {
            return true;
        }

        // 3. Si la validación online falla pero la local pasó, aceptamos el RUC
        return true;
    }

    private function checkOnline(string $ruc): bool
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::SRI_URL . $ruc);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout corto para no detener el proceso
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // En producción se debería validar

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response === 'true') {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            // Si falla la conexión, permitimos que el fallback local decida
            return false;
        }
    }
}
