<?php

require_once __DIR__ . '/vendor/autoload.php';

use Teran\Sri\Signature\XadesSignature;

if ($argc < 3) {
    echo "Uso: php verify_p12.php <path_to_p12> <password>\n";
    exit(1);
}

$p12Path = $argv[1];
$password = $argv[2];

if (!file_exists($p12Path)) {
    echo "Error: El archivo P12 no existe: $p12Path\n";
    exit(1);
}

$p12Content = file_get_contents($p12Path);

try {
    echo "Intentando cargar certificado...\n";
    $signature = new XadesSignature($p12Content, $password);
    
    echo "✅ Certificado cargado exitosamente!\n";
    
    $info = $signature->getCertificateInfo();
    echo "----------------------------------------\n";
    echo "Proveedor: " . $info['provider'] . "\n";
    echo "Sujeto: " . $info['subjectCN'] . "\n";
    echo "Emisor: " . $info['issuerCN'] . "\n";
    echo "Válido desde: " . $info['validFrom']->format('Y-m-d H:i:s') . "\n";
    echo "Válido hasta: " . $info['validTo']->format('Y-m-d H:i:s') . "\n";
    echo "Tipo Clave: " . $info['keyType'] . " (" . $info['keyBits'] . " bits)\n";
    echo "----------------------------------------\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
