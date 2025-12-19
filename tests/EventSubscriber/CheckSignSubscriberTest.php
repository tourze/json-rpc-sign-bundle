<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Contracts\RpcResultInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Result\EmptyResult;
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

    private CheckSignSubscriber $subscriber;

    private function setUpSubscriber(): void
    {
        // 从容器获取真实的服务
        $this->subscriber = self::getService(CheckSignSubscriber::class);
        $this->requestStack = self::getService(RequestStack::class);
    }

    protected function onSetUp(): void
    {
        // 无需设置Mock，直接使用容器中的真实服务
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
            public function __invoke(JsonRpcRequest $request): RpcResultInterface
            {
                return new EmptyResult();
            }

            public function execute(RpcParamInterface $param): RpcResultInterface
            {
                return new EmptyResult();
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
            public function __invoke(JsonRpcRequest $request): RpcResultInterface
            {
                return new EmptyResult();
            }

            public function execute(RpcParamInterface $param): RpcResultInterface
            {
                return new EmptyResult();
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
            public function __invoke(JsonRpcRequest $request): RpcResultInterface
            {
                return new EmptyResult();
            }

            public function execute(RpcParamInterface $param): RpcResultInterface
            {
                return new EmptyResult();
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

        // 使用 god sign 时不需要数据库中有 AccessKey，直接通过
        $this->subscriber->beforeMethodApply($event);

        // 验证通过，无异常抛出即为成功
        $this->assertTrue(true);

        // 清理环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);
    }

    public function testBeforeMethodApplyWithCheckSignAttributeValidRequest(): void
    {
        $this->setUpSubscriber();

        // 创建真实的 AccessKey 实体并持久化到数据库
        $accessKey = new AccessKey();
        $accessKey->setAppId('test-app');
        $accessKey->setAppSecret('test-secret');
        $accessKey->setTitle('Test Access Key');
        $accessKey->setValid(true);
        $accessKey->setSignTimeoutSecond(180);
        $this->persistAndFlush($accessKey);

        // 创建一个带有 CheckSign 属性的方法类
        $method = new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): RpcResultInterface
            {
                return new EmptyResult();
            }

            public function execute(RpcParamInterface $param): RpcResultInterface
            {
                return new EmptyResult();
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
        $rawText = '{"test": "data"}' . $timestamp . $nonce;
        $validSignature = hash_hmac('sha1', $rawText, 'test-secret');
        $request->headers->set('Signature', $validSignature);

        // 将请求推送到 RequestStack
        $this->requestStack->push($request);

        // 真实的服务会从数据库查询 AccessKey 并验证签名
        $this->subscriber->beforeMethodApply($event);

        // 验证通过，无异常抛出即为成功
        $this->assertTrue(true);
    }

    public function testBeforeMethodApplyWithCheckSignAttributeValidRequestWithoutGodSignEnv(): void
    {
        $this->setUpSubscriber();

        // 创建真实的 AccessKey 实体并持久化到数据库
        $accessKey = new AccessKey();
        $accessKey->setAppId('test-app');
        $accessKey->setAppSecret('test-secret');
        $accessKey->setTitle('Test Access Key 2');
        $accessKey->setValid(true);
        $accessKey->setSignTimeoutSecond(180);
        $this->persistAndFlush($accessKey);

        // 创建一个带有 CheckSign 属性的方法类
        $method = new #[CheckSign] class implements JsonRpcMethodInterface {
            public function __invoke(JsonRpcRequest $request): RpcResultInterface
            {
                return new EmptyResult();
            }

            public function execute(RpcParamInterface $param): RpcResultInterface
            {
                return new EmptyResult();
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
        $rawText = '{"test": "data"}' . $timestamp . $nonce;
        $validSignature = hash_hmac('sha1', $rawText, 'test-secret');
        $request->headers->set('Signature', $validSignature);

        // 确保没有设置 JSON_RPC_GOD_SIGN 环境变量
        unset($_ENV['JSON_RPC_GOD_SIGN']);

        // 将请求推送到 RequestStack
        $this->requestStack->push($request);

        // 由于环境变量不匹配，应该调用真实的签名验证
        $this->subscriber->beforeMethodApply($event);

        // 验证通过，无异常抛出即为成功
        $this->assertTrue(true);
    }
}
