<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\ValueObject;

use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TenantDatabaseIdentifierTest extends TestCase
{
    public function testCreateAcceptsTheStoredBareUuid(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('tenant_db');

        $this->assertTrue($identifier->equals(TenantDatabaseIdentifier::create((string) $identifier)));
    }

    public function testCreateAcceptsAPrefixedIdentifier(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('tenant_db');

        $this->assertTrue($identifier->equals(TenantDatabaseIdentifier::create('tenant:' . $identifier)));
    }

    public function testCreateRejectsAValueThatIsNotAUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantDatabaseIdentifier::create('12');
    }

    public function testGenerateWithValueIsStableForTheSameValue(): void
    {
        $this->assertTrue(
            TenantDatabaseIdentifier::generateWithValue('tenant_db')
                ->equals(TenantDatabaseIdentifier::generateWithValue('tenant_db')),
        );
    }

    public function testGenerateWithValueDiffersPerValue(): void
    {
        $this->assertFalse(
            TenantDatabaseIdentifier::generateWithValue('tenant_one')
                ->equals(TenantDatabaseIdentifier::generateWithValue('tenant_two')),
        );
    }
}
