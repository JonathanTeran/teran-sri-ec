<?php

declare(strict_types=1);

namespace Teran\Sri\Utils;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

trait LoggerTrait
{
    protected LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }
}
