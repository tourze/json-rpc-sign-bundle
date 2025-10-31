<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCSignBundle\Exception\SignRequiredException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SignRequiredException::class)]
final class SignRequiredExceptionTest extends AbstractExceptionTestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignRequiredException();

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
