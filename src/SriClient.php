<?php

declare(strict_types=1);

namespace Teran\Sri;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Signing\Certificate;
use Teran\Sri\Signing\XadesSigner;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Transport\SriTransportInterface;
use Teran\Sri\Transport\SoapClientTransport;
use Teran\Sri\Emission\EmissionResult;
use Teran\Sri\Emission\EmissionStatus;
use Teran\Sri\Emission\RejectionStage;

/**
 * Entrada del 2.0 para emisión individual. Orquesta:
 * serializar → firmar → enviar (recepción) → autorizar.
 */
final class SriClient
{
    public function __construct(
        private readonly Ambiente $ambiente,
        private readonly Certificate $certificate,
        private readonly SriTransportInterface $transport,
        private readonly XadesSigner $signer = new XadesSigner(),
        private readonly FacturaXmlSerializer $facturaSerializer = new FacturaXmlSerializer(),
    ) {
    }

    /**
     * Crea un SriClient listo para usar con transporte zero-config (ext-soap).
     *
     * @param SriTransportInterface|null $transport Transporte explícito; por defecto SoapClientTransport.
     */
    public static function create(
        Ambiente $ambiente,
        Certificate $certificate,
        ?SriTransportInterface $transport = null,
    ): self {
        return new self(
            ambiente:     $ambiente,
            certificate:  $certificate,
            transport:    $transport ?? new SoapClientTransport(),
        );
    }

    /**
     * Emite un comprobante electrónico al SRI (recepción + autorización).
     *
     * Actualmente soporta únicamente {@see Factura}. La emisión de
     * NotaCredito, NotaDebito, Guia de Remisión y Retención se añadirá en
     * una fase posterior mediante una ruta de serialización agnóstica al tipo
     * de documento.
     *
     * @param Factura     $factura      Documento a emitir.
     * @param string      $claveAcceso  Clave de acceso de 49 dígitos ya calculada.
     * @return EmissionResult           Resultado inmutable con estado y mensajes del SRI.
     */
    public function emit(Factura $factura, string $claveAcceso): EmissionResult
    {
        $xml = $this->facturaSerializer->serialize($factura, $claveAcceso);
        $signed = $this->signer->sign($xml, $this->certificate);

        $reception = $this->transport->enviar($signed, $this->ambiente);
        if ($reception->estado !== 'RECIBIDA') {
            return new EmissionResult(
                status: EmissionStatus::Rejected,
                claveAcceso: $claveAcceso,
                signedXml: $signed,
                messages: $reception->mensajes,
                rejectedStage: RejectionStage::Recepcion,
            );
        }

        $auth = $this->transport->autorizar($claveAcceso, $this->ambiente);
        $status = match (strtoupper($auth->estado)) {
            'AUTORIZADO' => EmissionStatus::Authorized,
            'EN PROCESO', 'EN PROCESAMIENTO' => EmissionStatus::InProcess,
            default => EmissionStatus::Rejected,
        };

        return new EmissionResult(
            status: $status,
            claveAcceso: $claveAcceso,
            signedXml: $signed,
            numeroAutorizacion: $auth->numeroAutorizacion,
            fechaAutorizacion: $auth->fechaAutorizacion,
            authorizedXml: $auth->comprobante,
            messages: $auth->mensajes,
            rejectedStage: $status === EmissionStatus::Rejected ? RejectionStage::Autorizacion : null,
        );
    }
}
