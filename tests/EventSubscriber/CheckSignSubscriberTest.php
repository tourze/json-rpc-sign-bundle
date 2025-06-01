<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCSignBundle\Attribute\CheckSign;
use Tourze\JsonRPCSignBundle\EventSubscriber\CheckSignSubscriber;
use Tourze\JsonRPCSignBundle\Service\Signer;

class CheckSignSubscriberTest extends TestCase
{
    private CheckSignSubscriber $subscriber;
    private RequestStack&MockObject $requestStack;
    private Signer&MockObject $signer;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->signer = $this->createMock(Signer::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new CheckSignSubscriber(
            $this->requestStack,
            $this->signer,
            $this->logger
        );
    }

    private function createMockMethodWithoutCheckSign(): JsonRpcMethodInterface
    {
        return new class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return ['success' => true];
            }

            public function execute(): array
            {
                return ['success' => true];
            }
        };
    }

    private function createMockMethodWithCheckSign(): JsonRpcMethodInterface
    {
        return new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return ['success' => true];
            }

            public function execute(): array
            {
                return ['success' => true];
            }
        };
    }

    public function testBeforeMethodApply_withoutCheckSignAttribute(): void
    {
        $method = $this->createMockMethodWithoutCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟请求栈
        $this->requestStack->expects($this->never())
            ->method('getMainRequest');

        // 签名器不应该被调用
        $this->signer->expects($this->never())
            ->method('checkRequest');

        // 日志器不应该被调用
        $this->logger->expects($this->never())
            ->method('info');

        // 执行方法，应该直接返回，不进行任何签名检查
        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApply_withCheckSignAttribute(): void
    {
        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟请求
        $request = Request::create('/', 'POST', ['__ignoreSign' => 'wrong_value']);
        
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        // 签名器应该被调用
        $this->signer->expects($this->once())
            ->method('checkRequest')
            ->with($request);

        // 日志器应该记录成功信息
        $this->logger->expects($this->once())
            ->method('info')
            ->with('签名校验通过，允许访问接口', [
                'method' => $method,
                'request' => $request,
            ]);

        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApply_withIgnoreSignParameter(): void
    {
        // 设置环境变量
        $_ENV['JSON_RPC_GOD_SIGN'] = 'god';

        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟包含忽略签名参数的请求 - 注意：代码检查的是query参数，不是POST参数
        $request = Request::create('/?__ignoreSign=god', 'POST');
        
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        // 签名器不应该被调用（因为有忽略参数）
        $this->signer->expects($this->never())
            ->method('checkRequest');

        // 日志器不应该被调用
        $this->logger->expects($this->never())
            ->method('info');

        $this->subscriber->beforeMethodApply($event);

        // 清理环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);
    }

    public function testBeforeMethodApply_withoutIgnoreSignEnvironment(): void
    {
        // 确保没有设置环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);

        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟包含忽略签名参数的请求，但环境变量未设置 - 使用默认值'god'
        $request = Request::create('/?__ignoreSign=god', 'POST');
        
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        // 签名器不应该被调用（因为默认值是'god'，所以忽略参数有效）
        $this->signer->expects($this->never())
            ->method('checkRequest');

        // 日志器不应该被调用
        $this->logger->expects($this->never())
            ->method('info');

        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApply_wrongIgnoreSignValue(): void
    {
        // 设置环境变量
        $_ENV['JSON_RPC_GOD_SIGN'] = 'secret_key';

        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟包含错误忽略签名参数的请求
        $request = Request::create('/?__ignoreSign=wrong_value', 'POST');
        
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        // 签名器应该被调用（因为忽略参数值不匹配）
        $this->signer->expects($this->once())
            ->method('checkRequest')
            ->with($request);

        // 日志器应该记录成功信息
        $this->logger->expects($this->once())
            ->method('info')
            ->with('签名校验通过，允许访问接口', [
                'method' => $method,
                'request' => $request,
            ]);

        $this->subscriber->beforeMethodApply($event);

        // 清理环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);
    }

    public function testBeforeMethodApply_signerThrowsException(): void
    {
        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟请求
        $request = Request::create('/', 'POST');
        
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        // 签名器抛出异常
        $expectedException = new \RuntimeException('签名验证失败');
        $this->signer->expects($this->once())
            ->method('checkRequest')
            ->with($request)
            ->willThrowException($expectedException);

        // 日志器不应该被调用（因为异常会中断执行）
        $this->logger->expects($this->never())
            ->method('info');

        // 验证异常被正确抛出
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('签名验证失败');

        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApply_nullRequest(): void
    {
        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟空请求 - 这将导致运行时错误，因为代码没有处理null请求的情况
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        // 验证会抛出Error（由于null->query->get()调用）
        $this->expectException(\Error::class);

        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApply_complexIgnoreSignScenario(): void
    {
        // 设置环境变量
        $_ENV['JSON_RPC_GOD_SIGN'] = 'custom_god_key';

        $method = $this->createMockMethodWithCheckSign();
        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 模拟包含正确忽略签名参数的请求
        $request = Request::create('/?__ignoreSign=custom_god_key', 'POST');
        
        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        // 签名器不应该被调用
        $this->signer->expects($this->never())
            ->method('checkRequest');

        // 日志器不应该被调用
        $this->logger->expects($this->never())
            ->method('info');

        $this->subscriber->beforeMethodApply($event);

        // 清理环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);
    }
} 