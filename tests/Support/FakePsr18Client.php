<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

final class FakePsr18Client implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(private readonly string $responseBody, private readonly int $status = 200)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;
        $factory = new Psr17Factory();
        return $factory->createResponse($this->status)
            ->withBody($factory->createStream($this->responseBody));
    }
}
