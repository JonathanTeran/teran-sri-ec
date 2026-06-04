# Changelog

All notable changes to `teran-sri-ec` will be documented in this file.

## [1.1.2] - 2026-06-04

Endurecimiento de seguridad adicional (hardening), 100% retrocompatible. No cambia la salida de los comprobantes.

### Security
- **Limpieza garantizada del temporal del certificado:** el `.p12` y el PEM con la clave privada descifrada del *fallback* de OpenSSL CLI ahora se borran en un bloque `finally`, en cualquier salida (éxito, error o excepción). Antes, ciertos caminos de error podían dejar la clave en el directorio temporal.
- **Validación de nombres XML:** un nombre de campo inválido (p. ej. una clave con espacios en `infoAdicional` o en los impuestos) ahora lanza `ValidationException` capturable en vez de un `DOMException` fatal no controlado.
- **Dist más limpio:** `.gitattributes` (`export-ignore`) excluye del paquete distribuido las utilidades de desarrollo (`verify_p12.php`, `example.php`), los tests, la config de PHPUnit y `vendor/`. Menos superficie y tamaño, sin afectar instalaciones existentes.

### Pendiente para 2.0
- Pasar la contraseña del `.p12` por `stdin` (no por `argv`) y mantener la clave privada fuera de disco en el *fallback* de OpenSSL CLI (hallazgos M-1/M-2 de la auditoría; solo afectan a hosting compartido con certificados legacy). Requiere un certificado legacy de prueba para validarlo sin riesgo.

## [1.1.1] - 2026-06-04

Parche de **seguridad**, 100% retrocompatible. No cambia la salida de los comprobantes.

### Security
- **Fuga de datos sensibles (alta):** se eliminó el volcado del XML firmado y de la respuesta SOAP completa a los logs en cada emisión (`SRI::procesar`). El logging ahora usa PSR-3 vía `LoggerTrait` y registra solo los mensajes de error del SRI, sin datos del comprobante.
- **Escritura de debug a disco (alta):** se eliminó `file_put_contents(base_path('storage/app/final_signed.xml'), ...)` en `XadesSignature::sign`, que filtraba el comprobante firmado a disco en cada firma (y acoplaba la librería a Laravel).
- **TLS sin verificar (media):** `RucValidator` ahora verifica el certificado del SRI (`CURLOPT_SSL_VERIFYPEER=true`, `CURLOPT_SSL_VERIFYHOST=2`) al consultar el RUC, evitando ataques MITM.
- **Temporal del certificado (media):** el fallback de OpenSSL CLI usa `tempnam()` (nombre impredecible + permisos 0600) y aplica `chmod(0600)` al PEM que contiene la clave privada descifrada.
- **XXE (defensa en profundidad):** `loadXML` usa `LIBXML_NONET` en la validación XSD y en la firma.

### Fixed
- **Escape XML:** los valores de los comprobantes se insertan ahora como nodos de texto, escapando correctamente `&`, `<`, `>`. Antes, un valor con `&` (p. ej. "ALMACENES J & J") corrompía el XML generado en los 5 generadores.

### Compatibilidad
- Al eliminar el acoplamiento a `Illuminate\Support\Facades\Log` y `base_path()`, la librería ahora **también funciona fuera de Laravel**. La salida firmada no cambia.

### Conocido (diferido a 2.0)
- El atributo `version` de la factura emite "1.1.0"; debería ser "2.1.0" según la ficha técnica/XSD. No se modifica la salida en este parche de seguridad.

## [Unreleased] - 2026-02-01

### Fixed
- **Critical**: Fixed temp file creation in `XadesSignature.php` that was using broken `tmpfile()` approach
  - Changed from `stream_get_meta_data(tmpfile())['uri']` to `sys_get_temp_dir() . '/p12_' . uniqid() . '.p12'`
  - This fix ensures the OpenSSL CLI fallback works correctly on all systems

- **Critical**: Prioritized OpenSSL 1.1 over OpenSSL 3.0+ for legacy certificate support
  - Legacy P12 certificates (pre-2024) from Ecuadorian providers use RC2/3DES encryption
  - OpenSSL 3.0+ rejects these algorithms by default
  - The fallback now tries OpenSSL 1.1 first, then falls back to OpenSSL 3.0+
  - Ensures compatibility with certificates from Uanataca, Security Data, ANF AC, and other providers

### Confirmed Working
- PSR-4 compliant exception classes (each in separate file)
- XSD validation with `processContents="lax"` for signature wildcard
- OpenSSL CLI fallback for certificate reading

### Technical Details

#### Temp File Fix
**Before:**
```php
$tempFile = stream_get_meta_data(tmpfile())['uri'];  // Returns invalid path
```

**After:**
```php
$tempFile = sys_get_temp_dir() . '/p12_' . uniqid() . '.p12';  // Valid temp file path
```

#### OpenSSL Priority Fix
**Before:** Tried OpenSSL 3 first → Failed with legacy certs → Error
**After:** Tries OpenSSL 1.1 first → Success with legacy certs → Falls back to OpenSSL 3 for modern certs

### Testing
Tested with:
- ✅ Legacy certificates (Uanataca 2023-2024)
- ✅ Modern certificates (Uanataca 2025+)
- ✅ macOS with Homebrew OpenSSL installations
- ✅ Production and test SRI environments

### Credits
Fixes identified and tested in production environments:
- GoTicket (ticketing system)
- Amephia Gym (gym management system)
