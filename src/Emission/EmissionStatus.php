<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

/**
 * Estado interno de una emisión.
 *
 * IMPORTANTE: los valores respaldados (backed values) son identificadores
 * INTERNOS de esta librería. NO son los strings que el SRI devuelve en el
 * wire. Por ejemplo, el SRI retorna "EN PROCESO" (con espacio); esta enum
 * usa "EN_PROCESO" (con guion bajo) para evitar colisiones. El método
 * {@see \Teran\Sri\SriClient::emit()} normaliza los valores wire mediante
 * `strtoupper`/`match`; nunca llama a `EmissionStatus::from()` directamente
 * sobre una respuesta cruda del SRI.
 */
enum EmissionStatus: string
{
    case Authorized = 'AUTORIZADO';
    case Rejected = 'RECHAZADO';
    case InProcess = 'EN_PROCESO';
    // Reservado para fallos de transporte (se establecerá en Fase 1.6 al manejar TransportException).
    case Error = 'ERROR';
}
