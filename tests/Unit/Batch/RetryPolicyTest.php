<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Batch;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Batch\RetryPolicy;

class RetryPolicyTest extends TestCase
{
    public function test_allows_attempts_up_to_max(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);
        $this->assertTrue($policy->shouldRetry(1));
        $this->assertTrue($policy->shouldRetry(2));
        $this->assertFalse($policy->shouldRetry(3));
        $this->assertFalse($policy->shouldRetry(4));
    }

    public function test_backoff_grows(): void
    {
        $policy = new RetryPolicy(baseDelaySeconds: 2);
        $this->assertSame(2, $policy->delaySeconds(1));
        $this->assertSame(4, $policy->delaySeconds(2));
        $this->assertSame(8, $policy->delaySeconds(3));
    }
}
