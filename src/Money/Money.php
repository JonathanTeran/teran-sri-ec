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

        // $str is now guaranteed numeric (is_numeric check above); bcadd requires numeric-string.
        /** @var numeric-string $numericStr */
        $numericStr = $str;

        // Normalizar a la escala interna con bcmath.
        return new self(bcadd($numericStr, '0', self::INTERNAL_SCALE));
    }

    public function plus(self $other): self
    {
        /** @var numeric-string $a */
        $a = $this->amount;
        /** @var numeric-string $b */
        $b = $other->amount;
        return new self(bcadd($a, $b, self::INTERNAL_SCALE));
    }

    public function times(string|int|float $factor): self
    {
        $str = is_float($factor)
            ? number_format($factor, self::INTERNAL_SCALE, '.', '')
            : (string) $factor;

        if (!is_numeric($str)) {
            throw new ValidationException("Factor no numérico: '$str'.");
        }

        /** @var numeric-string $numericStr */
        $numericStr = $str;
        /** @var numeric-string $a */
        $a = $this->amount;

        return new self(bcmul($a, $numericStr, self::INTERNAL_SCALE));
    }

    /**
     * Devuelve el monto formateado a $decimals con redondeo half-up (como el SRI).
     *
     * @throws \InvalidArgumentException si $decimals supera la escala interna.
     */
    public function format(int $decimals): string
    {
        if ($decimals > self::INTERNAL_SCALE) {
            throw new \InvalidArgumentException(
                'Money::format() admite máximo ' . self::INTERNAL_SCALE . ' decimales.'
            );
        }

        // Rounding deltas for half-up per scale: for 2 decimals → '0.005', for 6 → '0.0000005'.
        // PHPStan stubs require numeric-string for bcadd/bcsub; string literals below are numeric.
        /** @var array<int, numeric-string> $halves */
        $halves = ['0.5', '0.05', '0.005', '0.0005', '0.00005', '0.000005', '0.0000005'];
        $roundingDelta = $halves[$decimals] ?? bcpow('10', (string) -($decimals + 1), $decimals + 1);
        /** @var numeric-string $a */
        $a = $this->amount;
        $rounded = $this->amount[0] === '-'
            ? bcsub($a, $roundingDelta, $decimals)
            : bcadd($a, $roundingDelta, $decimals);

        return $rounded;
    }

    /**
     * Formatea a 2 decimales (half-up). Para otras escalas usar {@see format(int)}.
     */
    public function __toString(): string
    {
        return $this->format(2);
    }
}
