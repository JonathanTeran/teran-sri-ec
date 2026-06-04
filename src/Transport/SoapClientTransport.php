<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Emission\Message;
use Teran\Sri\Exceptions\CommunicationException;
use SoapClient;
use SoapFault;

final class SoapClientTransport implements SriTransportInterface
{
    private const WSDL = [
        'recepcion' => [
            'pruebas'   => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
        ],
        'autorizacion' => [
            'pruebas'   => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ];

    /** @var (callable(string,array<string,mixed>,string):object)|null */
    private $soapCaller;

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        ?callable $soapCaller = null,
    ) {
        $this->soapCaller = $soapCaller;
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $resp = $this->call('validarComprobante', ['xml' => $signedXml], self::WSDL['recepcion'][$this->key($ambiente)]);
        return $this->parseReception($resp);
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $resp = $this->call('autorizacionComprobante', ['claveAccesoComprobante' => $claveAcceso], self::WSDL['autorizacion'][$this->key($ambiente)]);
        return $this->parseAuthorization($resp);
    }

    private function key(Ambiente $a): string
    {
        return $a === Ambiente::Produccion ? 'produccion' : 'pruebas';
    }

    /**
     * @param array<string,mixed> $params
     */
    private function call(string $method, array $params, string $wsdl): object
    {
        if ($this->soapCaller !== null) {
            return ($this->soapCaller)($method, $params, $wsdl);
        }

        $attempt = 0;
        while ($attempt < $this->retries) {
            try {
                $client = new SoapClient($wsdl, [
                    'connection_timeout' => $this->timeout,
                    'trace'              => true,
                    'exceptions'         => true,
                ]);
                return $client->__soapCall($method, [$params]);
            } catch (SoapFault $e) {
                if (++$attempt >= $this->retries) {
                    throw new CommunicationException(
                        "Error de comunicación SRI tras {$this->retries} intentos: " . $e->getMessage()
                    );
                }
                usleep(500000);
            }
        }

        throw new CommunicationException('Falla desconocida en la comunicación con el SRI.');
    }

    private function parseReception(object $data): ReceptionOutcome
    {
        $root   = $data->RespuestaRecepcionComprobante ?? $data;
        $estado = isset($root->estado) ? (string) $root->estado : 'DEVUELTA';

        $mensajes = [];
        if (isset($root->comprobantes->comprobante->mensajes->mensaje)) {
            $raw = $root->comprobantes->comprobante->mensajes->mensaje;
            foreach (is_array($raw) ? $raw : [$raw] as $m) {
                $mensajes[] = $this->message($m);
            }
        }

        // Código 70 / PROCESAMIENTO se trata como RECIBIDA (no es rechazo), igual que 1.x.
        if ($estado === 'DEVUELTA') {
            foreach ($mensajes as $m) {
                if ($m->identificador === '70' || stripos($m->mensaje, 'PROCESAMIENTO') !== false) {
                    $estado = 'RECIBIDA';
                    break;
                }
            }
        }

        return new ReceptionOutcome($estado, $mensajes);
    }

    private function parseAuthorization(object $data): AuthorizationOutcome
    {
        $a = null;
        if (isset($data->autorizaciones->autorizacion)) {
            $raw = $data->autorizaciones->autorizacion;
            $a   = is_array($raw) ? $raw[0] : $raw;
        }

        if ($a === null) {
            return new AuthorizationOutcome('NO AUTORIZADO');
        }

        $mensajes = [];
        if (isset($a->mensajes->mensaje)) {
            $raw = $a->mensajes->mensaje;
            foreach (is_array($raw) ? $raw : [$raw] as $m) {
                $mensajes[] = $this->message($m);
            }
        }

        return new AuthorizationOutcome(
            estado:             (string) ($a->estado ?? 'NO AUTORIZADO'),
            numeroAutorizacion: isset($a->numeroAutorizacion) ? (string) $a->numeroAutorizacion : null,
            fechaAutorizacion:  isset($a->fechaAutorizacion)  ? (string) $a->fechaAutorizacion  : null,
            comprobante:        isset($a->comprobante)        ? (string) $a->comprobante        : null,
            mensajes:           $mensajes,
        );
    }

    private function message(object $m): Message
    {
        return new Message(
            identificador:      isset($m->identificador)      ? (string) $m->identificador      : '',
            mensaje:            isset($m->mensaje)            ? (string) $m->mensaje            : '',
            tipo:               isset($m->tipo)               ? (string) $m->tipo               : '',
            informacionAdicional: isset($m->informacionAdicional) ? (string) $m->informacionAdicional : '',
        );
    }
}
