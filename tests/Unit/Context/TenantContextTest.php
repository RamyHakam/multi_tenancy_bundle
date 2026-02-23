<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Context;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Context\TenantContext;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    /**
     * Build a TenantSwitchedEvent mock that carries both an identifier and a
     * TenantConnectionConfigDTO with the given db name.
     */
    private function makeSwitchedEvent(mixed $identifier, string $dbName = 'test_db'): TenantSwitchedEvent
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            identifier: $identifier,
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 3306,
            dbname: $dbName,
            user: 'user',
        );

        $event = $this->createMock(TenantSwitchedEvent::class);
        $event->method('getTenantIdentifier')->willReturn($identifier);
        $event->method('getTenantConfig')->willReturn($config);

        return $event;
    }


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

    public function testGetSchemaReturnsRealDbName(): void
    {
        $context = new TenantContext();
        $context->onTenantSwitched($this->makeSwitchedEvent('42', 'acme_corp'));

        $this->assertSame('acme_corp', $context->getSchema());
    }

    public function testGetSchemaIsIndependentOfTenantId(): void
    {
        // The schema must come from the DTO dbname, never from the raw identifier.
        $context = new TenantContext();
        $context->onTenantSwitched($this->makeSwitchedEvent('1', 'production_tenant_db'));

        $this->assertSame('production_tenant_db', $context->getSchema());
        $this->assertNotSame('tenant_1', $context->getSchema());
    }

    public function testGetSchemaThrowsWhenNoTenantIsActive(): void
    {
        $context = new TenantContext();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no tenant is currently active');

        $context->getSchema();
    }

    public function testGetSchemaThrowsAfterReset(): void
    {
        $context = new TenantContext();
        $context->onTenantSwitched($this->makeSwitchedEvent('7', 'tenant_seven'));
        $context->reset();

        $this->expectException(\LogicException::class);
        $context->getSchema();
    }

    public function testResetClearsDbNameAlongWithTenantId(): void
    {
        $context = new TenantContext();
        $context->onTenantSwitched($this->makeSwitchedEvent('5', 'some_db'));
        $context->reset();

        $this->assertNull($context->getTenantId());
        $this->expectException(\LogicException::class);
        $context->getSchema(); // dbName also cleared
    }
}
