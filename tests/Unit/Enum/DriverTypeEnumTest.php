<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Enum;

use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use PHPUnit\Framework\TestCase;

class DriverTypeEnumTest extends TestCase
{
    public function testEnumHasCorrectCases(): void
    {
        $cases = DriverTypeEnum::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(DriverTypeEnum::MYSQL, $cases);
        $this->assertContains(DriverTypeEnum::POSTGRES, $cases);
        $this->assertContains(DriverTypeEnum::SQLITE, $cases);
    }

    public function testMysqlValue(): void
    {
        $this->assertSame('mysql', DriverTypeEnum::MYSQL->value);
    }

    public function testPostgresValue(): void
    {
        $this->assertSame('postgresql', DriverTypeEnum::POSTGRES->value);
    }

    public function testSqliteValue(): void
    {
        $this->assertSame('sqlite', DriverTypeEnum::SQLITE->value);
    }

    public function testEnumCanBeCreatedFromValue(): void
    {
        $mysql = DriverTypeEnum::from('mysql');
        $postgres = DriverTypeEnum::from('postgresql');
        $sqlite = DriverTypeEnum::from('sqlite');

        $this->assertSame(DriverTypeEnum::MYSQL, $mysql);
        $this->assertSame(DriverTypeEnum::POSTGRES, $postgres);
        $this->assertSame(DriverTypeEnum::SQLITE, $sqlite);
    }

    public function testEnumFromValueThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);

        DriverTypeEnum::from('oracle');
    }

    public function testEnumTryFromReturnsNullForInvalidValue(): void
    {
        $result = DriverTypeEnum::tryFrom('mongodb');

        $this->assertNull($result);
    }

    public function testEnumTryFromReturnsEnumForValidValue(): void
    {
        $result = DriverTypeEnum::tryFrom('postgresql');

        $this->assertSame(DriverTypeEnum::POSTGRES, $result);
    }

    public function testEnumInstanceComparison(): void
    {
        $driver1 = DriverTypeEnum::MYSQL;
        $driver2 = DriverTypeEnum::MYSQL;
        $driver3 = DriverTypeEnum::POSTGRES;

        $this->assertTrue($driver1 === $driver2);
        $this->assertFalse($driver1 === $driver3);
    }

    public function testEnumInArrayCheck(): void
    {
        $supportedDrivers = [
            DriverTypeEnum::MYSQL,
            DriverTypeEnum::POSTGRES
        ];

        $this->assertTrue(in_array(DriverTypeEnum::MYSQL, $supportedDrivers, true));
        $this->assertTrue(in_array(DriverTypeEnum::POSTGRES, $supportedDrivers, true));
        $this->assertFalse(in_array(DriverTypeEnum::SQLITE, $supportedDrivers, true));
    }

    public function testEnumInMatchExpression(): void
    {
        $driver = DriverTypeEnum::POSTGRES;

        $port = match($driver) {
            DriverTypeEnum::MYSQL => 3306,
            DriverTypeEnum::POSTGRES => 5432,
            DriverTypeEnum::SQLITE => 0,
        };

        $this->assertSame(5432, $port);
    }

    public function testAllDriversHaveUniqueValues(): void
    {
        $values = array_map(fn($case) => $case->value, DriverTypeEnum::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues);
    }

    public function testDriverValuesAreLowercase(): void
    {
        foreach (DriverTypeEnum::cases() as $driver) {
            $this->assertSame(strtolower($driver->value), $driver->value);
        }
    }
}
