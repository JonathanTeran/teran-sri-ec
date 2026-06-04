<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

enum ComprobanteState: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Authorized = 'AUTHORIZED';
    case Rejected = 'REJECTED';
    case InProcess = 'IN_PROCESS';
    case Failed = 'FAILED';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Authorized, self::Rejected, self::Failed], true);
    }
}
