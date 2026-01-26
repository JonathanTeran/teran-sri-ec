# 🇪🇨 teran-sri-ec

[![Latest Version on Packagist](https://img.shields.io/packagist/v/teran/sri-ec.svg?style=flat-square)](https://packagist.org/packages/teran/sri-ec)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/teran/sri-ec.svg?style=flat-square)](https://packagist.org/packages/teran/sri-ec)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-blue.svg?style=flat-square)](https://www.php.net/)

Professional and high-performance library for **SRI Ecuador Electronic Billing**. Simplify the process of generating, signing, and authorizing electronic documents (Facturas, Retenciones, etc.) according to the latest SRI technical requirements.

## ✨ Key Features

- ✅ **XAdES-BES Signing**: Integrated digital signature for XML documents using `.p12` certificates.
- ✅ **XSD Validation**: Local validation against SRI schemas to ensure structural correctness before sending.
- ✅ **SOAP Client**: Robust communication with SRI web services (Reception & Authorization).
- ✅ **Simplified API**: High-level methods to process documents in a few lines of code.
- ✅ **Environment Support**: Easy switching between `pruebas` and `produccion`.
- ✅ **Business Validation**: Built-in RUC and logical data validation.

## 🚀 Installation

You can install the package via composer:

```bash
composer require teran/sri-ec
```

## 🛠 Requirements

- **PHP**: `^8.1`
- **Extensions**: `ext-curl`, `ext-dom`, `ext-libxml`, `ext-openssl`, `ext-soap`.

## 📖 Quick Start

### Basic Configuration

```php
use Teran\Sri\SRI;

// Initialize in 'pruebas' or 'produccion' environment
$sri = new SRI('pruebas');

// Set your digital signature (.p12)
$p12 = file_get_contents('path/to/your/signature.p12');
$password = 'your_p12_password';
$sri->setFirma($p12, $password);
```

### Processing a Factura (Simple Way)

```php
$facturaData = [
    'infoTributaria' => [
        'ruc' => '1790011001001',
        'estab' => '001',
        'ptoEmi' => '001',
        'secuencial' => '000000001',
        // ... other fields
    ],
    'infoFactura' => [
        'fechaEmision' => '26/01/2026',
        // ... other fields
    ],
    // ... items and taxes
];

try {
    $resultado = $sri->facturaFromArray($facturaData);
    
    echo "Clave de Acceso: " . $resultado['claveAcceso'];
    echo "Estado: " . $resultado['autorizacion']->estado;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## 🏗 Advanced Usage

For custom implementations, you can use `ComprobanteInterface`:

```php
use Teran\Sri\Strategies\ComprobanteInterface;

class MyCustomDoc implements ComprobanteInterface {
    // Implement required methods...
}

$doc = new MyCustomDoc();
$resultado = $sri->procesar($doc);
```

## 📂 Project Structure

- `src/Generators`: XML generation logic.
- `src/Signature`: XAdES-BES digital signature implementation.
- `src/Soap`: SRI Web Service clients.
- `src/Schema`: XSD and Business validation rules.
- `resources/xsd`: Official SRI schema definitions.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---
Developed with ❤️ by [Jonathan Terán](https://github.com/jonathanteran)
