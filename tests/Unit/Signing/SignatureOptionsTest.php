<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\SignatureOptions;
use Teran\Sri\Exceptions\SignatureException;

class SignatureOptionsTest extends TestCase
{
    public function test_defaults_are_sri_compatible_and_generic(): void
    {
        $o = new SignatureOptions();
        $this->assertSame('sha1', $o->digestAlgorithm); // SRI requiere SHA-1
        $this->assertStringNotContainsStringIgnoringCase('ecuanexus', $o->description);
        $this->assertStringNotContainsStringIgnoringCase('ecuafact', $o->description);
        $this->assertNotSame('', $o->description);
    }

    public function test_rejects_unsupported_digest(): void
    {
        $this->expectException(SignatureException::class);
        new SignatureOptions(digestAlgorithm: 'md5');
    }
}
