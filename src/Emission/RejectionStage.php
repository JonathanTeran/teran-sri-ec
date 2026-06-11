<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

/**
 * Etapa en la que el SRI rechazó el comprobante (cuando status = Rejected).
 *
 * El SRI distingue dos rechazos con consecuencias legales distintas:
 *  - Recepcion: el comprobante fue DEVUELTO en recepción (XML/estructura/clave
 *    inválidos) — nunca entró al sistema; se puede corregir y re-emitir con la
 *    misma clave de acceso.
 *  - Autorizacion: recibido pero NO AUTORIZADO (regla de negocio/duplicado) —
 *    queda registrado en el SRI; el tratamiento legal es distinto.
 *
 * v2.0 colapsaba ambos en `EmissionStatus::Rejected`; desde v2.1
 * `EmissionResult::$rejectedStage` expone la etapa (null si no hubo rechazo).
 */
enum RejectionStage: string
{
    case Recepcion = 'recepcion';
    case Autorizacion = 'autorizacion';
}
