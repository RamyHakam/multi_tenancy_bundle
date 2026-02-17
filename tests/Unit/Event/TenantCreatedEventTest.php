<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\AbstractTenantEvent;
use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

class TenantCreatedEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_1',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_1_db',
            'root',
            'secret'
        );

        $event = new TenantCreatedEvent('tenant_1', $config, 'tenant_1_db');

        $this->assertSame('tenant_1', $event->getTenantIdentifier());
        $this->assertSame($config, $event->getTenantConfig());
        $this->assertSame('tenant_1_db', $event->getDatabaseName());
    }

    public function testOccurredAtIsSet(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_1',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_1_db',
            'root'
        );

        $before = new \DateTimeImmutable();
        $event = new TenantCreatedEvent('tenant_1', $config, 'tenant_1_db');
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
        $this->assertGreaterThanOrEqual($before, $event->getOccurredAt());
        $this->assertLessThanOrEqual($after, $event->getOccurredAt());
    }

    public function testExtendsAbstractTenantEvent(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_1',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_1_db',
            'root'
        );

        $event = new TenantCreatedEvent('tenant_1', $config, 'tenant_1_db');

        $this->assertInstanceOf(AbstractTenantEvent::class, $event);
        $this->assertInstanceOf(Event::class, $event);
    }
}
