<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Utils\RucValidator;
use Teran\Sri\Schema\BusinessValidator;

class RucValidatorTest extends TestCase
{
    /** 
     * @var RucValidator 
     */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new RucValidator();
    }

    public function test_validate_returns_true_for_valid_local_ruc()
    {
        // RUC válido de prueba (Algoritmo local)
        // 1790011001001 es comúnmente usado en ejemplos
        $ruc = '1790011001001';
        $this->assertTrue($this->validator->validate($ruc));
    }

    public function test_validate_returns_false_for_invalid_ruc_structure()
    {
        $ruc = '123'; // Longitud incorrecta
        $this->assertFalse($this->validator->validate($ruc));
    }

    // Nota: Testear el checkOnline real en unit tests no es recomendado sin mocking de HTTPClient.
    // Para esta iteración probaremos la lógica de fallback hacia la validación local.
}
