# Guía de migración a 2.0

La 2.0 introduce una API tipada y agnóstica de framework. **La clase `Teran\Sri\SRI` (1.x) sigue funcionando** sin cambios; migrar a `SriClient` es opcional pero recomendado.

## Equivalencias rápidas

| 1.x | 2.0 |
|---|---|
| `new SRI('pruebas')` | `SriClient::create(Ambiente::Pruebas, $cert)` |
| `$sri->setFirma($p12, $pass)` | `$cert = (new CertificateLoader())->load($p12, $pass)` |
| `$sri->facturaFromArray($data)` | `$client->emit(Factura::fromArray($data), $claveAcceso)` |
| `$result['claveAcceso']` | `$result->claveAcceso` (o `$result['claveAcceso']` por ArrayAccess) |
| `$result['xmlFirmado']` | `$result->signedXml` (o `$result['xmlFirmado']` por ArrayAccess) |
| `$result['autorizacion']->numeroAutorizacion` | `$result->numeroAutorizacion` |
| `$result['autorizacion']->estado` | `$result->status` (`EmissionStatus` enum) |
| `$result['autorizacion']->mensajes` | `$result->messages` |

## Antes / Después — Factura

### 1.x (sigue funcionando)

```php
use Teran\Sri\SRI;

$p12     = file_get_contents('/ruta/firma.p12');
$password = 'mi-password';

$sri = new SRI('pruebas');        // o 'produccion'
$sri->setFirma($p12, $password);

$data = [
    'infoTributaria' => [
        'ambiente'    => '1',
        'razonSocial' => 'EMPRESA S.A.',
        'ruc'         => '1790011001001',
        'estab'       => '001',
        'ptoEmi'      => '001',
        'secuencial'  => '000000001',
        'dirMatriz'   => 'Av. Ejemplo 123, Quito',
    ],
    'infoFactura' => [
        'fechaEmision'                   => '26/01/2026',
        'tipoIdentificacionComprador'    => '05',
        'razonSocialComprador'           => 'CONSUMIDOR FINAL',
        'identificacionComprador'        => '9999999999',
        'totalSinImpuestos'              => '100.00',
        'totalDescuento'                 => '0.00',
        'importeTotal'                   => '115.00',
        'totalConImpuestos'              => [
            ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
        ],
        'pagos' => [
            ['formaPago' => '01', 'total' => '115.00'],
        ],
    ],
    'detalles' => [[
        'codigoPrincipal'        => 'P001',
        'descripcion'            => 'Producto de ejemplo',
        'cantidad'               => '1.00',
        'precioUnitario'         => '100.00',
        'descuento'              => '0.00',
        'precioTotalSinImpuesto' => '100.00',
        'impuestos' => [
            ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
        ],
    ]],
];

$result = $sri->facturaFromArray($data);

// Acceso por clave array:
echo $result['claveAcceso'];                          // '26012026011790011001001100100100000000112345678...'
echo $result['xmlFirmado'];                           // XML firmado
echo $result['autorizacion']->numeroAutorizacion;     // número de autorización SRI
echo $result['autorizacion']->estado;                 // 'AUTORIZADO' | 'NO AUTORIZADO'
```

### 2.0 (API tipada)

```php
use Teran\Sri\SriClient;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Utils\ClaveAcceso;
use Teran\Sri\Emission\EmissionStatus;

// 1. Cargar el certificado de firma.
$p12      = file_get_contents('/ruta/firma.p12');
$password = 'mi-password';
$cert     = (new CertificateLoader())->load($p12, $password);

// 2. Generar la clave de acceso de 49 dígitos.
//    ClaveAcceso::generar(fecha, tipoDoc, ruc, ambiente, serie, numero, codigoNum, tipoEmision)
$claveAcceso = ClaveAcceso::generar(
    fecha:            '26012026',   // ddmmyyyy
    tipoComprobante:  '01',         // 01 = Factura
    ruc:              '1790011001001',
    ambiente:         '1',          // '1' = Pruebas, '2' = Producción
    serie:            '001001',     // estab (3) + ptoEmi (3)
    numero:           '000000001',  // 9 dígitos
    codigoNum:        '12345678',   // 8 dígitos aleatorios o fijos
    tipoEmision:      '1',          // 1 = Normal
);

// 3. Construir el array de la factura (igual que en 1.x).
$data = [
    'infoTributaria' => [
        'ambiente'    => '1',
        'razonSocial' => 'EMPRESA S.A.',
        'ruc'         => '1790011001001',
        'estab'       => '001',
        'ptoEmi'      => '001',
        'secuencial'  => '000000001',
        'dirMatriz'   => 'Av. Ejemplo 123, Quito',
    ],
    'infoFactura' => [
        'fechaEmision'                   => '26/01/2026',
        'tipoIdentificacionComprador'    => '05',
        'razonSocialComprador'           => 'CONSUMIDOR FINAL',
        'identificacionComprador'        => '9999999999',
        'totalSinImpuestos'              => '100.00',
        'totalDescuento'                 => '0.00',
        'importeTotal'                   => '115.00',
        'totalConImpuestos'              => [
            ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
        ],
        'pagos' => [
            ['formaPago' => '01', 'total' => '115.00'],
        ],
    ],
    'detalles' => [[
        'codigoPrincipal'        => 'P001',
        'descripcion'            => 'Producto de ejemplo',
        'cantidad'               => '1.00',
        'precioUnitario'         => '100.00',
        'descuento'              => '0.00',
        'precioTotalSinImpuesto' => '100.00',
        'impuestos' => [
            ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
        ],
    ]],
];

// 4. Crear el cliente y emitir.
$client = SriClient::create(Ambiente::Pruebas, $cert);
$result = $client->emit(Factura::fromArray($data), $claveAcceso);

// 5. Leer el resultado (propiedades tipadas).
if ($result->status === EmissionStatus::Authorized) {
    echo $result->numeroAutorizacion; // string
    echo $result->fechaAutorizacion;  // string ISO-8601
    echo $result->claveAcceso;        // string de 49 dígitos
    echo $result->signedXml;          // XML firmado
} else {
    foreach ($result->messages as $msg) {
        echo "[{$msg->tipo}] {$msg->identificador}: {$msg->mensaje}\n";
    }
}

// Compatibilidad ArrayAccess: el código 1.x sigue funcionando sin cambios.
echo $result['claveAcceso'];        // igual que $result->claveAcceso
echo $result['xmlFirmado'];         // igual que $result->signedXml
echo $result['numeroAutorizacion']; // igual que $result->numeroAutorizacion
```

## Notas

- **Transporte por defecto:** `SoapClientTransport` (basado en `ext-soap`, ya requerido por el paquete) funciona sin configuración adicional. El WSDL del SoapClient se obtiene una vez y queda cacheado en disco (`soap.wsdl_cache`, activo por defecto en PHP), así que no hay una descarga por cada emisión.
- **Transporte alternativo (testeable / agnóstico de framework):** inyecta `Psr18SoapTransport` con tu cliente PSR-18 (Guzzle, Symfony HttpClient, etc.):
  ```php
  use Teran\Sri\Transport\Psr18SoapTransport;

  $transport = new Psr18SoapTransport($psrHttpClient, $psrRequestFactory);
  $client    = SriClient::create(Ambiente::Pruebas, $cert, $transport);
  ```
- **La clase `SRI` (1.x) no será eliminada** en la 2.x; está deprecada de facto. La remoción formal (si la hubiere) se anunciará en la versión 3.0.
- **`ClaveAcceso::generar`** recibe los mismos parámetros que en 1.x; no hay cambio de firma.
- **`EmissionResult` implementa `ArrayAccess`** para que el código 1.x con acceso por corchetes (`$result['claveAcceso']`) siga compilando sin modificaciones.
