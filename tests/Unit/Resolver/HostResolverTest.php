<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\HostResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class HostResolverTest extends TestCase
{
    public function testImplementsTenantResolverInterface(): void
    {
        $resolver = new HostResolver();
        
        $this->assertInstanceOf(TenantResolverInterface::class, $resolver);
    }

    public function testResolveWithMappedHost(): void
    {
        $resolver = new HostResolver([
            'host_map' => [
                'client1.com' => 'tenant1',
                'client2.com' => 'tenant2',
            ],
        ]);
        
        $request = Request::create('http://client1.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant1', $result);
    }

    public function testResolveReturnsNullForUnmappedHost(): void
    {
        $resolver = new HostResolver([
            'host_map' => [
                'client1.com' => 'tenant1',
            ],
        ]);
        
        $request = Request::create('http://unknown.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testResolveReturnsNullWithEmptyHostMap(): void
    {
        $resolver = new HostResolver();
        $request = Request::create('http://example.com/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testSupportsReturnsTrueForMappedHost(): void
    {
        $resolver = new HostResolver([
            'host_map' => [
                'client1.com' => 'tenant1',
            ],
        ]);
        
        $request = Request::create('http://client1.com/dashboard');
        
        $this->assertTrue($resolver->supports($request));
    }

    public function testSupportsReturnsFalseForUnmappedHost(): void
    {
        $resolver = new HostResolver([
            'host_map' => [
                'client1.com' => 'tenant1',
            ],
        ]);
        
        $request = Request::create('http://unknown.com/dashboard');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testMultipleHostMappings(): void
    {
        $resolver = new HostResolver([
            'host_map' => [
                'acme.example.com' => 'acme-corp',
                'beta.example.com' => 'beta-inc',
                'gamma.example.com' => 'gamma-llc',
            ],
        ]);
        
        $acmeRequest = Request::create('http://acme.example.com/');
        $betaRequest = Request::create('http://beta.example.com/');
        $gammaRequest = Request::create('http://gamma.example.com/');
        
        $this->assertSame('acme-corp', $resolver->resolve($acmeRequest));
        $this->assertSame('beta-inc', $resolver->resolve($betaRequest));
        $this->assertSame('gamma-llc', $resolver->resolve($gammaRequest));
    }
}
