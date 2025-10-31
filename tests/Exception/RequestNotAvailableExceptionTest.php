<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCSignBundle\Exception\RequestNotAvailableException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(RequestNotAvailableException::class)]
final class RequestNotAvailableExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new RequestNotAvailableException('Request is not available');

        // 验证异常消息正确
        $this->assertEquals('Request is not available', $exception->getMessage());
    }

    public function testExceptionCanBeCreatedWithCodeAndPreviousException(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new RequestNotAvailableException('Request error', 500, $previous);

        $this->assertEquals('Request error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
