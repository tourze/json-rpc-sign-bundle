<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPC\Core\Exception\JsonRpcExceptionInterface;
use Tourze\JsonRPCSignBundle\Exception\SignRequiredException;

class SignRequiredExceptionTest extends TestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignRequiredException();

        $this->assertInstanceOf(JsonRpcExceptionInterface::class, $exception);
        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(-32600, $exception->getErrorCode());
        $this->assertEquals('缺少必要的签名', $exception->getMessage());
        $this->assertEquals('缺少必要的签名', $exception->getErrorMessage());
        $this->assertEquals([], $exception->getErrorData());
    }

    public function testCustomMessageAndData(): void
    {
        $message = '自定义签名缺失错误信息';
        $data = ['detail' => '请检查签名参数'];
        $exception = new SignRequiredException($message, $data);

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($data, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previousException = new \RuntimeException('原始错误');
        $exception = new SignRequiredException('缺少必要的签名', [], $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }
}
