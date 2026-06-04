<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\CertificateException;

final class CertificateLoader
{
    /**
     * Carga un .p12: primero el lector nativo de PHP; si falla (típico en
     * certificados legacy pre-2024), recurre al fallback de OpenSSL CLI seguro.
     */
    public function load(string $p12Content, string $password): Certificate
    {
        $certs = [];
        if (openssl_pkcs12_read($p12Content, $certs, $password)) {
            return new Certificate(
                $certs['cert'] ?? '',
                $certs['pkey'] ?? '',
                $certs['extracerts'] ?? [],
            );
        }

        if ($this->hasOpensslBinary()) {
            return $this->loadViaOpensslCli($p12Content, $password);
        }

        throw new CertificateException(
            'No se pudo leer el certificado .p12 con el lector nativo y no hay binario openssl para el fallback. ' .
            'Verifique la contraseña y el archivo.'
        );
    }

    /**
     * Fallback CLI ENDURECIDO:
     *  - M-1: la contraseña se pasa por stdin (no por argv → invisible en `ps`).
     *  - M-2: el PEM descifrado se lee por stdout (la clave privada NUNCA se escribe a disco).
     *  - El .p12 (cifrado) se escribe a un temporal con permisos 0600 y se borra en `finally`.
     *  - `proc_open` con comando en ARRAY → sin shell, sin inyección.
     */
    public function loadViaOpensslCli(string $p12Content, string $password): Certificate
    {
        $bin = $this->opensslBinary();
        if ($bin === null) {
            throw new CertificateException('No hay binario openssl disponible para el fallback CLI.');
        }

        $tempP12 = tempnam(sys_get_temp_dir(), 'sri_p12in_');
        if ($tempP12 === false) {
            throw new CertificateException('No se pudo crear el archivo temporal para el certificado.');
        }
        file_put_contents($tempP12, $p12Content);

        try {
            // Intento estándar y, si falla, con -legacy para cifrados RC2/3DES.
            foreach ([[], ['-legacy', '-provider', 'default']] as $extraArgs) {
                $pem = $this->runOpenssl($bin, $tempP12, $password, $extraArgs);
                if ($pem !== null) {
                    return $this->parsePem($pem);
                }
            }
        } finally {
            if (is_file($tempP12)) {
                @unlink($tempP12);
            }
        }

        throw new CertificateException(
            'No se pudo descifrar el certificado .p12 con OpenSSL CLI. Verifique la contraseña.'
        );
    }

    /** Ejecuta openssl pkcs12 con password por stdin; devuelve el PEM por stdout o null si falla. */
    private function runOpenssl(string $bin, string $tempP12, string $password, array $extraArgs): ?string
    {
        $cmd = array_merge(
            [$bin, 'pkcs12', '-in', $tempP12, '-nodes', '-passin', 'stdin'],
            $extraArgs
        );

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return null;
        }

        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code === 0 && is_string($stdout) && str_contains($stdout, 'BEGIN')) {
            return $stdout;
        }
        return null;
    }

    private function parsePem(string $pem): Certificate
    {
        $cert = '';
        $key = '';
        if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m)) {
            $cert = $m[0];
        }
        if (preg_match('/-----BEGIN (?:ENCRYPTED )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED )?PRIVATE KEY-----/s', $pem, $m)) {
            $key = $m[0];
        }
        if ($cert === '' || $key === '') {
            throw new CertificateException('El PEM descifrado no contiene certificado y/o clave privada.');
        }
        return new Certificate($cert, $key, []);
    }

    public function hasOpensslBinary(): bool
    {
        return $this->opensslBinary() !== null;
    }

    private function opensslBinary(): ?string
    {
        $candidates = [
            '/opt/homebrew/opt/openssl@1.1/bin/openssl',
            '/usr/local/opt/openssl@1.1/bin/openssl',
            '/opt/homebrew/opt/openssl@3/bin/openssl',
            '/usr/local/opt/openssl@3/bin/openssl',
        ];
        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        // Buscar en PATH.
        $which = @shell_exec('command -v openssl 2>/dev/null');
        if (is_string($which) && trim($which) !== '') {
            return trim($which);
        }
        return null;
    }
}
