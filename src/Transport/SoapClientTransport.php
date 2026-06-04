<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;
use SoapClient;
use SoapFault;

/**
 * Transporte basado en ext-soap (SoapClient), portado del 1.x. Funciona out-of-the-box
 * (ext-soap es requisito del paquete) sin necesidad de un cliente PSR-18 externo.
 * La llamada SOAP real se aísla en $soapCaller para poder testear sin red.
 *
 * Nota: el método realSoapCall() (camino real hacia el SRI) no está cubierto por tests
 * unitarios porque requiere el servicio en vivo del SRI; valídalo con un smoke test
 * contra el ambiente de pruebas del SRI antes de pasar a producción.
 */
final class SoapClientTransport implements SriTransportInterface
{
    private const WSDL = [
        'recepcion' => [
            'pruebas'    => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
        ],
        'autorizacion' => [
            'pruebas'    => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ];

    /** @var callable(string,array<string,mixed>,string):object */
    private $soapCaller;

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        private readonly SoapStdClassParser $parser = new SoapStdClassParser(),
        ?callable $soapCaller = null,
    ) {
        $this->soapCaller = $soapCaller ?? fn (string $method, array $params, string $wsdl): object
            => $this->realSoapCall($method, $params, $wsdl);
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $wsdl = self::WSDL['recepcion'][$this->key($ambiente)];
        $resp = $this->call('validarComprobante', ['xml' => $signedXml], $wsdl);
        $outcome = $this->parser->reception($resp);

        // Código 70 / PROCESAMIENTO se trata como RECIBIDA (no es rechazo), igual que 1.x.
        if ($outcome->estado === 'DEVUELTA') {
            foreach ($outcome->mensajes as $m) {
                if ($m->identificador === '70' || stripos($m->mensaje, 'PROCESAMIENTO') !== false) {
                    return new ReceptionOutcome('RECIBIDA', $outcome->mensajes);
                }
            }
        }

        return $outcome;
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $wsdl = self::WSDL['autorizacion'][$this->key($ambiente)];
        $resp = $this->call('autorizacionComprobante', ['claveAccesoComprobante' => $claveAcceso], $wsdl);
        return $this->parser->authorization($resp);
    }

    private function key(Ambiente $ambiente): string
    {
        return $ambiente === Ambiente::Produccion ? 'produccion' : 'pruebas';
    }

    /**
     * @param array<string,mixed> $params
     */
    private function call(string $method, array $params, string $wsdl): object
    {
        $attempt = 0;
        while (true) {
            try {
                return ($this->soapCaller)($method, $params, $wsdl);
            } catch (SoapFault $e) {
                if (++$attempt >= $this->retries) {
                    throw new CommunicationException(
                        "Error de comunicación SRI tras {$this->retries} intentos: " . $e->getMessage()
                    );
                }
                usleep(500000);
            }
        }
    }

    private function realSoapCall(string $method, array $params, string $wsdl): object
    {
        $client = new SoapClient($wsdl, [
            'connection_timeout' => $this->timeout,
            'trace'              => true,
            'exceptions'         => true,
        ]);
        /** @var object $result */
        $result = $client->__soapCall($method, [$params]);
        return $result;
    }
}
