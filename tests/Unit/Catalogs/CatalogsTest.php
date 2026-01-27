<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Catalogs;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Catalogs\ContributorTypes;
use Teran\Sri\Catalogs\IdentificationTypes;
use Teran\Sri\Catalogs\PaymentMethods;
use Teran\Sri\Catalogs\Provinces;
use Teran\Sri\Catalogs\RetentionCodes;
use Teran\Sri\Catalogs\SriConfig;
use Teran\Sri\Catalogs\TaxSupport;

class CatalogsTest extends TestCase
{
    // ========================================
    // PaymentMethods Tests
    // ========================================

    public function test_payment_methods_returns_valid_name(): void
    {
        $this->assertEquals(
            'Sin utilización del sistema financiero (Efectivo)',
            PaymentMethods::getName('01')
        );
        $this->assertEquals('Tarjeta de crédito', PaymentMethods::getName('19'));
    }

    public function test_payment_methods_validates_codes(): void
    {
        $this->assertTrue(PaymentMethods::isValid('01'));
        $this->assertTrue(PaymentMethods::isValid('19'));
        $this->assertFalse(PaymentMethods::isValid('99'));
    }

    // ========================================
    // IdentificationTypes Tests
    // ========================================

    public function test_identification_types_returns_valid_info(): void
    {
        $this->assertEquals('RUC', IdentificationTypes::getName('04'));
        $this->assertEquals('Cédula de Identidad', IdentificationTypes::getName('05'));
        $this->assertEquals(13, IdentificationTypes::getLength('04'));
        $this->assertEquals(10, IdentificationTypes::getLength('05'));
    }

    public function test_identification_types_detects_type(): void
    {
        $this->assertEquals('05', IdentificationTypes::detectType('1712345678'));
        $this->assertEquals('04', IdentificationTypes::detectType('1790016919001'));
        $this->assertEquals('07', IdentificationTypes::detectType('9999999999999'));
    }

    // ========================================
    // RetentionCodes Tests
    // ========================================

    public function test_retention_codes_renta_returns_valid_info(): void
    {
        $renta = RetentionCodes::getRenta('303');
        $this->assertNotNull($renta);
        $this->assertEquals('Honorarios Profesionales', $renta['name']);
        $this->assertEquals(10.0, $renta['percentage']);
    }

    public function test_retention_codes_iva_returns_valid_info(): void
    {
        $iva = RetentionCodes::getIva('3');
        $this->assertNotNull($iva);
        $this->assertEquals(100.0, $iva['percentage']);
    }

    public function test_retention_codes_calculates_correctly(): void
    {
        // Retención renta 10% sobre $100
        $this->assertEquals(10.0, RetentionCodes::calculateRenta('303', 100.0));

        // Retención IVA 30% sobre $15 de IVA
        $this->assertEquals(4.5, RetentionCodes::calculateIva('1', 15.0));
    }

    // ========================================
    // ContributorTypes Tests
    // ========================================

    public function test_contributor_types_returns_valid_info(): void
    {
        $this->assertEquals('Sociedad', ContributorTypes::getName('03'));
        $this->assertTrue(ContributorTypes::isObligadoContabilidad('03'));
        $this->assertFalse(ContributorTypes::isRimpe('03'));
    }

    public function test_contributor_types_rimpe_detection(): void
    {
        $this->assertTrue(ContributorTypes::isRimpe('06')); // RIMPE Emprendedor
        $this->assertTrue(ContributorTypes::isRimpe('07')); // RIMPE Negocio Popular
        $this->assertFalse(ContributorTypes::isRimpe('01')); // Persona Natural

        $this->assertEquals('343A', ContributorTypes::getRimpeRetentionCode('06'));
        $this->assertEquals('332B', ContributorTypes::getRimpeRetentionCode('07'));
    }

    // ========================================
    // Provinces Tests
    // ========================================

    public function test_provinces_returns_valid_info(): void
    {
        $this->assertEquals('Pichincha', Provinces::getName('17'));
        $this->assertEquals('Quito', Provinces::getCapital('17'));
        $this->assertEquals('sierra', Provinces::getRegion('17'));
    }

    public function test_provinces_extracts_from_ruc(): void
    {
        // RUC de Pichincha
        $this->assertEquals('17', Provinces::extractFromRuc('1790016919001'));
        // RUC de Guayas
        $this->assertEquals('09', Provinces::extractFromRuc('0990123456001'));
    }

    public function test_provinces_validates_ruc_province(): void
    {
        $this->assertTrue(Provinces::validateRucProvince('1790016919001'));
        $this->assertFalse(Provinces::validateRucProvince('9990016919001')); // 99 no es provincia válida
    }

    // ========================================
    // TaxSupport Tests
    // ========================================

    public function test_tax_support_returns_valid_info(): void
    {
        $this->assertEquals(
            'Crédito Tributario para declaración de IVA',
            TaxSupport::getName('01')
        );
        $this->assertTrue(TaxSupport::allowsCreditoIva('01'));
        $this->assertFalse(TaxSupport::allowsCostoGastoIr('01'));
    }

    public function test_tax_support_defaults(): void
    {
        $this->assertEquals('01', TaxSupport::getDefaultForPurchase());
        $this->assertEquals('03', TaxSupport::getDefaultForFixedAsset());
        $this->assertEquals('06', TaxSupport::getDefaultForInventory());
    }

    // ========================================
    // SriConfig Tests
    // ========================================

    public function test_sri_config_constants(): void
    {
        $this->assertEquals(15.0, SriConfig::IVA_RATE);
        $this->assertEquals(482.00, SriConfig::SBU);
        $this->assertEquals('9999999999999', SriConfig::CONSUMIDOR_FINAL_RUC);
    }

    public function test_sri_config_urls(): void
    {
        $this->assertStringContainsString('celcer', SriConfig::getReceptionUrl(false));
        $this->assertStringContainsString('cel.sri', SriConfig::getReceptionUrl(true));
    }

    public function test_sri_config_deadline_calculation(): void
    {
        // RUC con noveno dígito 1 -> vence día 10
        $this->assertEquals(10, SriConfig::getDeadlineDay('179001691'));

        // RUC con noveno dígito 9 -> vence día 26
        $this->assertEquals(26, SriConfig::getDeadlineDay('170000009'));
    }

    public function test_sri_config_iess_calculations(): void
    {
        $salario = 500.0;

        // Aporte personal 9.45%
        $this->assertEquals(47.25, SriConfig::calculateAportePersonal($salario));

        // Aporte patronal 11.15%
        $this->assertEquals(55.75, SriConfig::calculateAportePatronal($salario));
    }

    public function test_sri_config_customer_identification_requirement(): void
    {
        $this->assertFalse(SriConfig::requiresCustomerIdentification(50.0));
        $this->assertTrue(SriConfig::requiresCustomerIdentification(50.01));
        $this->assertTrue(SriConfig::requiresCustomerIdentification(100.0));
    }
}
