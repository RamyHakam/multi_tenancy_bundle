<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\EventListener\DbSwitchEventListener;
use Hakam\MultiTenancyBundle\Test\TenantTestTrait;

class TenantTestTraitTest extends IntegrationTestCase
{
    use TenantTestTrait {
        getTenantEntityManager as traitGetTenantEntityManager;
    }

    public function testRunInTenantExecutesCallback(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'trait_test_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $result = $this->runInTenant((string) $tenant->getId(), function () {
            return 'callback_executed';
        });

        $this->assertSame('callback_executed', $result);
    }

    public function testRunInTenantResetsStateAfterExecution(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'trait_reset_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $this->runInTenant((string) $tenant->getId(), function () {
            // do nothing
        });

        // After runInTenant, the listener's internal state should be reset
        $listener = $this->getContainer()->get(DbSwitchEventListener::class);
        $ref = new \ReflectionProperty($listener, 'currentTenantIdentifier');
        $this->assertNull($ref->getValue($listener));

        $refDb = new \ReflectionProperty($listener, 'currentTenantDbName');
        $this->assertNull($refDb->getValue($listener));
    }

    public function testRunInTenantResetsStateOnException(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'trait_exception_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        try {
            $this->runInTenant((string) $tenant->getId(), function () {
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // State should still be reset despite the exception
        $listener = $this->getContainer()->get(DbSwitchEventListener::class);
        $ref = new \ReflectionProperty($listener, 'currentTenantIdentifier');
        $this->assertNull($ref->getValue($listener));
    }

    public function testSwitchToTenantAndManualReset(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'trait_manual_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $this->switchToTenant((string) $tenant->getId());

        // Listener should have the tenant set
        $listener = $this->getContainer()->get(DbSwitchEventListener::class);
        $ref = new \ReflectionProperty($listener, 'currentTenantIdentifier');
        $this->assertNotNull($ref->getValue($listener));

        // Manual reset
        $this->resetTenantState();

        $this->assertNull($ref->getValue($listener));
    }

    public function testSequentialRunInTenantCalls(): void
    {
        $tenant1 = $this->insertTenantConfig(
            dbName: 'trait_seq_one',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenant2 = $this->insertTenantConfig(
            dbName: 'trait_seq_two',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $result1 = $this->runInTenant((string) $tenant1->getId(), function () {
            return 'first';
        });

        $result2 = $this->runInTenant((string) $tenant2->getId(), function () {
            return 'second';
        });

        $this->assertSame('first', $result1);
        $this->assertSame('second', $result2);

        // Listener state should be clean after both calls
        $listener = $this->getContainer()->get(DbSwitchEventListener::class);
        $ref = new \ReflectionProperty($listener, 'currentTenantIdentifier');
        $this->assertNull($ref->getValue($listener));
    }

    public function testResetTenantStateIsIdempotent(): void
    {
        // Calling resetTenantState when no tenant is active should not throw
        $this->resetTenantState();
        $this->resetTenantState();

        $this->assertTrue(true, 'resetTenantState is safe to call when no tenant is active');
    }
}
