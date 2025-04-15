<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPC\Core\Exception\JsonRpcExceptionInterface;
use Tourze\JsonRPCSignBundle\Exception\SignNonceMissingException;

class SignNonceMissingExceptionTest extends TestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignNonceMissingException();

        $this->assertInstanceOf(JsonRpcExceptionInterface::class, $exception);
        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(-32600, $exception->getErrorCode());
        $this->assertEquals('缺少必要的随机字符串', $exception->getMessage());
        $this->assertEquals('缺少必要的随机字符串', $exception->getErrorMessage());
        $this->assertEquals([], $exception->getErrorData());
    }

    public function testCustomMessageAndData(): void
    {
        $message = '请提供随机字符串以防止重放攻击';
        $data = ['required_parameters' => ['nonce']];
        $exception = new SignNonceMissingException($message, $data);

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($data, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previousException = new \RuntimeException('原始错误');
        $exception = new SignNonceMissingException('缺少必要的随机字符串', [], $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }
}
