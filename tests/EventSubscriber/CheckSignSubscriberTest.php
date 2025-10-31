<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Service\ApiCallerService;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCSignBundle\Attribute\CheckSign;
use Tourze\JsonRPCSignBundle\EventSubscriber\CheckSignSubscriber;
use Tourze\JsonRPCSignBundle\Exception\RequestNotAvailableException;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(CheckSignSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class CheckSignSubscriberTest extends AbstractEventSubscriberTestCase
{
    private RequestStack $requestStack;

    private MockObject|ApiCallerService $apiCallerService;

    private MockObject|LoggerInterface $logger;

    private CheckSignSubscriber $subscriber;

    private function setUpSubscriber(): void
    {
        $this->apiCallerService = $this->createMock(ApiCallerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 在获取服务前先将Mock注入容器，但只在服务未初始化时设置
        if (!self::getContainer()->initialized(ApiCallerService::class)) {
            self::getContainer()->set(ApiCallerService::class, $this->apiCallerService);
        }
        if (!self::getContainer()->initialized('monolog.logger.procedure')) {
            self::getContainer()->set('monolog.logger.procedure', $this->logger);
        }

        // RequestStack 使用真实的服务，我们通过其他方式模拟行为
        // 从容器获取服务实例，而不是直接实例化
        $this->subscriber = self::getService(CheckSignSubscriber::class);

        // 获取真实的RequestStack并设置为属性
        $this->requestStack = self::getService(RequestStack::class);
    }

    protected function onSetUp(): void
    {
        // 设置容器中的服务以支持容器获取测试
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->apiCallerService = $this->createMock(ApiCallerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        if (!self::getContainer()->has(ApiCallerService::class)) {
            self::getContainer()->set(ApiCallerService::class, $this->apiCallerService);
        }
        if (!self::getContainer()->has('monolog.logger.procedure')) {
            self::getContainer()->set('monolog.logger.procedure', $this->logger);
        }
        if (!self::getContainer()->has(RequestStack::class)) {
            self::getContainer()->set(RequestStack::class, $this->requestStack);
        }
    }

    public function testEventSubscriberCanBeRetrievedFromContainer(): void
    {
        $subscriber = self::getService(CheckSignSubscriber::class);
        $this->assertInstanceOf(CheckSignSubscriber::class, $subscriber);
    }

    public function testBeforeMethodApplyWithoutCheckSignAttribute(): void
    {
        $this->setUpSubscriber();

        // 创建一个没有 CheckSign 属性的方法类
        $method = new class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return 'test';
            }

            public function execute(): array
            {
                return [];
            }
        };

        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 确保 RequestStack 为空（这个测试应该不会访问请求）
        $this->subscriber->beforeMethodApply($event);

        // 如果没有抛出异常，说明没有检查签名属性成功跳过了签名验证
        $this->assertTrue(true);
    }

    public function testBeforeMethodApplyWithCheckSignAttributeButNoRequest(): void
    {
        $this->setUpSubscriber();
        // 创建一个带有 CheckSign 属性的方法类
        $method = new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return 'test';
            }

            public function execute(): array
            {
                return [];
            }
        };

        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // RequestStack 默认为空，所以 getMainRequest() 应该返回 null

        $this->expectException(RequestNotAvailableException::class);
        $this->expectExceptionMessage('No request available');

        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApplyWithCheckSignAttributeAndGodSign(): void
    {
        $this->setUpSubscriber();
        // 创建一个带有 CheckSign 属性的方法类
        $method = new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return 'test';
            }

            public function execute(): array
            {
                return [];
            }
        };

        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 创建一个包含 __ignoreSign 参数的请求
        $request = Request::create('/', 'POST');
        $request->query->set('__ignoreSign', 'god');

        // 设置环境变量模拟
        $_ENV['JSON_RPC_GOD_SIGN'] = 'god';

        // 将请求推送到 RequestStack
        $this->requestStack->push($request);

        // ApiCallerService 不应该被调用，因为使用了 god sign
        $this->apiCallerService->expects($this->never())->method('findValidApiCallerByAppId');

        $this->subscriber->beforeMethodApply($event);

        // 清理环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);
    }

    public function testBeforeMethodApplyWithCheckSignAttributeValidRequest(): void
    {
        $this->setUpSubscriber();
        // 创建一个带有 CheckSign 属性的方法类
        $method = new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return 'test';
            }

            public function execute(): array
            {
                return [];
            }
        };

        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 创建一个正常的请求
        $request = Request::create('/', 'POST', [], [], [], [], '{"test": "data"}');
        $timestamp = (string) time();
        $nonce = 'test-nonce';
        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', $timestamp);
        $request->headers->set('Signature-Nonce', $nonce);

        // 准备有效的签名
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->method('getSignTimeoutSecond')->willReturn(180);
        $accessKey->method('getAppSecret')->willReturn('test-secret');

        $rawText = '{"test": "data"}' . $timestamp . $nonce;
        $validSignature = hash_hmac('sha1', $rawText, 'test-secret');
        $request->headers->set('Signature', $validSignature);

        // 将请求推送到 RequestStack
        $this->requestStack->push($request);

        // 模拟 ApiCallerService 返回有效的 AccessKey
        $this->apiCallerService->expects($this->once())
            ->method('findValidApiCallerByAppId')
            ->with('test-app')
            ->willReturn($accessKey)
        ;

        // 应该记录日志
        $this->logger->expects($this->once())
            ->method('info')
            ->with('签名校验通过，允许访问接口', [
                'method' => $method,
                'request' => $request,
            ])
        ;

        $this->subscriber->beforeMethodApply($event);
    }

    public function testBeforeMethodApplyWithCheckSignAttributeValidRequestWithoutGodSignEnv(): void
    {
        $this->setUpSubscriber();
        // 创建一个带有 CheckSign 属性的方法类
        $method = new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): mixed
            {
                return 'test';
            }

            public function execute(): array
            {
                return [];
            }
        };

        $event = new BeforeMethodApplyEvent();
        $event->setMethod($method);

        // 创建一个包含 __ignoreSign 但没有设置环境变量的请求
        $request = Request::create('/', 'POST', [], [], [], [], '{"test": "data"}');
        $request->query->set('__ignoreSign', 'wrong-value'); // 使用错误的值，不匹配默认的 'god'
        $timestamp = (string) time();
        $nonce = 'test-nonce';
        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', $timestamp);
        $request->headers->set('Signature-Nonce', $nonce);

        // 准备有效的签名
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->method('getSignTimeoutSecond')->willReturn(180);
        $accessKey->method('getAppSecret')->willReturn('test-secret');

        $rawText = '{"test": "data"}' . $timestamp . $nonce;
        $validSignature = hash_hmac('sha1', $rawText, 'test-secret');
        $request->headers->set('Signature', $validSignature);

        // 确保没有设置 JSON_RPC_GOD_SIGN 环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);

        // 将请求推送到 RequestStack
        $this->requestStack->push($request);

        // 由于环境变量不匹配，应该调用签名验证
        $this->apiCallerService->expects($this->once())
            ->method('findValidApiCallerByAppId')
            ->with('test-app')
            ->willReturn($accessKey)
        ;

        // 应该记录日志
        $this->logger->expects($this->once())
            ->method('info')
            ->with('签名校验通过，允许访问接口', [
                'method' => $method,
                'request' => $request,
            ])
        ;

        $this->subscriber->beforeMethodApply($event);
    }
}
