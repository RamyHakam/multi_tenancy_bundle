<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Event\AbstractTenantEvent;
use Hakam\MultiTenancyBundle\Event\TenantDeletedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

class TenantDeletedEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $event = new TenantDeletedEvent('tenant_1', 'tenant_1_db');

        $this->assertSame('tenant_1', $event->getTenantIdentifier());
        $this->assertSame('tenant_1_db', $event->getDatabaseName());
    }

    public function testTenantConfigIsNullByDefault(): void
    {
        $event = new TenantDeletedEvent('tenant_1', 'tenant_1_db');

        $this->assertNull($event->getTenantConfig());
    }

    public function testExtendsAbstractTenantEvent(): void
    {
        $event = new TenantDeletedEvent('tenant_1', 'tenant_1_db');

        $this->assertInstanceOf(AbstractTenantEvent::class, $event);
        $this->assertInstanceOf(Event::class, $event);
    }
}
