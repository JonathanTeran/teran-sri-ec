<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs2;

enum FormaPago: string
{
    case SinUtilizacionSistemaFinanciero = '01';
    case CompensacionDeudas = '15';
    case TarjetaDebito = '16';
    case DineroElectronico = '17';
    case TarjetaPrepago = '18';
    case TarjetaCredito = '19';
    case OtrosConUtilizacionSistemaFinanciero = '20';
    case EndosoTitulos = '21';
}
