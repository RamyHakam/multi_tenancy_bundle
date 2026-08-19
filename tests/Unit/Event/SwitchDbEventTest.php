<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use PHPUnit\Framework\TestCase;

class SwitchDbEventTest extends TestCase
{
    public function testItCarriesTheIdentifierItWasGiven(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('tenant_db');

        $event = new SwitchDbEvent($identifier);

        $this->assertTrue($identifier->equals($event->getIdentifier()));
        $this->assertSame((string) $identifier, $event->getDbIndex());
    }

    public function testItAcceptsTheStringFormSoConsoleArgumentsKeepWorking(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('tenant_db');

        $event = new SwitchDbEvent((string) $identifier);

        $this->assertTrue($identifier->equals($event->getIdentifier()));
    }

    public function testANullIdentifierStaysNull(): void
    {
        $event = new SwitchDbEvent(null);

        $this->assertNull($event->getIdentifier());
        $this->assertNull($event->getDbIndex());
    }
}
