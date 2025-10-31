<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPCSignBundle\JsonRPCSignBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(JsonRPCSignBundle::class)]
#[RunTestsInSeparateProcesses]
final class JsonRPCSignBundleTest extends AbstractBundleTestCase
{
}
