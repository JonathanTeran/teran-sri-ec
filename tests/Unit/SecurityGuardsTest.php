<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guardas de seguridad: previenen la reintroducción de los problemas
 * corregidos en 1.1.1 (acoplamiento a Laravel, fuga de datos, TLS inseguro,
 * temporales predecibles del certificado).
 */
class SecurityGuardsTest extends TestCase
{
    /** @return string[] */
    private function sourceFiles(): array
    {
        $dir = __DIR__ . '/../../src';
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    public function test_core_has_no_laravel_framework_coupling(): void
    {
        $forbidden = ['Illuminate\\', 'base_path(', 'storage_path(', 'app_path(', 'public_path('];
        foreach ($this->sourceFiles() as $file) {
            $code = file_get_contents($file);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    "El núcleo debe ser agnóstico de framework: '$needle' encontrado en " . basename($file)
                );
            }
        }
    }

    public function test_no_tls_verification_disabled(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $code = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/CURLOPT_SSL_VERIFYPEER\s*,\s*false/i',
                $code,
                'No debe deshabilitarse la verificación TLS del peer en ' . basename($file)
            );
            $this->assertDoesNotMatchRegularExpression(
                '/CURLOPT_SSL_VERIFYHOST\s*,\s*(0|false)/i',
                $code,
                'No debe deshabilitarse la verificación TLS del host en ' . basename($file)
            );
        }
    }

    public function test_no_debug_dump_of_signed_documents(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $code = file_get_contents($file);
            $this->assertStringNotContainsString(
                'final_signed',
                $code,
                'No debe escribirse el XML firmado a disco como debug en ' . basename($file)
            );
        }
    }

    public function test_certificate_temp_uses_unpredictable_names(): void
    {
        $code = file_get_contents(__DIR__ . '/../../src/Signature/XadesSignature.php');
        $this->assertStringNotContainsString(
            'uniqid()',
            $code,
            'El temporal del certificado no debe usar uniqid() (predecible); usar tempnam()'
        );
    }
}
