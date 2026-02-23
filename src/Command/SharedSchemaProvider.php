<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

/**
 * A Doctrine Migrations SchemaProvider that exposes ONLY #[TenantShared] entities.
 *
 * Used by SharedDiffCommand to generate a migration diff that covers only the
 * shared schema, leaving tenant-specific entities untouched.
 *
 * Driver-specific behaviour:
 *  - MySQL:      tables are unqualified (we're connected directly to the shared database).
 *  - PostgreSQL: tables are schema-qualified ("shared"."table_name") since the shared
 *                schema is a Postgres schema inside the application database.
 *  - SQLite:     same as MySQL (no separate schemas).
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class SharedSchemaProvider implements SchemaProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $sharedSchemaName,
        private readonly DriverTypeEnum $driver,
    ) {
    }

    public function createSchema(): Schema
    {
        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
        $sharedMetadata = [];

        foreach ($allMetadata as $metadata) {
            $reflClass = $metadata->getReflectionClass();

            if (!$reflClass || empty($reflClass->getAttributes(TenantShared::class))) {
                // Hide non-shared entities from the schema comparison.
                $metadata->isMappedSuperclass = true;
                continue;
            }

            // Set the correct schema qualifier for the driver.
            if ($this->driver === DriverTypeEnum::POSTGRES) {
                $metadata->setPrimaryTable([
                    'name' => $metadata->getTableName(),
                    'schema' => $this->sharedSchemaName,
                ]);
            } else {
                // MySQL / SQLite: no schema prefix — we're connected to the shared DB directly.
                $metadata->setPrimaryTable(['name' => $metadata->getTableName()]);
            }

            $sharedMetadata[] = $metadata;
        }

        return (new SchemaTool($this->em))->getSchemaFromMetadata($sharedMetadata);
    }
}
