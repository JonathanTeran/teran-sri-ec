<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

use Teran\Sri\Emission\Message;

/**
 * Entidad inmutable de un comprobante en el flujo masivo. Las transiciones
 * devuelven NUEVAS instancias (no mutan), lo que hace el estado seguro y testeable.
 */
final class BatchItem
{
    /** @param Message[] $messages */
    public function __construct(
        public readonly string $claveAcceso,
        public readonly string $signedXml,
        public readonly ComprobanteState $state = ComprobanteState::Pending,
        public readonly int $attempts = 0,
        public readonly ?string $numeroAutorizacion = null,
        public readonly ?string $authorizedXml = null,
        public readonly array $messages = [],
    ) {
    }

    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
    }

    /** @param Message[] $messages */
    private function with(ComprobanteState $state, array $messages, int $attempts, ?string $num = null, ?string $xml = null): self
    {
        return new self($this->claveAcceso, $this->signedXml, $state, $attempts, $num ?? $this->numeroAutorizacion, $xml ?? $this->authorizedXml, $messages);
    }

    /** @param Message[] $messages */
    public function markSent(array $messages = []): self
    {
        return $this->with(ComprobanteState::Sent, $messages, $this->attempts);
    }

    /** @param Message[] $messages */
    public function markAuthorized(?string $numeroAutorizacion, ?string $authorizedXml, array $messages): self
    {
        return $this->with(ComprobanteState::Authorized, $messages, $this->attempts, $numeroAutorizacion, $authorizedXml);
    }

    /** @param Message[] $messages */
    public function markRejected(array $messages): self
    {
        return $this->with(ComprobanteState::Rejected, $messages, $this->attempts);
    }

    /** @param Message[] $messages */
    public function markInProcess(array $messages): self
    {
        return $this->with(ComprobanteState::InProcess, $messages, $this->attempts + 1);
    }

    /** @param Message[] $messages */
    public function markFailed(array $messages): self
    {
        return $this->with(ComprobanteState::Failed, $messages, $this->attempts);
    }

    public function incrementAttempts(): self
    {
        return $this->with($this->state, $this->messages, $this->attempts + 1);
    }
}
