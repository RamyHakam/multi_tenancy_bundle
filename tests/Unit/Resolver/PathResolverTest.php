<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\PathResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PathResolverTest extends TestCase
{
    public function testImplementsTenantResolverInterface(): void
    {
        $resolver = new PathResolver();
        
        $this->assertInstanceOf(TenantResolverInterface::class, $resolver);
    }

    public function testResolveFirstPathSegment(): void
    {
        $resolver = new PathResolver();
        $request = Request::create('http://example.com/tenant1/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant1', $result);
    }

    public function testResolveSecondPathSegment(): void
    {
        $resolver = new PathResolver(['path_segment' => 1]);
        $request = Request::create('http://example.com/api/tenant1/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant1', $result);
    }

    public function testResolveReturnsNullForEmptyPath(): void
    {
        $resolver = new PathResolver();
        $request = Request::create('http://example.com/');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testResolveReturnsNullForInvalidSegmentIndex(): void
    {
        $resolver = new PathResolver(['path_segment' => 5]);
        $request = Request::create('http://example.com/tenant1/dashboard');
        
        $result = $resolver->resolve($request);
        
        $this->assertNull($result);
    }

    public function testSupportsReturnsTrueForPathWithSegments(): void
    {
        $resolver = new PathResolver();
        $request = Request::create('http://example.com/tenant1/dashboard');
        
        $this->assertTrue($resolver->supports($request));
    }

    public function testSupportsReturnsFalseForRootPath(): void
    {
        $resolver = new PathResolver();
        $request = Request::create('http://example.com/');
        
        $this->assertFalse($resolver->supports($request));
    }

    public function testSupportsReturnsFalseForExcludedPath(): void
    {
        $resolver = new PathResolver(['excluded_paths' => ['/api', '/admin']]);
        
        $apiRequest = Request::create('http://example.com/api/v1/users');
        $adminRequest = Request::create('http://example.com/admin/dashboard');
        $normalRequest = Request::create('http://example.com/tenant1/dashboard');
        
        $this->assertFalse($resolver->supports($apiRequest));
        $this->assertFalse($resolver->supports($adminRequest));
        $this->assertTrue($resolver->supports($normalRequest));
    }

    public function testResolveWithMultiplePathSegments(): void
    {
        $resolver = new PathResolver();
        $request = Request::create('http://example.com/tenant-abc-123/users/list');
        
        $result = $resolver->resolve($request);
        
        $this->assertSame('tenant-abc-123', $result);
    }
}
