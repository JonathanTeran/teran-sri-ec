<?php

/**
 * Smoke test del flujo 2.0 contra el ambiente de PRUEBAS del SRI.
 *
 * Firma una factura mínima con TU certificado y la envía al SRI de pruebas,
 * mostrando la respuesta real (recepción + autorización).
 *
 * El certificado NUNCA sale de tu máquina. La clave del .p12 se pide por consola
 * (no se pasa en el comando, para que no quede en el historial del shell).
 *
 * Uso:
 *   php examples/smoke-prueba-sri.php /ruta/a/tu-firma.p12 TU_RUC [estab] [ptoEmi] [secuencial]
 *
 * Ejemplo:
 *   php examples/smoke-prueba-sri.php ~/firma.p12 1790011001001 001 001 000000001
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Signing\XadesSigner;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Utils\ClaveAcceso;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Transport\SoapClientTransport;

// --- Argumentos ---
$p12Path = $argv[1] ?? null;
$ruc     = $argv[2] ?? null;
$estab   = $argv[3] ?? '001';
$ptoEmi  = $argv[4] ?? '001';
$sec     = $argv[5] ?? '000000001';

if ($p12Path === null || $ruc === null) {
    fwrite(STDERR, "Uso: php examples/smoke-prueba-sri.php /ruta/firma.p12 TU_RUC [estab] [ptoEmi] [secuencial]\n");
    exit(1);
}
if (!is_file($p12Path)) {
    fwrite(STDERR, "No se encontró el certificado: $p12Path\n");
    exit(1);
}
if (strlen($ruc) !== 13 || !ctype_digit($ruc)) {
    fwrite(STDERR, "El RUC debe tener 13 dígitos.\n");
    exit(1);
}

// --- Clave del .p12 por consola (sin eco si es posible) ---
fwrite(STDOUT, "Clave del certificado (.p12): ");
$pass = '';
if (function_exists('shell_exec') && stripos(PHP_OS, 'WIN') === false) {
    @shell_exec('stty -echo 2>/dev/null');
    $pass = rtrim((string) fgets(STDIN), "\n");
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
} else {
    $pass = rtrim((string) fgets(STDIN), "\n");
}

echo "\n== SMOKE TEST 2.0 → SRI PRUEBAS ==\n\n";

// 1) Cargar certificado
try {
    $cert = (new CertificateLoader())->load((string) file_get_contents($p12Path), $pass);
    $info = $cert->x509Info();
    echo "1) ✅ Certificado cargado. Sujeto: " . ($info['subject']['CN'] ?? '(desconocido)') . "\n";
} catch (\Throwable $e) {
    echo "1) ❌ No se pudo cargar el certificado: " . $e->getMessage() . "\n";
    echo "   (Verifica la clave y que el .p12 sea válido.)\n";
    exit(1);
}

// 2) Clave de acceso (módulo 11) — fecha de hoy
$fecha = date('d/m/Y');
$codigoNum = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
$clave = ClaveAcceso::generar(date('dmY'), '01', $ruc, '1', $estab . $ptoEmi, $sec, $codigoNum, '1');
echo "2) Clave de acceso: $clave\n";

// 3) Factura mínima (consumidor final, IVA 15%) -> serializar -> firmar
$data = [
    'infoTributaria' => [
        'ambiente' => '1', 'razonSocial' => 'EMISOR DE PRUEBA', 'ruc' => $ruc,
        'estab' => $estab, 'ptoEmi' => $ptoEmi, 'secuencial' => $sec, 'dirMatriz' => 'Quito',
    ],
    'infoFactura' => [
        'fechaEmision' => $fecha, 'obligadoContabilidad' => 'NO',
        'tipoIdentificacionComprador' => '07', 'razonSocialComprador' => 'CONSUMIDOR FINAL',
        'identificacionComprador' => '9999999999999',
        'totalSinImpuestos' => '100.00', 'totalDescuento' => '0.00', 'importeTotal' => '115.00',
        'totalConImpuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00']],
        'pagos' => [['formaPago' => '01', 'total' => '115.00']],
    ],
    'detalles' => [[
        'codigoPrincipal' => 'PROD-001', 'descripcion' => 'Producto de prueba',
        'cantidad' => '1.00', 'precioUnitario' => '100.00', 'descuento' => '0.00', 'precioTotalSinImpuesto' => '100.00',
        'impuestos' => [['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00']],
    ]],
];
$signed = (new XadesSigner())->sign((new FacturaXmlSerializer())->serialize(Factura::fromArray($data), $clave), $cert);
echo "3) ✅ Factura serializada y firmada (XAdES). " . strlen($signed) . " bytes.\n\n";

$transport = new SoapClientTransport(timeout: 30, retries: 2);

// 4) RECEPCIÓN
echo "4) Enviando al SRI (recepción)...\n";
$rec = $transport->enviar($signed, Ambiente::Pruebas);
echo "   → estado: {$rec->estado}\n";
foreach ($rec->mensajes as $m) {
    echo "     • [{$m->identificador}] {$m->tipo}: {$m->mensaje}" . ($m->informacionAdicional !== '' ? " ({$m->informacionAdicional})" : '') . "\n";
}

if ($rec->estado !== 'RECIBIDA') {
    echo "\n⚠️ El SRI no recibió el comprobante. Revisa los mensajes de arriba (suele ser datos del emisor/RUC en pruebas).\n";
    echo "   IMPORTANTE: que el TRANSPORTE haya traído esta respuesta ya confirma que la comunicación funciona.\n";
    exit(0);
}

// 5) AUTORIZACIÓN (con re-consulta si está EN PROCESO)
echo "\n5) Consultando autorización...\n";
for ($intento = 1; $intento <= 4; $intento++) {
    $aut = $transport->autorizar($clave, Ambiente::Pruebas);
    echo "   intento $intento → estado: {$aut->estado}\n";
    foreach ($aut->mensajes as $m) {
        echo "     • [{$m->identificador}] {$m->tipo}: {$m->mensaje}" . ($m->informacionAdicional !== '' ? " ({$m->informacionAdicional})" : '') . "\n";
    }
    if (in_array(strtoupper($aut->estado), ['EN PROCESO', 'EN PROCESAMIENTO'], true)) {
        echo "     (en proceso, reintentando en 3s...)\n";
        sleep(3);
        continue;
    }
    if (strtoupper($aut->estado) === 'AUTORIZADO') {
        echo "\n🎉 ¡AUTORIZADO! Nº {$aut->numeroAutorizacion} — {$aut->fechaAutorizacion}\n";
        echo "   El flujo 2.0 completo funciona contra el SRI con tu certificado. ✅\n";
    } else {
        echo "\nEstado final: {$aut->estado}. Revisa los mensajes (datos de negocio del emisor).\n";
    }
    break;
}

echo "\n== Fin ==\n";
