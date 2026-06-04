<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

enum EmissionStatus: string
{
    case Authorized = 'AUTORIZADO';
    case Rejected = 'RECHAZADO';
    case InProcess = 'EN_PROCESO';
    case Error = 'ERROR';
}
