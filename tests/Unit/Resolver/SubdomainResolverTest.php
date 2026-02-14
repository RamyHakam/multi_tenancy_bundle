<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\SubdomainResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SubdomainResolverTest extends TestCase
{
    public function testImplementsTenantResolverInterface(): void
    {
        $resolver = new SubdomainResolver();
        
        $this->assertInstanceOf(TenantResolverInterface::class, $resolver);
    }

    public function testResolveWithSubdomain(): void
    {
        $resolver = new SubdomainResolver();
        $request = Request::create('http://tenant1.example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant1', $result);
    }

    public function testResolveWithConfiguredBaseDomain(): void
    {
        $resolver = new SubdomainResolver(['base_domain' => 'example.com']);
        $request = Request::create('http://tenant1.example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant1', $result);
    }

    public function testResolveWithMultipleSubdomains(): void
    {
        $resolver = new SubdomainResolver(['base_domain' => 'example.com']);
        $request = Request::create('http://api.tenant1.example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('api', $result);
    }

    public function testResolveWithSubdomainPositionOne(): void
    {
        $resolver = new SubdomainResolver([
            'base_domain' => 'example.com',
            'subdomain_position' => 1,
        ]);
        $request = Request::create('http://api.tenant1.example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant1', $result);
    }

    public function testResolveReturnsNullForNoSubdomain(): void
    {
        $resolver = new SubdomainResolver();
        $request = Request::create('http://example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testResolveReturnsNullWhenBaseDomainDoesNotMatch(): void
    {
        $resolver = new SubdomainResolver(['base_domain' => 'example.com']);
        $request = Request::create('http://tenant1.other.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testSupportsReturnsTrueForSubdomain(): void
    {
        $resolver = new SubdomainResolver();
        $request = Request::create('http://tenant1.example.com/dashboard');
        
        $this->assertTrue($resolver->supports($request));
    }

    public function testSupportsReturnsFalseForLocalhost(): void
    {
        $resolver = new SubdomainResolver();
        $request = Request::create('http://localhost/dashboard');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testSupportsReturnsFalseForIpAddress(): void
    {
        $resolver = new SubdomainResolver();
        $request = Request::create('http://192.168.1.1/dashboard');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testSupportsReturnsFalseForTwoPartDomain(): void
    {
        $resolver = new SubdomainResolver();
        $request = Request::create('http://example.com/dashboard');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testSupportsWithBaseDomainConfig(): void
    {
        $resolver = new SubdomainResolver(['base_domain' => 'example.com']);
        
        $matchingRequest = Request::create('http://tenant1.example.com/');
        $nonMatchingRequest = Request::create('http://tenant1.other.com/');
        
        $this->assertTrue($resolver->supports($matchingRequest));
        $this->assertFalse($resolver->supports($nonMatchingRequest));
    }
}
