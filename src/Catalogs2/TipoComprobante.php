<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs2;

enum TipoComprobante: string
{
    case Factura = '01';
    case NotaCredito = '04';
    case NotaDebito = '05';
    case GuiaRemision = '06';
    case Retencion = '07';
}
