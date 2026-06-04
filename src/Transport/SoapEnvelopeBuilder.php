<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

final class SoapEnvelopeBuilder
{
    public function reception(string $signedXml): string
    {
        $b64 = base64_encode($signedXml);
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.recepcion">
           <soapenv:Header/>
           <soapenv:Body>
              <ec:validarComprobante>
                 <xml>{$b64}</xml>
              </ec:validarComprobante>
           </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }

    public function authorization(string $claveAcceso): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.autorizacion">
           <soapenv:Header/>
           <soapenv:Body>
              <ec:autorizacionComprobante>
                 <claveAccesoComprobante>{$claveAcceso}</claveAccesoComprobante>
              </ec:autorizacionComprobante>
           </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }
}
