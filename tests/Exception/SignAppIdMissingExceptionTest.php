<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCSignBundle\Exception\SignAppIdMissingException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SignAppIdMissingException::class)]
final class SignAppIdMissingExceptionTest extends AbstractExceptionTestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignAppIdMissingException();

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(-32600, $exception->getErrorCode());
        $this->assertEquals('缺少必要的签名AppID', $exception->getMessage());
        $this->assertEquals('缺少必要的签名AppID', $exception->getErrorMessage());
        $this->assertEquals([], $exception->getErrorData());
    }

    public function testCustomMessageAndData(): void
    {
        $message = '请在请求中提供AppID';
        $data = ['required_parameters' => ['app_id']];
        $exception = new SignAppIdMissingException($message, $data);

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($data, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previousException = new \RuntimeException('原始错误');
        $exception = new SignAppIdMissingException('缺少必要的签名AppID', [], $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }
}
