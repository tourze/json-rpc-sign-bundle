<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\JsonRPCSignBundle\JsonRPCSignBundle;

class JsonRPCSignBundleTest extends TestCase
{
    public function testBundleInstantiation(): void
    {
        $bundle = new JsonRPCSignBundle();
        
        $this->assertInstanceOf(JsonRPCSignBundle::class, $bundle);
        $this->assertInstanceOf(Bundle::class, $bundle);
    }

    public function testBundleNamespace(): void
    {
        $bundle = new JsonRPCSignBundle();
        
        // Bundle 类应该在正确的命名空间下
        $this->assertEquals('Tourze\JsonRPCSignBundle\JsonRPCSignBundle', get_class($bundle));
    }

    public function testBundleContainerExtension(): void
    {
        $bundle = new JsonRPCSignBundle();
        
        // 获取容器扩展
        $extension = $bundle->getContainerExtension();
        
        // 验证扩展类型
        $this->assertInstanceOf(
            'Tourze\JsonRPCSignBundle\DependencyInjection\JsonRPCSignExtension',
            $extension
        );
    }
} 