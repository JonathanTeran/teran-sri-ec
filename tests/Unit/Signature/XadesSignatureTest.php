<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signature;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signature\XadesSignature;
use Teran\Sri\Exceptions\SriException;

class XadesSignatureTest extends TestCase
{
    public function test_failed_load_leaves_no_temp_certificate_files(): void
    {
        $pattern = sys_get_temp_dir() . '/sri_p12_*';
        $before = glob($pattern) ?: [];

        try {
            new XadesSignature('not-a-valid-p12-blob', 'wrong-password');
            $this->fail('Debió lanzar SriException al no poder leer el .p12');
        } catch (SriException $e) {
            $this->addToAssertionCount(1);
        }

        $after = glob($pattern) ?: [];
        $this->assertSame(
            $before,
            $after,
            'No deben quedar archivos temporales del certificado (.p12/.pem) tras un fallo de carga'
        );
    }
}
