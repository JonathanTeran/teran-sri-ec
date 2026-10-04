<?php

declare(strict_types=1);

namespace Teran\Sri\Support;

/**
 * Traduce al español los mensajes de bajo nivel que devuelven SoapClient,
 * libxml y cURL cuando la comunicación con el SRI falla.
 *
 * Esos textos ("Could not connect to host", "Error Fetching http headers",
 * "failed to load external entity"…) acaban en alertas, en el detalle del
 * comprobante y delante de contadores que no tienen por qué leer inglés ni
 * saber qué es un WSDL. Aquí se convierten en una frase que dice qué pasó con
 * el SRI; cuando el texto no se reconoce, se conserva tal cual para no perder
 * la pista.
 */
final class MensajeSri
{
    /**
     * Patrones (en minúsculas, por `str_contains`) → explicación. El orden
     * importa: el primero que coincide gana, así que lo específico va antes
     * que lo genérico.
     *
     * @var array<string, string>
     */
    private const TRADUCCIONES = [
        'could not connect to host' => 'no se pudo conectar con el servidor del SRI',
        'connection refused' => 'el servidor del SRI rechazó la conexión',
        'connection reset' => 'el servidor del SRI cortó la conexión',
        'connection timed out' => 'el servidor del SRI no respondió a tiempo',
        'operation timed out' => 'el servidor del SRI no respondió a tiempo',
        'timed out' => 'el servidor del SRI no respondió a tiempo',
        'timeout' => 'el servidor del SRI no respondió a tiempo',
        'error fetching http headers' => 'el servidor del SRI cortó la conexión sin responder',
        'failed to load external entity' => 'el SRI no entregó la definición del servicio (WSDL); el servicio está caído o inaccesible',
        'parsing wsdl' => 'el SRI no entregó la definición del servicio (WSDL); el servicio está caído o inaccesible',
        'getaddrinfo' => 'no se pudo resolver el dominio del SRI (fallo de DNS)',
        'name or service not known' => 'no se pudo resolver el dominio del SRI (fallo de DNS)',
        'could not resolve host' => 'no se pudo resolver el dominio del SRI (fallo de DNS)',
        'ssl' => 'falló la conexión segura (SSL/TLS) con el servidor del SRI',
        'certificate' => 'falló la conexión segura (SSL/TLS) con el servidor del SRI',
        'service unavailable' => 'el servicio del SRI está fuera de línea (503)',
        'bad gateway' => 'el servicio del SRI está fuera de línea (502)',
        'gateway time-out' => 'el servicio del SRI está fuera de línea (504)',
        'gateway timeout' => 'el servicio del SRI está fuera de línea (504)',
        'internal server error' => 'el servidor del SRI devolvió un error interno (500)',
        'looks like we got no xml document' => 'el SRI respondió con algo que no es XML (suele ser una página de mantenimiento)',
        'unauthorized' => 'el servidor del SRI rechazó la petición (401)',
        'forbidden' => 'el servidor del SRI rechazó la petición (403)',
        'not found' => 'la dirección del servicio del SRI no existe (404)',
    ];

    /**
     * Explica en español una falla de comunicación con el SRI.
     *
     * @param string $mensajeOriginal Texto crudo de SoapFault, libxml o cURL.
     */
    public static function deFalla(string $mensajeOriginal): string
    {
        $mensaje = trim($mensajeOriginal);
        if ($mensaje === '') {
            return 'el servidor del SRI no respondió';
        }

        $enMinusculas = mb_strtolower($mensaje);

        foreach (self::TRADUCCIONES as $patron => $explicacion) {
            if (str_contains($enMinusculas, $patron)) {
                return $explicacion;
            }
        }

        return 'el SRI respondió: ' . $mensaje;
    }

    /**
     * Mensaje completo de la excepción de comunicación tras agotar reintentos.
     */
    public static function trasReintentos(int $intentos, string $mensajeOriginal): string
    {
        $veces = $intentos === 1 ? '1 intento' : "{$intentos} intentos";

        return "Error de comunicación con el SRI tras {$veces}: " . self::deFalla($mensajeOriginal) . '.';
    }
}
