<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Hakam\MultiTenancyBundle\Doctrine\DBAL\Types\TenantDatabaseIdentifierType;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use PHPUnit\Framework\TestCase;

class TenantDatabaseIdentifierTypeTest extends TestCase
{
    public function testTheValueItWritesIsAValueItCanReadBack(): void
    {
        $type = new TenantDatabaseIdentifierType();
        $platform = $this->createMock(AbstractPlatform::class);
        $identifier = TenantDatabaseIdentifier::generateWithValue('tenant_db');

        $stored = $type->convertToDatabaseValue($identifier, $platform);

        $this->assertTrue($identifier->equals($type->convertToPHPValue($stored, $platform)));
    }
}
