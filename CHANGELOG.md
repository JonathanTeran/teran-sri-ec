# Changelog

All notable changes to `teran-sri-ec` will be documented in this file.

## [2.1.0] - 2026-06-11

Minor retrocompatible: el rechazo de emisión ahora distingue la **etapa**.

### Added
- **`EmissionResult::$rejectedStage`** (`?RejectionStage`, default `null`): cuando
  `status === EmissionStatus::Rejected`, expone si el rechazo fue en **recepción**
  (`RejectionStage::Recepcion` — comprobante DEVUELTO, nunca entró al SRI; se puede
  corregir y re-emitir con la misma clave) o en **autorización**
  (`RejectionStage::Autorizacion` — NO AUTORIZADO, quedó registrado). v2.0 colapsaba
  ambos en `Rejected` y los consumidores que necesitan el routing legal
  DEVUELTO/NO_AUTORIZADO (p. ej. facturación con reintentos) perdían la distinción.
- Nueva enum `Teran\Sri\Emission\RejectionStage` (`Recepcion` | `Autorizacion`).

### Notas de compatibilidad
- 100% retrocompatible: parámetro nuevo opcional al final del constructor de
  `EmissionResult`; `EmissionStatus` no cambia (sin casos nuevos — los `match`
  exhaustivos existentes siguen compilando). Consumidores que no lean
  `rejectedStage` no notan ningún cambio.

## [2.0.3] - 2026-06-05

Patch **crítico de correctitud de la firma** (+ autorización, rendimiento y ejemplos). Retrocompatible.

### Fixed — Firma XAdES (crítico para certificados Uanataca / eIDAS)
- **El `<ds:X509IssuerName>` ahora coincide exactamente con `javax.security.auth.x500.X500Principal.getName("RFC2253")`**, que es lo que valida el stack Java del SRI. Antes, los atributos del emisor sin palabra clave estándar — en particular `organizationIdentifier` (OID **2.5.4.97**), presente en certificados **Uanataca** y eIDAS — se emitían en formato OpenSSL (`organizationIdentifier=VATES-…`), lo que provocaba el rechazo **`[39] FIRMA INVALIDA – La información sobre el certificado de firma no se ajusta a XAdES`** al autorizar. Ahora se emiten como `2.5.4.97=#0c0f…` (OID numérico + valor en DER hexadecimal), idéntico a Java. Verificado **byte-a-byte contra OpenJDK 17** con certificados reales y de prueba, incluido el escaping completo (`, + " \ < > ; = #`). Nueva clase `Teran\Sri\Signing\IssuerName` (parser DER dedicado, con tests y revisión adversarial). **Aplica al API 2.0 (`XadesSigner`) y al API 1.x (`XadesSignature`).**
- **Sin regresión:** para certificados de CAs con solo atributos estándar (CN/O/OU/C/L/ST — el caso de las CAs ecuatorianas comunes), la salida del IssuerName es **idéntica** a la anterior. Los usuarios actuales no notan ningún cambio.

### Fixed — Autorización
- La consulta de autorización inmediatamente posterior a `RECIBIDA` (cuando el SRI todavía no ha generado el nodo `<autorizacion>`) ahora se interpreta como **`EN PROCESO`** (reintentable), no como `NO AUTORIZADO` (rechazo terminal). Afecta a `SoapResponseParser` y `SoapStdClassParser`.

### Performance
- **Firma ~22% más rápida**: `XadesSigner` ahora también memoiza la **clave privada parseada** (`openssl_pkey_get_private`), además del parseo del certificado introducido en 2.0.1. Reutiliza la misma instancia de `XadesSigner` para aprovechar la caché.

### Docs / Ejemplos
- Nuevo `examples/smoke-prueba-sri.php`: prueba el flujo completo (firmar → enviar → autorizar) contra el ambiente de **pruebas** del SRI con tu propio certificado. La clave del `.p12` se pide por consola (nunca por `argv`); el certificado nunca sale de tu máquina.

### Calidad
- 171 tests · PHPStan nivel máximo limpio · la nueva clase `IssuerName` pasó revisión adversarial de seguridad (parser DER robusto ante certificados malformados: sin lecturas fuera de límites ni bucles).

## [2.0.2] - 2026-06-04

### Docs
- Botón **"Invítame un café"** (PayPal) en el README, en `composer.json` (`funding`) y en `.github/FUNDING.yml` (botón *Sponsor* de GitHub).
- Corregido el badge de versión de PHP en el README (8.1 → 8.2, acorde al mínimo real del 2.0).

## [2.0.1] - 2026-06-04

Patch de **rendimiento**, retrocompatible. Misma salida (firma idéntica y verificada).

### Performance
- **Firma ~12% más rápida** (de ~520 a ~580 comprobantes/seg en el pipeline completo): `XadesSigner` ahora **memoiza el parseo del certificado** (`openssl_x509_parse`, detalles de la clave, DN, serial, digest), que no cambia entre documentos. Comportamiento idéntico — los tests de verificación criptográfica, golden y determinismo siguen verdes. **Sugerencia para lotes:** reutiliza la misma instancia de `XadesSigner` (y del serializador) en vez de crear una nueva por documento, para aprovechar la caché. Para mayor throughput, la firma es *stateless* y escala con workers en paralelo.

## [2.0.0] - 2026-06-04

Rediseño mayor: **núcleo agnóstico de framework, tipado, seguro, con envío masivo.** El API 1.x (`SRI`) se mantiene **intacta y funcional** (deprecada) — el código existente sigue corriendo; solo se sube el constraint `^1.0` → `^2.0`.

### Added — API 2.0 (recomendada)
- **Modelo de documentos tipado:** DTOs inmutables `Factura`, `NotaCredito`, `NotaDebito`, `GuiaRemision`, `Retencion` (+ sub-objetos), con `::fromArray()` (mismas llaves de 1.x) y validación en construcción. Dinero decimal-seguro (`Money`, bcmath). Catálogos como enums (`Ambiente`, `TipoComprobante`, `FormaPago`, …).
- **Serializadores XML** para los 5 comprobantes, verificados por **paridad** contra los generadores 1.x probados en producción + escape correcto. La factura declara `version="2.1.0"` (corrige el `1.1.0` del path 1.x).
- **Carga de certificado endurecida** (`CertificateLoader`): contraseña por `stdin` (no por `argv`) y clave privada descifrada **nunca escrita a disco** (capturada por stdout) en el *fallback* de OpenSSL CLI.
- **Firma XAdES-BES** (`XadesSigner`): port fiel del 1.x, `Clock` inyectable (firma determinista y testeable), descripción configurable (sin branding de terceros), **verificada criptográficamente**.
- **`SriClient::emit()`** orquesta serializar → firmar → enviar → autorizar, devolviendo un `EmissionResult` inmutable (con `ArrayAccess` para compat 1.x: `$r['claveAcceso']`, `$r['xmlFirmado']`).
- **Transportes intercambiables** (`SriTransportInterface`): `SoapClientTransport` (ext-soap, funciona out-of-the-box) y `Psr18SoapTransport` (cualquier cliente PSR-18).
- **Envío masivo** (`BatchEmitter` / `BatchProcessor`): máquina de estados por documento, **idempotente** (clave de acceso), reintentos con backoff, rate-limit (interfaz), repositorio (interfaz + implementación in-memory). Reanudable.

### Changed
- **PHP mínimo: 8.2** (antes 8.1, ya fuera de soporte de seguridad).
- Nuevas dependencias: `ext-bcmath`, `psr/http-client`, `psr/http-factory` (solo interfaces; el cliente HTTP concreto lo inyecta el usuario para el transporte PSR-18).
- El API `SRI` (1.x, basada en arrays) queda **`@deprecated`** en favor de `SriClient` / `BatchEmitter`. Se eliminará en 3.0.

### Security
- Remediados los hallazgos de auditoría **M-1** (password del `.p12` en línea de comandos) y **M-2** (clave privada a disco) en el nuevo camino de carga. Firma cripto-verificada. **PHPStan nivel máximo sin errores** en el código 2.0 (capturó bugs reales de null-safety).

### Calidad
- 165 tests · CI con GitHub Actions (PHP 8.2/8.3/8.4) · PHPStan nivel máximo · cada incremento revisado por agentes independientes (spec + calidad + seguridad).

### ⚠️ Antes de usar en producción
- Hacer un **smoke test** de un transporte contra el ambiente de **pruebas** del SRI (`celcer.sri.gob.ec`) con un comprobante firmado real: el formato SOAP está verificado contra la documentación, no contra el servicio en vivo.

### Migración
- El código 1.x **sigue funcionando sin cambios**. Para adoptar el API nuevo: `$sri->facturaFromArray($a)` → `$client->emit(Factura::fromArray($a), $clave)`. Ver la sección "API 2.0" del README.

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
