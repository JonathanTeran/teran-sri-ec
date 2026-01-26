<?php

declare(strict_types=1);

namespace Teran\Sri\Strategies;

class GeneratedComprobante implements ComprobanteInterface
{
    public function __construct(
        private readonly string $tipo,
        private readonly string $xml,
        private readonly string $xsdPath,
        private readonly array $datosClave
    ) {}

    public function getTipo(): string { return $this->tipo; }
    public function generarXml(): string { return $this->xml; }
    public function getXsdPath(): string { return $this->xsdPath; }
    public function getDatosClave(): array { return $this->datosClave; }
}
