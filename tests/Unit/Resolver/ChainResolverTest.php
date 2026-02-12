<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\ChainResolver;
use Hakam\MultiTenancyBundle\Resolver\HeaderResolver;
use Hakam\MultiTenancyBundle\Resolver\PathResolver;
use Hakam\MultiTenancyBundle\Resolver\SubdomainResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ChainResolverTest extends TestCase
{
    public function testImplementsTenantResolverInterface(): void
    {
        $resolver = new ChainResolver();
        
        $this->assertInstanceOf(TenantResolverInterface::class, $resolver);
    }

    public function testResolveUsesFirstSuccessfulResolver(): void
    {
        $resolver = new ChainResolver([
            new HeaderResolver(),
            new SubdomainResolver(),
        ]);
        
        $request = Request::create('http://tenant-from-subdomain.example.com/dashboard');
        $request->headers->set('X-Tenant-ID', 'tenant-from-header');
        
        $result = $resolver->resolve($request);
        
        // Header resolver comes first and should win
        $this->assertSame('tenant-from-header', $result);
    }

    public function testResolveFallsBackToSecondResolver(): void
    {
        $resolver = new ChainResolver([
            new HeaderResolver(),
            new SubdomainResolver(),
        ]);
        
        // No header, but has subdomain
        $request = Request::create('http://tenant-from-subdomain.example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant-from-subdomain', $result);
    }

    public function testResolveReturnsNullWhenNoResolverSucceeds(): void
    {
        $resolver = new ChainResolver([
            new HeaderResolver(),
            new SubdomainResolver(),
        ]);
        
        // No header, no subdomain (localhost)
        $request = Request::create('http://localhost/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testSupportsReturnsTrueWhenAnyResolverSupports(): void
    {
        $resolver = new ChainResolver([
            new HeaderResolver(),
            new SubdomainResolver(),
        ]);
        
        $request = Request::create('http://tenant1.example.com/dashboard');
        
        $this->assertTrue($resolver->supports($request));
    }

    public function testSupportsReturnsFalseWhenNoResolverSupports(): void
    {
        $resolver = new ChainResolver([
            new HeaderResolver(),
            new SubdomainResolver(),
        ]);
        
        // localhost with no headers
        $request = Request::create('http://localhost/dashboard');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testAddResolverWithPriority(): void
    {
        $resolver = new ChainResolver();
        
        $lowPriorityResolver = new PathResolver();
        $highPriorityResolver = new HeaderResolver();
        
        $resolver->addResolver($lowPriorityResolver, 10);
        $resolver->addResolver($highPriorityResolver, 100);
        
        $resolvers = $resolver->getResolvers();
        
        // High priority should be first
        $this->assertInstanceOf(HeaderResolver::class, $resolvers[0]);
        $this->assertInstanceOf(PathResolver::class, $resolvers[1]);
    }

    public function testEmptyChainReturnsNull(): void
    {
        $resolver = new ChainResolver([]);
        $request = Request::create('http://example.com/tenant1');
        
        $this->assertNull($resolver->resolve($request));
        $this->assertFalse($resolver->supports($request));
    }

    public function testGetResolversReturnsAllResolvers(): void
    {
        $headerResolver = new HeaderResolver();
        $pathResolver = new PathResolver();
        
        $resolver = new ChainResolver([$headerResolver, $pathResolver]);
        
        $resolvers = $resolver->getResolvers();
        
        $this->assertCount(2, $resolvers);
        $this->assertSame($headerResolver, $resolvers[0]);
        $this->assertSame($pathResolver, $resolvers[1]);
    }
}
