<?php

declare(strict_types=1);

namespace Teran\Sri\Soap;

use Teran\Sri\Exceptions\CommunicationException;
use SoapClient;
use SoapFault;

class SriSoapClient
{
    private array $urls = [
        'recepcion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
        ],
        'autorizacion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ];

    private int $timeout = 30;
    private int $retries = 3;

    public function __construct(int $timeout = 30, int $retries = 3)
    {
        $this->timeout = $timeout;
        $this->retries = $retries;
    }


    public function enviar(string $xml, string $ambiente = 'pruebas'): object
    {
        $wsdl = $this->urls['recepcion'][$ambiente];
        // PHP's SoapClient automatically encodes to Base64
        return $this->call('validarComprobante', ['xml' => $xml], $wsdl);
    }

    public function autorizar(string $claveAcceso, string $ambiente = 'pruebas'): object
    {
        $wsdl = $this->urls['autorizacion'][$ambiente];
        return $this->call('autorizacionComprobante', ['claveAccesoComprobante' => $claveAcceso], $wsdl);
    }

    private function call(string $method, array $params, string $wsdl): object
    {
        $attempt = 0;
        while ($attempt < $this->retries) {
            try {
                $client = new SoapClient($wsdl, [
                    'connection_timeout' => $this->timeout,
                    'trace' => true,
                    'exceptions' => true,
                ]);

                return $client->__soapCall($method, [$params]);
            } catch (SoapFault $e) {
                $attempt++;
                if ($attempt >= $this->retries) {
                    throw new CommunicationException("Error de comunicación SRI después de {$this->retries} intentos: " . $e->getMessage());
                }
                usleep(500000); // Wait 0.5s between retries
            }
        }
        
        throw new CommunicationException("Falla desconocida en la comunicación con el SRI.");
    }
}
