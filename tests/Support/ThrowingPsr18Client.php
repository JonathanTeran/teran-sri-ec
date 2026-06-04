<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client stub that always throws a ClientExceptionInterface.
 */
final class ThrowingPsr18Client implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new class ('Simulated network error') extends \RuntimeException implements ClientExceptionInterface {};
    }
}
