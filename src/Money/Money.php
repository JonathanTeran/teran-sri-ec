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
