<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPC\Core\Exception\JsonRpcExceptionInterface;
use Tourze\JsonRPCSignBundle\Exception\SignTimeoutException;

class SignTimeoutExceptionTest extends TestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignTimeoutException();

        $this->assertInstanceOf(JsonRpcExceptionInterface::class, $exception);
        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(-32600, $exception->getErrorCode());
        $this->assertEquals('签名过期', $exception->getMessage());
        $this->assertEquals('签名过期', $exception->getErrorMessage());
        $this->assertEquals([], $exception->getErrorData());
    }

    public function testCustomMessageAndData(): void
    {
        $message = '签名已超时，请重新签名';
        $data = ['timeout_threshold' => '5分钟', 'current_time_diff' => '8分钟'];
        $exception = new SignTimeoutException($message, $data);

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($data, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previousException = new \RuntimeException('原始错误');
        $exception = new SignTimeoutException('签名过期', [], $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }
}
