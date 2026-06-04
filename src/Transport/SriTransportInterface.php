<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;

interface SriTransportInterface
{
    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome;

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome;
}
