<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Psr18SoapTransport implements SriTransportInterface
{
    private const ENDPOINTS = [
        'recepcion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
        ],
        'autorizacion' => [
            'pruebas' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
            'produccion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
        ],
    ];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly SoapEnvelopeBuilder $envelopes = new SoapEnvelopeBuilder(),
        private readonly SoapResponseParser $parser = new SoapResponseParser(),
    ) {
    }

    public function enviar(string $signedXml, Ambiente $ambiente): ReceptionOutcome
    {
        $body = $this->post(
            self::ENDPOINTS['recepcion'][$this->ambienteKey($ambiente)],
            $this->envelopes->reception($signedXml),
        );
        return $this->parser->parseReception($body);
    }

    public function autorizar(string $claveAcceso, Ambiente $ambiente): AuthorizationOutcome
    {
        $body = $this->post(
            self::ENDPOINTS['autorizacion'][$this->ambienteKey($ambiente)],
            $this->envelopes->authorization($claveAcceso),
        );
        return $this->parser->parseAuthorization($body);
    }

    private function ambienteKey(Ambiente $ambiente): string
    {
        return $ambiente === Ambiente::Produccion ? 'produccion' : 'pruebas';
    }

    private function post(string $url, string $soapBody): string
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withHeader('SOAPAction', '')
            ->withBody($this->streamFactory->createStream($soapBody));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new CommunicationException('Error de comunicación con el SRI: ' . $e->getMessage());
        }

        if ($response->getStatusCode() >= 400) {
            throw new CommunicationException('El SRI respondió HTTP ' . $response->getStatusCode());
        }

        return (string) $response->getBody();
    }
}
