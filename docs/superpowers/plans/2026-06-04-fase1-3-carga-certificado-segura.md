# Fase 1.3 — Carga segura del certificado (M-1/M-2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cargar un certificado PKCS#12 de forma segura para el 2.0: lector nativo de PHP primero y, para certificados legacy, un *fallback* por OpenSSL CLI que pasa la contraseña por **stdin** (no por `argv` — corrige M-1) y captura el PEM descifrado por **stdout** (la clave privada **nunca se escribe a disco** — corrige M-2), con limpieza garantizada del `.p12` temporal en `finally`.

**Architecture:** Código nuevo bajo `src/Signing/`. `Certificate` es un value object inmutable (PEM del certificado, PEM de la clave privada, CAs intermedias). `CertificateLoader::load()` intenta `openssl_pkcs12_read` (nativo); si falla, usa `loadViaOpensslCli()` que invoca `openssl pkcs12 -nodes -passin stdin` vía `proc_open` con **comando en forma de array** (sin shell → sin inyección), escribe la contraseña al pipe de stdin y lee el PEM de stdout. No toca el código 1.x (`src/Signature/XadesSignature.php` sigue como shim). Los tests generan certificados de prueba **en PHP puro** con `openssl_*` (sin depender de un .p12 real ni de binarios para generarlos).

**Tech Stack:** PHP 8.2 (`proc_open` con array), `ext-openssl`, PHPUnit 10.

---

## Contexto y alcance

Plan 3 de la Fase 1. Cubre **solo la carga/parseo seguro del certificado** (los hallazgos M-1/M-2 de la auditoría de seguridad). La firma XAdES propiamente (construcción del XML firmado, que consumirá `Certificate`) es el plan siguiente (Fase 1.4), reutilizando la lógica probada del 1.x. No reimplementamos XAdES aquí.

### Mapa de archivos

- Crear: `src/Exceptions/CertificateException.php` — excepción tipada.
- Crear: `src/Signing/Certificate.php` — value object (certPem, privateKeyPem, extraCerts).
- Crear: `src/Signing/CertificateLoader.php` — `load()` nativo + `loadViaOpensslCli()` (M-1/M-2).
- Test: `tests/Unit/Signing/CertificateTest.php`, `tests/Unit/Signing/CertificateLoaderTest.php`.
- Test helper: `tests/Support/TestCertificate.php` — genera un .p12 de prueba en PHP puro.

---

### Task 1: Excepción `CertificateException`

**Files:**
- Create: `src/Exceptions/CertificateException.php`
- Test: (cubierto indirectamente por tareas siguientes; no se requiere test propio para una subclase trivial — ver Step 2)

- [ ] **Step 1: Crear la excepción**

Mirar primero `src/Exceptions/SriException.php` para replicar el patrón exacto (constructor/firma). Luego crear `src/Exceptions/CertificateException.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Exceptions;

class CertificateException extends SriException
{
}
```

> Si `SriException` define un constructor con parámetros adicionales, NO lo sobreescribas: hereda tal cual.

- [ ] **Step 2: Verificar que carga (autoload) y la suite sigue verde**

Run: `php -r "require 'vendor/autoload.php'; throw new Teran\Sri\Exceptions\CertificateException('x');" 2>&1 | head -1` — debe imprimir el mensaje/clase, no "Class not found".
Run: `./vendor/bin/phpunit` — Expected: verde (67 tests, 1 skip), sin cambios de conteo.

- [ ] **Step 3: Commit**

```bash
git add src/Exceptions/CertificateException.php
git commit -m "feat: add CertificateException type"
```

---

### Task 2: Value object `Certificate`

**Files:**
- Create: `src/Signing/Certificate.php`
- Test: `tests/Unit/Signing/CertificateTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Exceptions\CertificateException;

class CertificateTest extends TestCase
{
    public function test_exposes_pems_and_x509_info(): void
    {
        // Generar par de claves + certificado autofirmado en PHP puro.
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'PRUEBA SRI'], $pkey);
        $x509 = openssl_csr_sign($csr, null, $pkey, 1);
        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($pkey, $keyPem);

        $cert = new Certificate($certPem, $keyPem, []);

        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->certPem);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);
        $this->assertSame('PRUEBA SRI', $cert->x509Info()['subject']['CN']);
    }

    public function test_rejects_empty_cert_or_key(): void
    {
        $this->expectException(CertificateException::class);
        new Certificate('', 'x', []);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Signing/CertificateTest.php`
Expected: FAIL con `Class "Teran\Sri\Signing\Certificate" not found`.

- [ ] **Step 3: Implementar `Certificate`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\CertificateException;

final class Certificate
{
    /** @param string[] $extraCerts CAs intermedias en PEM */
    public function __construct(
        public readonly string $certPem,
        public readonly string $privateKeyPem,
        public readonly array $extraCerts = [],
    ) {
        if (trim($certPem) === '') {
            throw new CertificateException('Certificate: el PEM del certificado está vacío.');
        }
        if (trim($privateKeyPem) === '') {
            throw new CertificateException('Certificate: el PEM de la clave privada está vacío.');
        }
    }

    /** @return array<string,mixed> openssl_x509_parse del certificado */
    public function x509Info(): array
    {
        $info = openssl_x509_parse($this->certPem);
        if ($info === false) {
            throw new CertificateException('Certificate: no se pudo parsear el certificado X.509.');
        }
        return $info;
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Signing/CertificateTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Signing/Certificate.php tests/Unit/Signing/CertificateTest.php
git commit -m "feat: add Certificate value object"
```

---

### Task 3: Helper de test `TestCertificate` (genera un .p12 en PHP)

**Files:**
- Create: `tests/Support/TestCertificate.php`

**Contexto:** para probar el `CertificateLoader` sin un .p12 real, generamos uno en PHP con `openssl_pkcs12_export`. Esto es portable (no depende de binarios para generar). Añadir el namespace de soporte al autoload-dev si no resuelve.

- [ ] **Step 1: Crear el helper**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

final class TestCertificate
{
    /**
     * Genera un .p12 moderno autofirmado en memoria.
     *
     * @return array{p12: string, password: string, subjectCN: string}
     */
    public static function modernP12(string $password = 'test-pass', string $cn = 'PRUEBA SRI 1790011001001'): array
    {
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => $cn], $pkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);

        $p12 = '';
        openssl_pkcs12_export($x509, $p12, $pkey, $password);

        return ['p12' => $p12, 'password' => $password, 'subjectCN' => $cn];
    }
}
```

- [ ] **Step 2: Verificar autoload-dev del namespace de soporte**

Revisar `composer.json` → `autoload-dev`. Actualmente mapea `Teran\\Sri\\Tests\\` a `tests/`. El helper en `tests/Support/TestCertificate.php` con namespace `Teran\Sri\Tests\Support` resuelve a `tests/Support/` → ✅ ya cubierto. Ejecutar `composer dump-autoload` para regenerar.

Run: `composer dump-autoload 2>&1 | tail -1 && php -r "require 'vendor/autoload.php'; var_dump(strlen(Teran\Sri\Tests\Support\TestCertificate::modernP12()['p12']) > 0);"`
Expected: `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add tests/Support/TestCertificate.php composer.json composer.lock
git commit -m "test: add TestCertificate helper (generates a modern p12 in PHP)"
```

---

### Task 4: `CertificateLoader` — nativo + fallback CLI seguro (M-1/M-2)

**Files:**
- Create: `src/Signing/CertificateLoader.php`
- Test: `tests/Unit/Signing/CertificateLoaderTest.php`

- [ ] **Step 1: Escribir los tests que fallan**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Exceptions\CertificateException;
use Teran\Sri\Tests\Support\TestCertificate;

class CertificateLoaderTest extends TestCase
{
    public function test_loads_via_native_reader(): void
    {
        $tc = TestCertificate::modernP12();

        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $this->assertInstanceOf(Certificate::class, $cert);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert->certPem);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);
        $this->assertStringContainsString('PRUEBA SRI', $cert->x509Info()['subject']['CN']);
    }

    public function test_wrong_password_throws(): void
    {
        $tc = TestCertificate::modernP12();

        $this->expectException(CertificateException::class);
        (new CertificateLoader())->load($tc['p12'], 'contraseña-incorrecta');
    }

    public function test_cli_fallback_loads_without_writing_key_to_disk(): void
    {
        $loader = new CertificateLoader();
        if (!$loader->hasOpensslBinary()) {
            $this->markTestSkipped('No hay binario openssl disponible para probar el fallback CLI.');
        }

        $tc = TestCertificate::modernP12();

        // Contar archivos temporales antes/después: la clave NUNCA debe quedar en disco.
        $pattern = sys_get_temp_dir() . '/sri_p12*';
        $before = glob($pattern) ?: [];

        // Forzar el camino CLI directamente (M-1 stdin / M-2 stdout).
        $cert = $loader->loadViaOpensslCli($tc['p12'], $tc['password']);

        $this->assertInstanceOf(Certificate::class, $cert);
        $this->assertStringContainsString('PRIVATE KEY', $cert->privateKeyPem);

        $after = glob($pattern) ?: [];
        $this->assertSame($before, $after, 'No deben quedar temporales del certificado tras la carga CLI');
    }

    public function test_cli_fallback_rejects_wrong_password(): void
    {
        $loader = new CertificateLoader();
        if (!$loader->hasOpensslBinary()) {
            $this->markTestSkipped('No hay binario openssl disponible.');
        }
        $tc = TestCertificate::modernP12();

        $this->expectException(CertificateException::class);
        $loader->loadViaOpensslCli($tc['p12'], 'mala');
    }
}
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `./vendor/bin/phpunit tests/Unit/Signing/CertificateLoaderTest.php`
Expected: FAIL con `Class "Teran\Sri\Signing\CertificateLoader" not found`.

- [ ] **Step 3: Implementar `CertificateLoader`**

```php
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
```

> Nota de seguridad: el `.p12` que se escribe al temporal está **cifrado** (es el archivo del usuario); la clave privada descifrada solo existe en memoria (`$stdout`). Esto cumple M-2. La contraseña va por stdin, cumpliendo M-1.

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `./vendor/bin/phpunit tests/Unit/Signing/CertificateLoaderTest.php`
Expected: PASS (4 tests; los dos de CLI pueden marcarse *skipped* si no hay binario openssl, pero en este entorno hay).

- [ ] **Step 5: Correr la suite completa**

Run: `./vendor/bin/phpunit`
Expected: verde, sin regresiones, 1 skip pre-existente (+ posibles skips de CLI solo si no hay openssl).

- [ ] **Step 6: Commit**

```bash
git add src/Signing/CertificateLoader.php tests/Unit/Signing/CertificateLoaderTest.php
git commit -m "feat: add secure CertificateLoader (M-1 stdin password, M-2 key off disk)"
```

---

## Self-Review

**1. Cobertura (spec §4.3 + auditoría M-1/M-2):**
- Carga nativa + fallback CLI → Task 4. ✅
- **M-1** password por stdin (proc_open, no argv) → `runOpenssl`. ✅
- **M-2** clave descifrada por stdout, nunca a disco; `.p12` cifrado temporal borrado en `finally` → `loadViaOpensslCli`. ✅
- Sin inyección de comandos (proc_open con array, sin shell) → ✅.
- Value object `Certificate` + `CertificateException` tipada → Tasks 1-2. ✅

**2. Placeholders:** ninguno; código completo. Los tests CLI se auto-skipean si no hay binario openssl (portabilidad), comportamiento explícito.

**3. Consistencia de tipos:** `CertificateLoader::load(string,string): Certificate`, `loadViaOpensslCli(...)`, `hasOpensslBinary(): bool`; `Certificate(certPem, privateKeyPem, extraCerts)`; `CertificateException extends SriException`. ✅

**4. Aditivo:** solo `src/Signing/`, `src/Exceptions/CertificateException.php`, `tests/...`; no se toca `src/Signature/XadesSignature.php` (1.x). ✅

**5. Pendiente para Fase 1.4:** `XadesSigner` consumirá `Certificate` para producir el XML firmado (reusando la lógica XAdES probada del 1.x), con `Clock` inyectable y `Description` configurable (sin el branding ecuanexus), y un golden test de firma byte-estable.
