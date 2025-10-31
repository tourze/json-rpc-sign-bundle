<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCSignBundle\DependencyInjection\JsonRPCSignExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(JsonRPCSignExtension::class)]
final class JsonRPCSignExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
