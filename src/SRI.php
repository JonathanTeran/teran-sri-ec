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

    // Códigos de tipo de documento según SRI
    public const TIPO_FACTURA = '01';
    public const TIPO_NOTA_CREDITO = '04';
    public const TIPO_NOTA_DEBITO = '05';
    public const TIPO_GUIA_REMISION = '06';
    public const TIPO_RETENCION = '07';

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

    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    /**
     * Procesa una factura desde un array de datos.
     */
    public function facturaFromArray(array $data): array
    {
        return $this->procesarComprobante(
            $data,
            self::TIPO_FACTURA,
            'infoFactura',
            Generators\FacturaGenerator::class,
            'factura_v2.1.0.xsd'
        );
    }

    /**
     * Procesa una nota de crédito desde un array de datos.
     */
    public function notaCreditoFromArray(array $data): array
    {
        return $this->procesarComprobante(
            $data,
            self::TIPO_NOTA_CREDITO,
            'infoNotaCredito',
            Generators\NotaCreditoGenerator::class,
            'notaCredito_v1.1.0.xsd'
        );
    }

    /**
     * Procesa una nota de débito desde un array de datos.
     */
    public function notaDebitoFromArray(array $data): array
    {
        return $this->procesarComprobante(
            $data,
            self::TIPO_NOTA_DEBITO,
            'infoNotaDebito',
            Generators\NotaDebitoGenerator::class,
            'notaDebito_v1.0.0.xsd'
        );
    }

    /**
     * Procesa una guía de remisión desde un array de datos.
     */
    public function guiaRemisionFromArray(array $data): array
    {
        return $this->procesarComprobante(
            $data,
            self::TIPO_GUIA_REMISION,
            'infoGuiaRemision',
            Generators\GuiaRemisionGenerator::class,
            'guiaRemision_v1.1.0.xsd'
        );
    }

    /**
     * Procesa un comprobante de retención desde un array de datos.
     */
    public function retencionFromArray(array $data): array
    {
        return $this->procesarComprobante(
            $data,
            self::TIPO_RETENCION,
            'infoCompRetencion',
            Generators\RetencionGenerator::class,
            'comprobanteRetencion_v2.0.0.xsd'
        );
    }

    /**
     * Método genérico para procesar cualquier tipo de comprobante.
     */
    private function procesarComprobante(
        array $data,
        string $tipoDoc,
        string $infoKey,
        string $generatorClass,
        string $xsdFile
    ): array {
        // Generar código numérico aleatorio de 8 dígitos si no existe
        $codigoNum = $data['infoTributaria']['codigoNumerico']
            ?? str_pad((string)random_int(1, 99999999), 8, '0', STR_PAD_LEFT);

        // Extraer fecha en formato ddmmyyyy
        $fechaEmision = $data[$infoKey]['fechaEmision'];
        $partesFecha = explode('/', $fechaEmision);
        $fechaClave = $partesFecha[0] . $partesFecha[1] . $partesFecha[2];

        // Generar clave de acceso
        $claveAcceso = Utils\ClaveAcceso::generar(
            $fechaClave,
            $tipoDoc,
            $data['infoTributaria']['ruc'],
            $data['infoTributaria']['ambiente'],
            $data['infoTributaria']['estab'] . $data['infoTributaria']['ptoEmi'],
            $data['infoTributaria']['secuencial'],
            $codigoNum,
            $data['infoTributaria']['tipoEmision'] ?? '1'
        );

        // Inyectar la clave de acceso y código de documento
        $data['infoTributaria']['claveAcceso'] = $claveAcceso;
        $data['infoTributaria']['codDoc'] = $tipoDoc;

        // Generar XML con la clave de acceso incluida
        $generator = new $generatorClass();
        $xml = $generator->generate($data);

        $comprobante = new Strategies\GeneratedComprobante(
            $tipoDoc,
            $xml,
            __DIR__ . '/../resources/xsd/' . $xsdFile,
            [
                'fecha' => $fechaClave,
                'ruc' => $data['infoTributaria']['ruc'],
                'serie' => $data['infoTributaria']['estab'] . $data['infoTributaria']['ptoEmi'],
                'numero' => $data['infoTributaria']['secuencial'],
                'codigoNum' => $codigoNum
            ]
        );

        return $this->procesar($comprobante);
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
        $rucValidator = new Utils\RucValidator();
        if (!$rucValidator->validate($datosClave['ruc'])) {
            throw new ValidationException("El RUC {$datosClave['ruc']} no es válido o no existe en el SRI.");
        }

        Schema\BusinessValidator::validarCampos($datosClave);

        // 2. Generar XML base
        $xml = $comprobante->generarXml();

        // 3. Validar Localmente (XSD) - Solo si existe el archivo
        $xsdPath = $comprobante->getXsdPath();
        if (file_exists($xsdPath)) {
            XsdValidator::validate($xml, $xsdPath);
        }

        // 4. Firmar Digitalmente (XAdES-BES)
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

        // 6. Obtener clave de acceso del XML
        $claveAcceso = Utils\ClaveAcceso::generar(
            $datosClave['fecha'],
            $comprobante->getTipo(),
            $datosClave['ruc'],
            $this->ambiente === 'pruebas' ? '1' : '2',
            $datosClave['serie'],
            $datosClave['numero'],
            $datosClave['codigoNum']
        );

        // 7. Consultar autorización
        $soapAutorizacion = $this->soapClient->autorizar($claveAcceso, $this->ambiente);
        $respuestaAutorizacion = Dto\AutorizacionResponse::fromSoap($soapAutorizacion);

        return [
            'claveAcceso' => $claveAcceso,
            'recepcion' => $respuestaRecepcion,
            'autorizacion' => $respuestaAutorizacion,
            'xmlFirmado' => $xmlFirmado
        ];
    }

    /**
     * Consulta el estado de autorización de un comprobante por su clave de acceso.
     */
    public function consultarAutorizacion(string $claveAcceso): Dto\AutorizacionResponse
    {
        $soapAutorizacion = $this->soapClient->autorizar($claveAcceso, $this->ambiente);
        return Dto\AutorizacionResponse::fromSoap($soapAutorizacion);
    }

    /**
     * Solo firma un XML sin enviarlo al SRI.
     * Útil para generar comprobantes offline.
     */
    public function firmarXml(string $xml): string
    {
        if (!$this->p12Content || !$this->p12Password) {
            throw new SriException("Se requiere configurar la firma digital antes de firmar.");
        }

        $signer = new XadesSignature($this->p12Content, $this->p12Password);
        return $signer->sign($xml);
    }

    /**
     * Valida un XML contra su esquema XSD.
     */
    public function validarXml(string $xml, string $tipoDoc): bool
    {
        $xsdMap = [
            self::TIPO_FACTURA => 'factura_v2.1.0.xsd',
            self::TIPO_NOTA_CREDITO => 'notaCredito_v1.1.0.xsd',
            self::TIPO_NOTA_DEBITO => 'notaDebito_v1.0.0.xsd',
            self::TIPO_GUIA_REMISION => 'guiaRemision_v1.1.0.xsd',
            self::TIPO_RETENCION => 'comprobanteRetencion_v2.0.0.xsd',
        ];

        $xsdPath = __DIR__ . '/../resources/xsd/' . ($xsdMap[$tipoDoc] ?? 'factura_v2.1.0.xsd');

        if (!file_exists($xsdPath)) {
            return true; // Si no existe el XSD, asumimos válido
        }

        return XsdValidator::validate($xml, $xsdPath);
    }
}
