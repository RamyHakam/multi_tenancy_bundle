<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

class SwitchDbEventTest extends TestCase
{
    public function testConstructorAndGetDbIndex(): void
    {
        $tenantDbIndex = '123';
        
        $event = new SwitchDbEvent($tenantDbIndex);
        
        $this->assertSame($tenantDbIndex, $event->getDbIndex());
    }

    public function testConstructorWithNullDbIndex(): void
    {
        $event = new SwitchDbEvent(null);
        
        $this->assertNull($event->getDbIndex());
    }

    public function testConstructorWithNumericStringDbIndex(): void
    {
        $tenantDbIndex = '456';
        
        $event = new SwitchDbEvent($tenantDbIndex);
        
        $this->assertSame($tenantDbIndex, $event->getDbIndex());
        $this->assertIsString($event->getDbIndex());
    }

    public function testConstructorWithAlphanumericDbIndex(): void
    {
        $tenantDbIndex = 'tenant_abc_123';
        
        $event = new SwitchDbEvent($tenantDbIndex);
        
        $this->assertSame($tenantDbIndex, $event->getDbIndex());
    }

    public function testEventExtendsSymfonyEvent(): void
    {
        $event = new SwitchDbEvent('test');
        
        $this->assertInstanceOf(Event::class, $event);
    }

    public function testEventCanBeStopped(): void
    {
        $event = new SwitchDbEvent('test');
        
        $this->assertFalse($event->isPropagationStopped());
        
        $event->stopPropagation();
        
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testMultipleInstancesWithDifferentIndexes(): void
    {
        $event1 = new SwitchDbEvent('tenant1');
        $event2 = new SwitchDbEvent('tenant2');
        $event3 = new SwitchDbEvent(null);
        
        $this->assertSame('tenant1', $event1->getDbIndex());
        $this->assertSame('tenant2', $event2->getDbIndex());
        $this->assertNull($event3->getDbIndex());
    }
}
