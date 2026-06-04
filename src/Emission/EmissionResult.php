<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

/**
 * Resultado inmutable de una emisión. Implementa ArrayAccess para que el código
 * 1.x (`$resultado['claveAcceso']`, `$resultado['xmlFirmado']`) siga funcionando.
 *
 * @implements \ArrayAccess<string,mixed>
 */
final class EmissionResult implements \ArrayAccess
{
    /** @param Message[] $messages */
    public function __construct(
        public readonly EmissionStatus $status,
        public readonly string $claveAcceso,
        public readonly string $signedXml,
        public readonly ?string $numeroAutorizacion = null,
        public readonly ?string $fechaAutorizacion = null,
        public readonly ?string $authorizedXml = null,
        public readonly array $messages = [],
    ) {
    }

    /** @return array<string,mixed> mapa de llaves legacy 1.x → valores */
    private function legacyMap(): array
    {
        return [
            'claveAcceso' => $this->claveAcceso,
            'xmlFirmado' => $this->signedXml,
            'numeroAutorizacion' => $this->numeroAutorizacion,
            'fechaAutorizacion' => $this->fechaAutorizacion,
            'estado' => $this->status->value,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->legacyMap());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->legacyMap()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('EmissionResult es inmutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('EmissionResult es inmutable.');
    }
}
