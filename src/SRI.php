<?php

declare(strict_types=1);

namespace Teran\Sri;

use Teran\Sri\Utils\ClaveAcceso;
use Teran\Sri\Schema\XsdValidator;
use Teran\Sri\Signature\XadesSignature;
use Teran\Sri\Soap\SriSoapClient;
use Teran\Sri\Strategies\ComprobanteInterface;
use Teran\Sri\Exceptions\SriException;
use Teran\Sri\Exceptions\ValidationException;

class SRI
{
    use Utils\LoggerTrait;

    public function facturaFromArray(array $data): array
    {
        $generator = new Generators\FacturaGenerator();
        $xml = $generator->generate($data);

        $comprobante = new Strategies\GeneratedComprobante(
            '01',
            $xml,
            __DIR__ . '/../resources/xsd/factura_v2.1.0.xsd',
            [
                'fecha' => str_replace('/', '', $data['infoFactura']['fechaEmision']),
                'ruc' => $data['infoTributaria']['ruc'],
                'serie' => $data['infoTributaria']['estab'] . $data['infoTributaria']['ptoEmi'],
                'numero' => $data['infoTributaria']['secuencial'],
                'codigoNum' => substr($data['infoTributaria']['claveAcceso'], -9, 8) // Extract from manual key or generate
            ]
        );

        return $this->procesar($comprobante);
    }

    private Soap\SriSoapClient $soapClient;
    private ?string $p12Content = null;
    private ?string $p12Password = null;
    private string $ambiente = 'pruebas';

    public function __construct(string $ambiente = 'pruebas')
    {
        $this->ambiente = $ambiente;
        $this->soapClient = new SriSoapClient();
    }

    public function setFirma(string $p12Content, string $password): self
    {
        $this->p12Content = $p12Content;
        $this->p12Password = $password;
        return $this;
    }

    /**
     * Proceso completo: Validar -> Firmar -> Enviar -> Autorizar
     */
    public function procesar(ComprobanteInterface $comprobante): array
    {
        if (!$this->p12Content || !$this->p12Password) {
            throw new SriException("Se requiere configurar la firma digital antes de procesar.");
        }

        $datosClave = $comprobante->getDatosClave();
        
        // 1. Validaciones de Negocio Locales
        Schema\BusinessValidator::validarRuc($datosClave['ruc']);
        Schema\BusinessValidator::validarCampos($datosClave);

        // 2. Generar XML base
        $xml = $comprobante->generarXml();

        // 3. Validar Localmente (XSD)
        XsdValidator::validate($xml, $comprobante->getXsdPath());

        // 4. Firmar Digitalmente (XAdES-BES Elite)
        $signer = new XadesSignature($this->p12Content, $this->p12Password);
        $xmlFirmado = $signer->sign($xml);

        // 5. Enviar al SRI
        $xmlBase64 = base64_encode($xmlFirmado);
        $soapRecepcion = $this->soapClient->enviar($xmlBase64, $this->ambiente);
        $respuestaRecepcion = Dto\RecepcionResponse::fromSoap($soapRecepcion);

        if ($respuestaRecepcion->estado === 'DEVUELTA') {
            throw new ValidationException(
                "El SRI devolvió el comprobante.", 
                array_map(fn($m) => $m->mensaje, $respuestaRecepcion->mensajes)
            );
        }

        // 6. Autorizar
        $claveAcceso = Utils\ClaveAcceso::generar(
            $datosClave['fecha'],
            $comprobante->getTipo(),
            $datosClave['ruc'],
            $this->ambiente === 'pruebas' ? '1' : '2',
            $datosClave['serie'],
            $datosClave['numero'],
            $datosClave['codigoNum']
        );

        $soapAutorizacion = $this->soapClient->autorizar($claveAcceso, $this->ambiente);
        $respuestaAutorizacion = Dto\AutorizacionResponse::fromSoap($soapAutorizacion);

        return [
            'claveAcceso' => $claveAcceso,
            'recepcion' => $respuestaRecepcion,
            'autorizacion' => $respuestaAutorizacion,
            'xmlFirmado' => $xmlFirmado
        ];
    }
}
