<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Context;

use Hakam\MultiTenancyBundle\Context\TenantContext;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    public function testGetTenantIdReturnsNullByDefault(): void
    {
        $context = new TenantContext();

        $this->assertNull($context->getTenantId());
    }

    public function testOnTenantSwitchedSetsTenantId(): void
    {
        $context = new TenantContext();

        $event = $this->createMock(TenantSwitchedEvent::class);
        $event->method('getTenantIdentifier')->willReturn('tenant_42');

        $context->onTenantSwitched($event);

        $this->assertSame('tenant_42', $context->getTenantId());
    }

    public function testResetClearsTenantId(): void
    {
        $context = new TenantContext();

        $event = $this->createMock(TenantSwitchedEvent::class);
        $event->method('getTenantIdentifier')->willReturn('tenant_42');
        $context->onTenantSwitched($event);

        $this->assertSame('tenant_42', $context->getTenantId());

        $context->reset();

        $this->assertNull($context->getTenantId());
    }

    public function testOnTenantSwitchedCastsIdentifierToString(): void
    {
        $context = new TenantContext();

        $event = $this->createMock(TenantSwitchedEvent::class);
        $event->method('getTenantIdentifier')->willReturn(123);

        $context->onTenantSwitched($event);

        $this->assertSame('123', $context->getTenantId());
    }
}
