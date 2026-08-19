<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The database manager creates the databases themselves; reading and writing
 * their configuration rows is DoctrineTenantConnectionManager's job.
 */
class DoctrineTenantDatabaseManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private DoctrineDBALConnectionGeneratorInterface&MockObject $connGen;
    private DoctrineTenantDatabaseManager $manager;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->connGen = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);

        $this->manager = new DoctrineTenantDatabaseManager(
            $this->em,
            $this->connGen,
        );
    }

    public function testCreateTenantDatabaseWrapsExceptions(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: TenantDatabaseIdentifier::generateWithValue('db'),
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: 'h',
            port: 3306,
            dbname: 'db',
            user: 'u',
            password: 'p',
        );

        $this->connGen->method('generateMaintenanceConnection')
            ->willThrowException(new Exception('err'));

        $this->expectException(MultiTenancyException::class);

        $this->manager->createTenantDatabase($dto);
    }
}
