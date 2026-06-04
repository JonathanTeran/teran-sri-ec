<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Catalogs2\Ambiente;

final class FakeTransport implements SriTransportInterface
{
    public ?string $lastSentXml = null;
    public ?string $lastAuthorizedClave = null;

    public function __construct(
        private readonly ReceptionOutcome $reception,
        private readonly AuthorizationOutcome $authorization,
    ) {
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $this->lastSentXml = $signedXml;
        return $this->reception;
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $this->lastAuthorizedClave = $claveAcceso;
        return $this->authorization;
    }
}
