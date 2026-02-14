<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\HeaderResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class HeaderResolverTest extends TestCase
{
    public function testImplementsTenantResolverInterface(): void
    {
        $resolver = new HeaderResolver();
        
        $this->assertInstanceOf(TenantResolverInterface::class, $resolver);
    }

    public function testResolveWithDefaultHeader(): void
    {
        $resolver = new HeaderResolver();
        $request = Request::create('http://example.com/dashboard');
        $request->headers->set('X-Tenant-ID', 'tenant123');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant123', $result);
    }

    public function testResolveWithCustomHeader(): void
    {
        $resolver = new HeaderResolver(['header_name' => 'X-Custom-Tenant']);
        $request = Request::create('http://example.com/dashboard');
        $request->headers->set('X-Custom-Tenant', 'my-tenant');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('my-tenant', $result);
    }

    public function testResolveReturnsNullWhenHeaderMissing(): void
    {
        $resolver = new HeaderResolver();
        $request = Request::create('http://example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testResolveReturnsNullWhenHeaderEmpty(): void
    {
        $resolver = new HeaderResolver();
        $request = Request::create('http://example.com/dashboard');
        $request->headers->set('X-Tenant-ID', '');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testSupportsReturnsTrueWhenHeaderPresent(): void
    {
        $resolver = new HeaderResolver();
        $request = Request::create('http://example.com/dashboard');
        $request->headers->set('X-Tenant-ID', 'tenant123');
        
        $this->assertTrue($resolver->supports($request));
    }

    public function testSupportsReturnsFalseWhenHeaderMissing(): void
    {
        $resolver = new HeaderResolver();
        $request = Request::create('http://example.com/dashboard');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testSupportsWithCustomHeaderName(): void
    {
        $resolver = new HeaderResolver(['header_name' => 'X-My-Tenant']);
        
        $requestWithHeader = Request::create('http://example.com/');
        $requestWithHeader->headers->set('X-My-Tenant', 'tenant1');
        
        $requestWithoutHeader = Request::create('http://example.com/');
        $requestWithoutHeader->headers->set('X-Tenant-ID', 'tenant1');
        
        $this->assertTrue($resolver->supports($requestWithHeader));
        $this->assertFalse($resolver->supports($requestWithoutHeader));
    }
}
