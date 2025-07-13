<?php

namespace Hakam\MultiTenancyBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;

class TenantDatabaseIdentifierType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL([
            'length' => 50,
            'fixed' => true,
        ]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): TenantDatabaseIdentifier
    {
        return  TenantDatabaseIdentifier::create($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        return (string) $value;
    }

    public function getName(): string
    {
        return 'tenant_identifier';
    }
}