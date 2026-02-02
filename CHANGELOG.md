# Changelog

All notable changes to `teran-sri-ec` will be documented in this file.

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
