<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;

final class ThrowingTransport implements SriTransportInterface
{
    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        throw new CommunicationException('fallo de red simulado');
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        throw new CommunicationException('fallo de red simulado');
    }
}
