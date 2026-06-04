# Fase 1.1 — Modelo de documentos tipado (Factura) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el modelo de documentos inmutable y tipado del 2.0 para la **Factura** (value objects `Money`, enums de catálogos, y los DTOs `Impuesto`, `Pago`, `Detalle`, `InfoTributaria`, `Factura` con `::fromArray()` y validación), sin tocar el código 1.x existente.

**Architecture:** Código nuevo y aditivo bajo `src/` en sub-namespaces nuevos (`Teran\Sri\Money`, `Teran\Sri\Catalogs2`, `Teran\Sri\Documents`). La clase `SRI` vieja queda intacta como futuro shim. Los DTOs son `readonly`, validan en el constructor (lanzan `ValidationException`), y exponen `::fromArray()` con **las mismas llaves de 1.x** para migración suave. El dinero se maneja con **bcmath** (sin `float`) para evitar descuadres de centavos.

**Tech Stack:** PHP 8.2 (`readonly`, enums), `ext-bcmath`, PHPUnit 10. Namespace raíz existente `Teran\Sri\` → `src/`.

---

## Contexto y alcance

Este es el **Plan 1 de la Fase 1** (núcleo seguro y agnóstico) del spec `docs/superpowers/specs/2026-06-04-sri-ec-2.0-design.md` (§4.2). Cubre **solo el modelo de datos de la Factura**. Planes siguientes de la Fase 1 (no incluidos aquí): serializador XML + XSD, `CertificateLoader`/`XadesSigner` endurecido (M-1/M-2), transporte PSR-18, `SriClient` + `EmissionResult`, y el shim legacy. Los DTOs de NotaCrédito/NotaDébito/Guía/Retención replican el patrón de la Factura en un plan posterior.

> **Nota de naming:** uso `Teran\Sri\Catalogs2` para los enums nuevos a fin de **no colisionar** con el directorio `src/Catalogs/` existente (clases estáticas de 1.x). En la Fase 3, al retirar 1.x, se renombra a `Catalogs`.

### Mapa de archivos (este plan)

- Crear: `src/Money/Money.php` — value object monetario decimal-seguro (bcmath).
- Crear: `src/Catalogs2/Ambiente.php` — enum (Pruebas=1, Produccion=2).
- Crear: `src/Catalogs2/TipoEmision.php` — enum (Normal=1).
- Crear: `src/Catalogs2/TipoComprobante.php` — enum (Factura=01, NC=04, …).
- Crear: `src/Catalogs2/FormaPago.php` — enum (códigos SRI de forma de pago).
- Crear: `src/Documents/Impuesto.php` — DTO línea de impuesto.
- Crear: `src/Documents/Pago.php` — DTO forma de pago.
- Crear: `src/Documents/Detalle.php` — DTO línea de detalle.
- Crear: `src/Documents/InfoTributaria.php` — DTO cabecera tributaria.
- Crear: `src/Documents/Factura.php` — agregado raíz + `::fromArray()`.
- Modificar: `composer.json` — añadir `ext-bcmath` a `require`.
- Test: `tests/Unit/Money/MoneyTest.php`, `tests/Unit/Catalogs2/EnumsTest.php`, `tests/Unit/Documents/{ImpuestoTest,PagoTest,DetalleTest,InfoTributariaTest,FacturaTest}.php`.

### Convenciones para todos los DTOs

- `final class` con propiedades `public readonly`.
- Constructor valida y lanza `\Teran\Sri\Exceptions\ValidationException` (firma existente: `new ValidationException(string $message, array $errors = [])`).
- `::fromArray(array $data): self` mapea las llaves de 1.x (ver README 1.x) y delega en el constructor.
- Sin dependencias de framework.

---

### Task 1: Añadir `ext-bcmath` a composer

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Añadir la extensión a `require`**

En `composer.json`, dentro de `"require"`, añadir la línea `"ext-bcmath": "*"` junto a las demás `ext-*` (orden alfabético, después de `ext-curl`... antes de `ext-dom`):

```json
    "require": {
        "php": ">=8.1",
        "ext-bcmath": "*",
        "ext-curl": "*",
        "ext-dom": "*",
        "ext-libxml": "*",
        "ext-openssl": "*",
        "ext-soap": "*",
        "psr/log": "^3.0"
    },
```

- [ ] **Step 2: Verificar que bcmath está disponible y composer valida**

Run: `php -r "echo extension_loaded('bcmath') ? 'OK' : 'MISSING';" && composer validate --no-check-publish`
Expected: `OK` y `./composer.json is valid`

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "build: require ext-bcmath for decimal-safe Money"
```

---

### Task 2: Value object `Money` (decimal-seguro)

**Files:**
- Create: `src/Money/Money.php`
- Test: `tests/Unit/Money/MoneyTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Money;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

class MoneyTest extends TestCase
{
    public function test_formats_to_fixed_decimals_without_float_drift(): void
    {
        // 0.1 + 0.2 en float da 0.30000000000000004; con bcmath debe ser exacto.
        $sum = Money::of('0.10')->plus(Money::of('0.20'));
        $this->assertSame('0.30', $sum->format(2));
    }

    public function test_rounds_half_up_to_requested_scale(): void
    {
        $this->assertSame('2.46', Money::of('2.455')->format(2));
        $this->assertSame('1.000000', Money::of('1')->format(6));
    }

    public function test_accepts_int_and_float_inputs(): void
    {
        $this->assertSame('100.00', Money::of(100)->format(2));
        $this->assertSame('12.50', Money::of(12.5)->format(2));
    }

    public function test_rejects_non_numeric(): void
    {
        $this->expectException(ValidationException::class);
        Money::of('abc');
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Money/MoneyTest.php`
Expected: FAIL con `Error: Class "Teran\Sri\Money\Money" not found`.

- [ ] **Step 3: Implementar `Money`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Money;

use Teran\Sri\Exceptions\ValidationException;

/**
 * Monto monetario decimal-seguro. Internamente guarda una cadena decimal
 * normalizada y opera con bcmath, evitando errores de redondeo de float.
 */
final class Money
{
    /** Escala interna alta para no perder precisión en operaciones intermedias. */
    private const INTERNAL_SCALE = 6;

    private function __construct(private readonly string $amount)
    {
    }

    public static function of(string|int|float $amount): self
    {
        $str = is_float($amount)
            ? number_format($amount, self::INTERNAL_SCALE, '.', '')
            : (string) $amount;

        if (!is_numeric($str)) {
            throw new ValidationException("Monto no numérico: '$str'.");
        }

        // Normalizar a la escala interna con bcmath.
        return new self(bcadd($str, '0', self::INTERNAL_SCALE));
    }

    public function plus(self $other): self
    {
        return new self(bcadd($this->amount, $other->amount, self::INTERNAL_SCALE));
    }

    public function times(string|int $factor): self
    {
        return new self(bcmul($this->amount, (string) $factor, self::INTERNAL_SCALE));
    }

    /**
     * Devuelve el monto formateado a $decimals con redondeo half-up (como el SRI).
     */
    public function format(int $decimals): string
    {
        $rounding = '0.' . str_repeat('0', $decimals) . '5';
        $rounded = $this->amount[0] === '-'
            ? bcsub($this->amount, $rounding, $decimals)
            : bcadd($this->amount, $rounding, $decimals);

        return $rounded;
    }

    public function __toString(): string
    {
        return $this->format(2);
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Money/MoneyTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Money/Money.php tests/Unit/Money/MoneyTest.php
git commit -m "feat: add decimal-safe Money value object"
```

---

### Task 3: Enums de catálogos

**Files:**
- Create: `src/Catalogs2/Ambiente.php`, `src/Catalogs2/TipoEmision.php`, `src/Catalogs2/TipoComprobante.php`, `src/Catalogs2/FormaPago.php`
- Test: `tests/Unit/Catalogs2/EnumsTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Catalogs2;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Catalogs2\TipoComprobante;
use Teran\Sri\Catalogs2\FormaPago;

class EnumsTest extends TestCase
{
    public function test_ambiente_has_sri_codes(): void
    {
        $this->assertSame('1', Ambiente::Pruebas->value);
        $this->assertSame('2', Ambiente::Produccion->value);
    }

    public function test_tipo_comprobante_factura_code(): void
    {
        $this->assertSame('01', TipoComprobante::Factura->value);
        $this->assertSame(TipoComprobante::Factura, TipoComprobante::from('01'));
    }

    public function test_forma_pago_known_code_resolves(): void
    {
        $this->assertSame(FormaPago::SinUtilizacionSistemaFinanciero, FormaPago::from('01'));
        $this->assertNull(FormaPago::tryFrom('zzz'));
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Catalogs2/EnumsTest.php`
Expected: FAIL con `Class "Teran\Sri\Catalogs2\Ambiente" not found`.

- [ ] **Step 3: Implementar los enums**

`src/Catalogs2/Ambiente.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs2;

enum Ambiente: string
{
    case Pruebas = '1';
    case Produccion = '2';
}
```

`src/Catalogs2/TipoEmision.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs2;

enum TipoEmision: string
{
    case Normal = '1';
}
```

`src/Catalogs2/TipoComprobante.php`:

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs2;

enum TipoComprobante: string
{
    case Factura = '01';
    case NotaCredito = '04';
    case NotaDebito = '05';
    case GuiaRemision = '06';
    case Retencion = '07';
}
```

`src/Catalogs2/FormaPago.php` (códigos de la tabla 24 del SRI; se incluyen los de uso común):

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Catalogs2;

enum FormaPago: string
{
    case SinUtilizacionSistemaFinanciero = '01';
    case CompensacionDeudas = '15';
    case TarjetaDebito = '16';
    case DineroElectronico = '17';
    case TarjetaPrepago = '18';
    case TarjetaCredito = '19';
    case OtrosConUtilizacionSistemaFinanciero = '20';
    case EndosoTitulos = '21';
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Catalogs2/EnumsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Catalogs2/ tests/Unit/Catalogs2/EnumsTest.php
git commit -m "feat: add typed SRI catalog enums (ambiente, tipo comprobante, forma pago)"
```

---

### Task 4: DTO `Impuesto`

**Files:**
- Create: `src/Documents/Impuesto.php`
- Test: `tests/Unit/Documents/ImpuestoTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

class ImpuestoTest extends TestCase
{
    public function test_from_array_maps_1x_keys(): void
    {
        $imp = Impuesto::fromArray([
            'codigo' => '2',
            'codigoPorcentaje' => '4',
            'tarifa' => '15.00',
            'baseImponible' => '100.00',
            'valor' => '15.00',
        ]);

        $this->assertSame('2', $imp->codigo);
        $this->assertSame('4', $imp->codigoPorcentaje);
        $this->assertSame('15.00', $imp->baseImponible->format(2));
        $this->assertSame('15.00', $imp->valor->format(2));
    }

    public function test_rejects_missing_codigo(): void
    {
        $this->expectException(ValidationException::class);
        Impuesto::fromArray(['baseImponible' => '100.00', 'valor' => '15.00']);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Documents/ImpuestoTest.php`
Expected: FAIL con `Class "Teran\Sri\Documents\Impuesto" not found`.

- [ ] **Step 3: Implementar `Impuesto`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Impuesto
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $codigoPorcentaje,
        public readonly Money $baseImponible,
        public readonly Money $valor,
        public readonly ?string $tarifa = null,
    ) {
        if ($codigo === '') {
            throw new ValidationException('Impuesto: el campo "codigo" es obligatorio.');
        }
        if ($codigoPorcentaje === '') {
            throw new ValidationException('Impuesto: el campo "codigoPorcentaje" es obligatorio.');
        }
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['codigo'])) {
            throw new ValidationException('Impuesto: falta la llave "codigo".');
        }

        return new self(
            codigo: (string) $data['codigo'],
            codigoPorcentaje: (string) ($data['codigoPorcentaje'] ?? ''),
            baseImponible: Money::of($data['baseImponible'] ?? 0),
            valor: Money::of($data['valor'] ?? 0),
            tarifa: isset($data['tarifa']) ? (string) $data['tarifa'] : null,
        );
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Documents/ImpuestoTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Documents/Impuesto.php tests/Unit/Documents/ImpuestoTest.php
git commit -m "feat: add Impuesto document DTO"
```

---

### Task 5: DTO `Pago`

**Files:**
- Create: `src/Documents/Pago.php`
- Test: `tests/Unit/Documents/PagoTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Catalogs2\FormaPago;
use Teran\Sri\Exceptions\ValidationException;

class PagoTest extends TestCase
{
    public function test_from_array_resolves_forma_pago_enum(): void
    {
        $pago = Pago::fromArray(['formaPago' => '01', 'total' => '112.00']);

        $this->assertSame(FormaPago::SinUtilizacionSistemaFinanciero, $pago->formaPago);
        $this->assertSame('112.00', $pago->total->format(2));
    }

    public function test_rejects_unknown_forma_pago(): void
    {
        $this->expectException(ValidationException::class);
        Pago::fromArray(['formaPago' => '99', 'total' => '1.00']);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Documents/PagoTest.php`
Expected: FAIL con `Class "Teran\Sri\Documents\Pago" not found`.

- [ ] **Step 3: Implementar `Pago`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Catalogs2\FormaPago;
use Teran\Sri\Exceptions\ValidationException;

final class Pago
{
    public function __construct(
        public readonly FormaPago $formaPago,
        public readonly Money $total,
        public readonly ?int $plazo = null,
        public readonly ?string $unidadTiempo = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $codigo = (string) ($data['formaPago'] ?? '');
        $formaPago = FormaPago::tryFrom($codigo);
        if ($formaPago === null) {
            throw new ValidationException("Pago: forma de pago desconocida '$codigo'.");
        }

        return new self(
            formaPago: $formaPago,
            total: Money::of($data['total'] ?? 0),
            plazo: isset($data['plazo']) ? (int) $data['plazo'] : null,
            unidadTiempo: isset($data['unidadTiempo']) ? (string) $data['unidadTiempo'] : null,
        );
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Documents/PagoTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Documents/Pago.php tests/Unit/Documents/PagoTest.php
git commit -m "feat: add Pago document DTO"
```

---

### Task 6: DTO `Detalle`

**Files:**
- Create: `src/Documents/Detalle.php`
- Test: `tests/Unit/Documents/DetalleTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Exceptions\ValidationException;

class DetalleTest extends TestCase
{
    public function test_from_array_builds_nested_impuestos(): void
    {
        $det = Detalle::fromArray([
            'codigoPrincipal' => 'PROD001',
            'descripcion' => 'Producto de prueba',
            'cantidad' => '1.00',
            'precioUnitario' => '100.00',
            'descuento' => '0.00',
            'precioTotalSinImpuesto' => '100.00',
            'impuestos' => [
                ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
            ],
        ]);

        $this->assertSame('PROD001', $det->codigoPrincipal);
        $this->assertCount(1, $det->impuestos);
        $this->assertInstanceOf(Impuesto::class, $det->impuestos[0]);
        $this->assertSame('100.000000', $det->cantidad->format(6));
    }

    public function test_rejects_detalle_without_impuestos(): void
    {
        $this->expectException(ValidationException::class);
        Detalle::fromArray([
            'codigoPrincipal' => 'P1',
            'descripcion' => 'x',
            'cantidad' => '1',
            'precioUnitario' => '1',
            'precioTotalSinImpuesto' => '1',
            'impuestos' => [],
        ]);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Documents/DetalleTest.php`
Expected: FAIL con `Class "Teran\Sri\Documents\Detalle" not found`.

- [ ] **Step 3: Implementar `Detalle`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Detalle
{
    /** @param Impuesto[] $impuestos */
    public function __construct(
        public readonly string $codigoPrincipal,
        public readonly string $descripcion,
        public readonly Money $cantidad,
        public readonly Money $precioUnitario,
        public readonly Money $descuento,
        public readonly Money $precioTotalSinImpuesto,
        public readonly array $impuestos,
        public readonly ?string $codigoAuxiliar = null,
    ) {
        if ($descripcion === '') {
            throw new ValidationException('Detalle: "descripcion" es obligatoria.');
        }
        if ($impuestos === []) {
            throw new ValidationException('Detalle: debe tener al menos un impuesto.');
        }
        foreach ($impuestos as $imp) {
            if (!$imp instanceof Impuesto) {
                throw new ValidationException('Detalle: cada impuesto debe ser una instancia de Impuesto.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $impuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $data['impuestos'] ?? [],
        );

        return new self(
            codigoPrincipal: (string) ($data['codigoPrincipal'] ?? ''),
            descripcion: (string) ($data['descripcion'] ?? ''),
            cantidad: Money::of($data['cantidad'] ?? 0),
            precioUnitario: Money::of($data['precioUnitario'] ?? 0),
            descuento: Money::of($data['descuento'] ?? 0),
            precioTotalSinImpuesto: Money::of($data['precioTotalSinImpuesto'] ?? 0),
            impuestos: $impuestos,
            codigoAuxiliar: isset($data['codigoAuxiliar']) ? (string) $data['codigoAuxiliar'] : null,
        );
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Documents/DetalleTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Documents/Detalle.php tests/Unit/Documents/DetalleTest.php
git commit -m "feat: add Detalle document DTO with nested Impuesto"
```

---

### Task 7: DTO `InfoTributaria`

**Files:**
- Create: `src/Documents/InfoTributaria.php`
- Test: `tests/Unit/Documents/InfoTributariaTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\InfoTributaria;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\ValidationException;

class InfoTributariaTest extends TestCase
{
    public function test_from_array_maps_and_resolves_ambiente(): void
    {
        $info = InfoTributaria::fromArray([
            'ambiente' => '1',
            'razonSocial' => 'MI EMPRESA S.A.',
            'ruc' => '1790011001001',
            'estab' => '001',
            'ptoEmi' => '001',
            'secuencial' => '000000001',
            'dirMatriz' => 'Quito, Ecuador',
        ]);

        $this->assertSame(Ambiente::Pruebas, $info->ambiente);
        $this->assertSame('1790011001001', $info->ruc);
        $this->assertSame('001', $info->estab);
    }

    public function test_rejects_ruc_with_wrong_length(): void
    {
        $this->expectException(ValidationException::class);
        InfoTributaria::fromArray([
            'ambiente' => '1',
            'razonSocial' => 'X',
            'ruc' => '123',
            'estab' => '001',
            'ptoEmi' => '001',
            'secuencial' => '000000001',
            'dirMatriz' => 'Quito',
        ]);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Documents/InfoTributariaTest.php`
Expected: FAIL con `Class "Teran\Sri\Documents\InfoTributaria" not found`.

- [ ] **Step 3: Implementar `InfoTributaria`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Catalogs2\TipoEmision;
use Teran\Sri\Exceptions\ValidationException;

final class InfoTributaria
{
    public function __construct(
        public readonly Ambiente $ambiente,
        public readonly string $razonSocial,
        public readonly string $ruc,
        public readonly string $estab,
        public readonly string $ptoEmi,
        public readonly string $secuencial,
        public readonly string $dirMatriz,
        public readonly TipoEmision $tipoEmision = TipoEmision::Normal,
        public readonly ?string $nombreComercial = null,
    ) {
        if (strlen($ruc) !== 13 || !ctype_digit($ruc)) {
            throw new ValidationException("InfoTributaria: RUC inválido '$ruc' (deben ser 13 dígitos).");
        }
        if ($razonSocial === '') {
            throw new ValidationException('InfoTributaria: "razonSocial" es obligatoria.');
        }
        foreach (['estab' => $estab, 'ptoEmi' => $ptoEmi] as $campo => $valor) {
            if (strlen($valor) !== 3 || !ctype_digit($valor)) {
                throw new ValidationException("InfoTributaria: \"$campo\" debe tener 3 dígitos.");
            }
        }
        if (strlen($secuencial) !== 9 || !ctype_digit($secuencial)) {
            throw new ValidationException('InfoTributaria: "secuencial" debe tener 9 dígitos.');
        }
    }

    public static function fromArray(array $data): self
    {
        $ambienteCodigo = (string) ($data['ambiente'] ?? '');
        $ambiente = Ambiente::tryFrom($ambienteCodigo);
        if ($ambiente === null) {
            throw new ValidationException("InfoTributaria: ambiente inválido '$ambienteCodigo' (use '1' o '2').");
        }

        $tipoEmision = TipoEmision::tryFrom((string) ($data['tipoEmision'] ?? '1')) ?? TipoEmision::Normal;

        return new self(
            ambiente: $ambiente,
            razonSocial: (string) ($data['razonSocial'] ?? ''),
            ruc: (string) ($data['ruc'] ?? ''),
            estab: (string) ($data['estab'] ?? ''),
            ptoEmi: (string) ($data['ptoEmi'] ?? ''),
            secuencial: (string) ($data['secuencial'] ?? ''),
            dirMatriz: (string) ($data['dirMatriz'] ?? ''),
            tipoEmision: $tipoEmision,
            nombreComercial: isset($data['nombreComercial']) ? (string) $data['nombreComercial'] : null,
        );
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Documents/InfoTributariaTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Documents/InfoTributaria.php tests/Unit/Documents/InfoTributariaTest.php
git commit -m "feat: add InfoTributaria document DTO with validation"
```

---

### Task 8: Agregado raíz `Factura` + `::fromArray()`

**Files:**
- Create: `src/Documents/Factura.php`
- Test: `tests/Unit/Documents/FacturaTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Exceptions\ValidationException;

class FacturaTest extends TestCase
{
    private function validData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'MI EMPRESA S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Quito, Ecuador',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE FINAL',
                'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '100.00',
                'totalDescuento' => '0.00',
                'importeTotal' => '115.00',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'PROD001',
                    'descripcion' => 'Producto de prueba',
                    'cantidad' => '1.00',
                    'precioUnitario' => '100.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '100.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
                    ],
                ],
            ],
        ];
    }

    public function test_from_array_builds_full_aggregate(): void
    {
        $factura = Factura::fromArray($this->validData());

        $this->assertSame('1790011001001', $factura->infoTributaria->ruc);
        $this->assertSame('26/01/2026', $factura->fechaEmision);
        $this->assertCount(1, $factura->detalles);
        $this->assertInstanceOf(Detalle::class, $factura->detalles[0]);
        $this->assertCount(1, $factura->pagos);
        $this->assertInstanceOf(Pago::class, $factura->pagos[0]);
        $this->assertSame('115.00', $factura->importeTotal->format(2));
    }

    public function test_rejects_factura_without_detalles(): void
    {
        $data = $this->validData();
        $data['detalles'] = [];

        $this->expectException(ValidationException::class);
        Factura::fromArray($data);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `./vendor/bin/phpunit tests/Unit/Documents/FacturaTest.php`
Expected: FAIL con `Class "Teran\Sri\Documents\Factura" not found`.

- [ ] **Step 3: Implementar `Factura`**

```php
<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Factura
{
    /**
     * @param Detalle[] $detalles
     * @param Impuesto[] $totalConImpuestos
     * @param Pago[] $pagos
     */
    public function __construct(
        public readonly InfoTributaria $infoTributaria,
        public readonly string $fechaEmision,
        public readonly string $tipoIdentificacionComprador,
        public readonly string $razonSocialComprador,
        public readonly string $identificacionComprador,
        public readonly Money $totalSinImpuestos,
        public readonly Money $totalDescuento,
        public readonly Money $importeTotal,
        public readonly array $totalConImpuestos,
        public readonly array $detalles,
        public readonly array $pagos,
        public readonly string $obligadoContabilidad = 'NO',
    ) {
        if ($detalles === []) {
            throw new ValidationException('Factura: debe tener al menos un detalle.');
        }
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmision)) {
            throw new ValidationException("Factura: fechaEmision inválida '$fechaEmision' (formato dd/MM/yyyy).");
        }
        foreach ($detalles as $d) {
            if (!$d instanceof Detalle) {
                throw new ValidationException('Factura: cada detalle debe ser instancia de Detalle.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $info = InfoTributaria::fromArray($data['infoTributaria'] ?? []);
        $f = $data['infoFactura'] ?? [];

        $totalConImpuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $f['totalConImpuestos'] ?? [],
        );
        $detalles = array_map(
            static fn (array $det): Detalle => Detalle::fromArray($det),
            $data['detalles'] ?? [],
        );
        $pagos = array_map(
            static fn (array $pago): Pago => Pago::fromArray($pago),
            $f['pagos'] ?? [],
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: (string) ($f['fechaEmision'] ?? ''),
            tipoIdentificacionComprador: (string) ($f['tipoIdentificacionComprador'] ?? ''),
            razonSocialComprador: (string) ($f['razonSocialComprador'] ?? ''),
            identificacionComprador: (string) ($f['identificacionComprador'] ?? ''),
            totalSinImpuestos: Money::of($f['totalSinImpuestos'] ?? 0),
            totalDescuento: Money::of($f['totalDescuento'] ?? 0),
            importeTotal: Money::of($f['importeTotal'] ?? $f['importetotal'] ?? 0),
            totalConImpuestos: $totalConImpuestos,
            detalles: $detalles,
            pagos: $pagos,
            obligadoContabilidad: (string) ($f['obligadoContabilidad'] ?? 'NO'),
        );
    }
}
```

> Nota: `fromArray` acepta `importeTotal` y el legacy `importetotal` (1.x usaba minúscula) para máxima compatibilidad de migración.

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `./vendor/bin/phpunit tests/Unit/Documents/FacturaTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Correr la suite completa (sin regresiones)**

Run: `./vendor/bin/phpunit`
Expected: PASS — todos los tests nuevos verdes y los existentes intactos (1 skip documentado de `version`).

- [ ] **Step 6: Commit**

```bash
git add src/Documents/Factura.php tests/Unit/Documents/FacturaTest.php
git commit -m "feat: add Factura aggregate root with fromArray"
```

---

## Self-Review

**1. Cobertura del spec (§4.2 "Modelo de documentos"):**
- DTOs `readonly` inmutables que validan en el constructor → Tasks 4-8. ✅
- `::fromArray()` con las mismas llaves de 1.x → cada DTO. ✅ (incluye alias `importetotal` legacy).
- `Monto` decimal-seguro (sin float) → Task 2 (`Money`, bcmath). ✅
- Catálogos como enums → Task 3. ✅
- Cubre **Factura** (alcance declarado). NC/ND/Guía/Retención → plan posterior (declarado en "Contexto y alcance"). ✅

**2. Placeholders:** ninguno; cada step trae código completo y comandos con salida esperada. ✅

**3. Consistencia de tipos:** `Money::of()`, `->format(int)`, `->plus()`, `->times()` usados consistentemente; `Impuesto`/`Detalle`/`Pago`/`InfoTributaria`/`Factura` con las mismas firmas en sus tests y consumidores; enums `Ambiente`/`TipoComprobante`/`FormaPago`/`TipoEmision` con `tryFrom`/`from`/`value`. ✅

**4. Namespaces:** `Teran\Sri\Money`, `Teran\Sri\Catalogs2`, `Teran\Sri\Documents` — todos bajo el PSR-4 existente `Teran\Sri\` → `src/`; `Catalogs2` evita colisión con `src/Catalogs/` de 1.x. ✅

**Decisión abierta para el plan siguiente:** el `FacturaXmlSerializer` consumirá este modelo y debe reproducir **byte-idéntico** el XML válido del SRI (con el `createTextElement` ya endurecido), e incluir el golden test contra `factura_v2.1.0.xsd`.
