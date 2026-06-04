<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapResponseParser;
use Teran\Sri\Exceptions\CommunicationException;

class SoapResponseParserTest extends TestCase
{
    public function test_parses_recibida(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:validarComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.recepcion">
              <RespuestaRecepcionComprobante>
                <estado>RECIBIDA</estado>
                <comprobantes/>
              </RespuestaRecepcionComprobante>
            </ns2:validarComprobanteResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

        $o = (new SoapResponseParser())->parseReception($xml);
        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame([], $o->mensajes);
    }

    public function test_parses_devuelta_with_messages(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <validarComprobanteResponse>
              <RespuestaRecepcionComprobante>
                <estado>DEVUELTA</estado>
                <comprobantes>
                  <comprobante>
                    <claveAcceso>2601...819</claveAcceso>
                    <mensajes>
                      <mensaje>
                        <identificador>43</identificador>
                        <mensaje>RUC del emisor no existe</mensaje>
                        <tipo>ERROR</tipo>
                        <informacionAdicional>1790011001001</informacionAdicional>
                      </mensaje>
                    </mensajes>
                  </comprobante>
                </comprobantes>
              </RespuestaRecepcionComprobante>
            </validarComprobanteResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

        $o = (new SoapResponseParser())->parseReception($xml);
        $this->assertSame('DEVUELTA', $o->estado);
        $this->assertCount(1, $o->mensajes);
        $this->assertSame('43', $o->mensajes[0]->identificador);
        $this->assertSame('ERROR', $o->mensajes[0]->tipo);
    }

    public function test_soap_fault_throws_communication_exception(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <soap:Fault>
              <faultcode>soap:Server</faultcode>
              <faultstring>Error de esquema</faultstring>
            </soap:Fault>
          </soap:Body>
        </soap:Envelope>
        XML;

        $parser = new SoapResponseParser();

        $this->expectException(CommunicationException::class);
        $this->expectExceptionMessage('SRI SOAP Fault: Error de esquema');
        $parser->parseReception($xml);
    }

    public function test_soap_fault_throws_communication_exception_on_authorization(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <soap:Fault>
              <faultcode>soap:Server</faultcode>
              <faultstring>Servicio no disponible</faultstring>
            </soap:Fault>
          </soap:Body>
        </soap:Envelope>
        XML;

        $parser = new SoapResponseParser();

        $this->expectException(CommunicationException::class);
        $this->expectExceptionMessage('SRI SOAP Fault: Servicio no disponible');
        $parser->parseAuthorization($xml);
    }

    public function test_parses_autorizado(): void
    {
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:autorizacionComprobanteResponse xmlns:ns2="http://ec.gob.sri.ws.autorizacion">
              <RespuestaAutorizacionComprobante>
                <autorizaciones>
                  <autorizacion>
                    <estado>AUTORIZADO</estado>
                    <numeroAutorizacion>2601202601179001100112345678</numeroAutorizacion>
                    <fechaAutorizacion>2026-01-26T10:00:00-05:00</fechaAutorizacion>
                    <comprobante>&lt;factura/&gt;</comprobante>
                    <mensajes/>
                  </autorizacion>
                </autorizaciones>
              </RespuestaAutorizacionComprobante>
            </ns2:autorizacionComprobanteResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

        $o = (new SoapResponseParser())->parseAuthorization($xml);
        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('2601202601179001100112345678', $o->numeroAutorizacion);
        $this->assertSame('<factura/>', $o->comprobante);
    }
}
