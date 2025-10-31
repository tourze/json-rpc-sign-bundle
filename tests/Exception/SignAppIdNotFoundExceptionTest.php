<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCSignBundle\Exception\SignAppIdNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SignAppIdNotFoundException::class)]
final class SignAppIdNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testDefaultExceptionValues(): void
    {
        $exception = new SignAppIdNotFoundException();

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(-32600, $exception->getErrorCode());
        $this->assertEquals('找不到AppID', $exception->getMessage());
        $this->assertEquals('找不到AppID', $exception->getErrorMessage());
        $this->assertEquals([], $exception->getErrorData());
    }

    public function testCustomMessageAndData(): void
    {
        $message = '无效的AppID，请检查配置';
        $data = ['provided_app_id' => 'test_app_id'];
        $exception = new SignAppIdNotFoundException($message, $data);

        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($data, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previousException = new \RuntimeException('原始错误');
        $exception = new SignAppIdNotFoundException('找不到AppID', [], $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }
}
