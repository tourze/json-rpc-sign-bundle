<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCSignBundle\Attribute\CheckSign;

class CheckSignTest extends TestCase
{
    public function testAttributeCreation(): void
    {
        $attribute = new CheckSign();
        $this->assertInstanceOf(CheckSign::class, $attribute);
    }

    public function testAttributeIsTargetedAtClass(): void
    {
        // 使用反射获取属性上的属性（元属性）
        $reflection = new \ReflectionClass(CheckSign::class);
        $attributes = $reflection->getAttributes();

        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());

        // 实例化元属性
        $attribute = $attributes[0]->newInstance();
        // 验证该属性只能用于类
        $this->assertEquals(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function testAttributeCanBeAppliedToClass(): void
    {
        // 创建一个应用了CheckSign属性的匿名类
        $testClass = new #[CheckSign] class {
            public function someMethod(): void
            {
            }
        };

        $reflection = new \ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(CheckSign::class);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(CheckSign::class, $attributes[0]->newInstance());
    }
}
