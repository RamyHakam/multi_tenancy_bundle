<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Enum;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use PHPUnit\Framework\TestCase;

class DatabaseStatusEnumTest extends TestCase
{
    public function testEnumHasCorrectCases(): void
    {
        $cases = DatabaseStatusEnum::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(DatabaseStatusEnum::DATABASE_MIGRATED, $cases);
        $this->assertContains(DatabaseStatusEnum::DATABASE_CREATED, $cases);
        $this->assertContains(DatabaseStatusEnum::DATABASE_NOT_CREATED, $cases);
    }

    public function testDatabaseMigratedValue(): void
    {
        $this->assertSame('DATABASE_MIGRATED', DatabaseStatusEnum::DATABASE_MIGRATED->value);
    }

    public function testDatabaseCreatedValue(): void
    {
        $this->assertSame('DATABASE_CREATED', DatabaseStatusEnum::DATABASE_CREATED->value);
    }

    public function testDatabaseNotCreatedValue(): void
    {
        $this->assertSame('DATABASE_NOT_CREATED', DatabaseStatusEnum::DATABASE_NOT_CREATED->value);
    }

    public function testEnumCanBeCreatedFromValue(): void
    {
        $enum = DatabaseStatusEnum::from('DATABASE_MIGRATED');

        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $enum);
    }

    public function testEnumFromValueThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);

        DatabaseStatusEnum::from('INVALID_STATUS');
    }

    public function testEnumTryFromReturnsNullForInvalidValue(): void
    {
        $result = DatabaseStatusEnum::tryFrom('INVALID_STATUS');

        $this->assertNull($result);
    }

    public function testEnumTryFromReturnsEnumForValidValue(): void
    {
        $result = DatabaseStatusEnum::tryFrom('DATABASE_CREATED');

        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $result);
    }

    public function testEnumInstanceComparison(): void
    {
        $status1 = DatabaseStatusEnum::DATABASE_MIGRATED;
        $status2 = DatabaseStatusEnum::DATABASE_MIGRATED;
        $status3 = DatabaseStatusEnum::DATABASE_CREATED;

        $this->assertTrue($status1 === $status2);
        $this->assertFalse($status1 === $status3);
    }

    public function testEnumInArrayCheck(): void
    {
        $validStatuses = [
            DatabaseStatusEnum::DATABASE_MIGRATED,
            DatabaseStatusEnum::DATABASE_CREATED
        ];

        $this->assertTrue(in_array(DatabaseStatusEnum::DATABASE_MIGRATED, $validStatuses, true));
        $this->assertTrue(in_array(DatabaseStatusEnum::DATABASE_CREATED, $validStatuses, true));
        $this->assertFalse(in_array(DatabaseStatusEnum::DATABASE_NOT_CREATED, $validStatuses, true));
    }

    public function testEnumInSwitchStatement(): void
    {
        $status = DatabaseStatusEnum::DATABASE_CREATED;

        $result = match($status) {
            DatabaseStatusEnum::DATABASE_NOT_CREATED => 'not_created',
            DatabaseStatusEnum::DATABASE_CREATED => 'created',
            DatabaseStatusEnum::DATABASE_MIGRATED => 'migrated',
        };

        $this->assertSame('created', $result);
    }
}
