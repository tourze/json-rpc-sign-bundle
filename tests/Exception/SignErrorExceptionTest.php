<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPC\Core\Exception\JsonRpcExceptionInterface;
use Tourze\JsonRPCSignBundle\Exception\SignErrorException;

class SignErrorExceptionTest extends TestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignErrorException();

        $this->assertInstanceOf(JsonRpcExceptionInterface::class, $exception);
        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(-32600, $exception->getErrorCode());
        $this->assertEquals('签名错误', $exception->getMessage());
        $this->assertEquals('签名错误', $exception->getErrorMessage());
        $this->assertEquals([], $exception->getErrorData());
    }

    public function testCustomMessageAndData(): void
    {
        $message = '自定义签名错误信息';
        $data = ['detail' => '签名校验失败'];
        $exception = new SignErrorException($message, $data);

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($data, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previousException = new \RuntimeException('原始错误');
        $exception = new SignErrorException('签名错误', [], $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }
}
