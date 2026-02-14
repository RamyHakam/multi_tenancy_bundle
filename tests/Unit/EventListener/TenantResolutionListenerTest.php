<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\EventListener;

use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\EventListener\TenantResolutionListener;
use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class TenantResolutionListenerTest extends TestCase
{
    private TenantResolverInterface&MockObject $resolver;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private HttpKernelInterface&MockObject $kernel;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(TenantResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = TenantResolutionListener::getSubscribedEvents();
        
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame(['onKernelRequest', 32], $events[KernelEvents::REQUEST]);
    }

    public function testOnKernelRequestResolvesTenantAndDispatchesEvent(): void
    {
        $this->resolver->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->willReturn('tenant123');
        
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof SwitchDbEvent && $event->getDbIndex() === 'tenant123';
            }));
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher
        );
        
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $listener->onKernelRequest($event);
        
        $this->assertSame('tenant123', $request->attributes->get(TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT));
        $this->assertTrue($request->attributes->get(TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT_RESOLVED));
    }

    public function testOnKernelRequestSkipsSubRequests(): void
    {
        $this->resolver->expects($this->never())
            ->method('supports');
        
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher
        );
        
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);
        
        $listener->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipsAlreadyResolvedRequests(): void
    {
        $this->resolver->expects($this->never())
            ->method('supports');
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher
        );
        
        $request = Request::create('http://example.com/');
        $request->attributes->set(TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT_RESOLVED, true);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $listener->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipsExcludedPaths(): void
    {
        $this->resolver->expects($this->never())
            ->method('supports');
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher,
            false,
            ['/api', '/health']
        );
        
        $request = Request::create('http://example.com/api/v1/users');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $listener->onKernelRequest($event);
        
        $this->assertTrue($request->attributes->get(TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT_RESOLVED));
    }

    public function testOnKernelRequestHandlesUnsupportedRequest(): void
    {
        $this->resolver->expects($this->once())
            ->method('supports')
            ->willReturn(false);
        
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher
        );
        
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $listener->onKernelRequest($event);
        
        $this->assertFalse($request->attributes->has(TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT));
    }

    public function testOnKernelRequestThrowsWhenResolverDoesNotSupportAndThrowOnMissingEnabled(): void
    {
        $this->resolver->expects($this->once())
            ->method('supports')
            ->willReturn(false);
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher,
            true // throw on missing
        );
        
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve tenant: resolver does not support this request.');
        
        $listener->onKernelRequest($event);
    }

    public function testOnKernelRequestThrowsWhenTenantNotFoundAndThrowOnMissingEnabled(): void
    {
        $this->resolver->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->willReturn(null);
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher,
            true // throw on missing
        );
        
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve tenant: no tenant identifier found.');
        
        $listener->onKernelRequest($event);
    }

    public function testOnKernelRequestHandlesNullTenantGracefully(): void
    {
        $this->resolver->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->willReturn(null);
        
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');
        
        $listener = new TenantResolutionListener(
            $this->resolver,
            $this->eventDispatcher,
            false // don't throw
        );
        
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $listener->onKernelRequest($event);
        
        $this->assertFalse($request->attributes->has(TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT));
    }

    public function testRequestAttributeConstants(): void
    {
        $this->assertSame('_tenant', TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT);
        $this->assertSame('_tenant_resolved', TenantResolutionListener::REQUEST_ATTRIBUTE_TENANT_RESOLVED);
    }
}
