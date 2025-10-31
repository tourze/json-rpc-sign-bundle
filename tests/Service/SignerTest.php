<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Service\ApiCallerService;
use Tourze\JsonRPCSignBundle\Exception\SignAppIdMissingException;
use Tourze\JsonRPCSignBundle\Exception\SignAppIdNotFoundException;
use Tourze\JsonRPCSignBundle\Exception\SignErrorException;
use Tourze\JsonRPCSignBundle\Exception\SignNonceMissingException;
use Tourze\JsonRPCSignBundle\Exception\SignRequiredException;
use Tourze\JsonRPCSignBundle\Exception\SignTimeoutException;
use Tourze\JsonRPCSignBundle\Service\Signer;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(Signer::class)]
#[RunTestsInSeparateProcesses]
final class SignerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 在集成测试中为基础测试设置默认的 mock 服务
        if (!self::getContainer()->has(ApiCallerService::class)) {
            $apiCallerService = $this->createMock(ApiCallerService::class);
            self::getContainer()->set(ApiCallerService::class, $apiCallerService);
        }

        if (!self::getContainer()->has('monolog.logger.json_rpc_sign')) {
            $logger = $this->createMock(LoggerInterface::class);
            self::getContainer()->set('monolog.logger.json_rpc_sign', $logger);
        }
    }

    private function createSignerWithMocks(ApiCallerService $apiCallerService, LoggerInterface $logger): Signer
    {
        // 在获取服务前先将Mock注入容器，但只在服务未初始化时设置
        if (!self::getContainer()->initialized(ApiCallerService::class)) {
            self::getContainer()->set(ApiCallerService::class, $apiCallerService);
        }
        if (!self::getContainer()->initialized('monolog.logger.json_rpc_sign')) {
            self::getContainer()->set('monolog.logger.json_rpc_sign', $logger);
        }

        // 从容器获取服务实例，而不是直接实例化
        return self::getService(Signer::class);
    }

    private function getSigner(): Signer
    {
        return self::getService(Signer::class);
    }

    public function testGetRequestNonceWithValidNonce(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Nonce', 'test-nonce-12345');

        $nonce = $signer->getRequestNonce($request);

        $this->assertEquals('test-nonce-12345', $nonce);
    }

    public function testGetRequestNonceMissingNonce(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');

        $this->expectException(SignNonceMissingException::class);
        $signer->getRequestNonce($request);
    }

    public function testGetRequestNonceEmptyNonce(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Nonce', '');

        $this->expectException(SignNonceMissingException::class);
        $signer->getRequestNonce($request);
    }

    public function testGetRequestSignatureMethodWithCustomMethod(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Method', 'MD5');

        $method = $signer->getRequestSignatureMethod($request);

        $this->assertEquals('MD5', $method);
    }

    public function testGetRequestSignatureMethodDefaultMethod(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');

        $method = $signer->getRequestSignatureMethod($request);

        $this->assertEquals('HMAC-SHA1', $method);
    }

    public function testGetRequestSignatureVersionWithCustomVersion(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Version', '2.0');

        $version = $signer->getRequestSignatureVersion($request);

        $this->assertEquals('2.0', $version);
    }

    public function testGetRequestSignatureVersionDefaultVersion(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');

        $version = $signer->getRequestSignatureVersion($request);

        $this->assertEquals('1.0', $version);
    }

    public function testGetRequestSignatureAppIdWithValidAppId(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'app123');

        $appId = $signer->getRequestSignatureAppId($request);

        $this->assertEquals('app123', $appId);
    }

    public function testGetRequestSignatureAppIdMissingAppId(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');

        $this->expectException(SignAppIdMissingException::class);
        $signer->getRequestSignatureAppId($request);
    }

    public function testGetRequestSignatureAppIdEmptyAppId(): void
    {
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', '');

        $this->expectException(SignAppIdMissingException::class);
        $signer->getRequestSignatureAppId($request);
    }

    public function testCheckRequestAppIdNotFound(): void
    {
        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->method('findValidApiCallerByAppId')->willReturn(null);
        $logger = $this->createMock(LoggerInterface::class);

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'nonexistent-app');

        $this->expectException(SignAppIdNotFoundException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestMissingTimestamp(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->method('getSignTimeoutSecond')->willReturn(180);

        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->method('findValidApiCallerByAppId')->willReturn($accessKey);
        $logger = $this->createMock(LoggerInterface::class);

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'test-app');
        // 缺少 Signature-Timestamp header

        $this->expectException(SignTimeoutException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestExpiredTimestamp(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->method('getSignTimeoutSecond')->willReturn(180);

        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->method('findValidApiCallerByAppId')->willReturn($accessKey);
        $logger = $this->createMock(LoggerInterface::class);

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', (string) (time() - 300)); // 5分钟前，超出3分钟允许范围

        $this->expectException(SignTimeoutException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestMissingSignature(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->method('getSignTimeoutSecond')->willReturn(180);

        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->method('findValidApiCallerByAppId')->willReturn($accessKey);
        $logger = $this->createMock(LoggerInterface::class);

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', (string) time());
        $request->headers->set('Signature-Nonce', 'test-nonce');
        // 缺少 Signature header

        $this->expectException(SignRequiredException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestInvalidSignature(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->method('getSignTimeoutSecond')->willReturn(180);
        $accessKey->method('getAppSecret')->willReturn('test-secret');

        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->method('findValidApiCallerByAppId')->willReturn($accessKey);
        $logger = $this->createMock(LoggerInterface::class);

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST', [], [], [], [], '{"test": "data"}');
        $timestamp = (string) time();
        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', $timestamp);
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature', 'invalid-signature');

        $this->expectException(SignErrorException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestValidSignatureHmacSha1(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->expects($this->once())->method('getSignTimeoutSecond')->willReturn(180);
        $accessKey->expects($this->once())->method('getAppSecret')->willReturn('test-secret');

        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->expects($this->once())
            ->method('findValidApiCallerByAppId')
            ->with('test-app')
            ->willReturn($accessKey)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning'); // 验证成功时不应记录警告

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST', [], [], [], [], '{"test": "data"}');
        $timestamp = (string) time();
        $nonce = 'test-nonce';

        // 计算正确的签名
        $rawText = '{"test": "data"}' . $timestamp . $nonce;
        $validSignature = hash_hmac('sha1', $rawText, 'test-secret');

        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', $timestamp);
        $request->headers->set('Signature-Nonce', $nonce);
        $request->headers->set('Signature', $validSignature);

        // 这个测试应该不会抛出异常
        $signer->checkRequest($request);
        $this->assertTrue(true); // 如果到达这里，说明验证通过
    }

    public function testCheckRequestValidSignatureMd5(): void
    {
        $accessKey = $this->createMock(AccessKey::class);
        $accessKey->expects($this->once())->method('getSignTimeoutSecond')->willReturn(180);
        $accessKey->expects($this->once())->method('getAppSecret')->willReturn('test-secret');

        $apiCallerService = $this->createMock(ApiCallerService::class);
        $apiCallerService->expects($this->once())
            ->method('findValidApiCallerByAppId')
            ->with('test-app')
            ->willReturn($accessKey)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning'); // 验证成功时不应记录警告

        $signer = $this->createSignerWithMocks($apiCallerService, $logger);
        $request = Request::create('/', 'POST', [], [], [], [], '{"test": "data"}');
        $timestamp = (string) time();
        $nonce = 'test-nonce';

        // 计算正确的 MD5 签名
        $rawText = '{"test": "data"}' . $timestamp . $nonce . 'test-secret';
        $validSignature = md5($rawText);

        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', $timestamp);
        $request->headers->set('Signature-Nonce', $nonce);
        $request->headers->set('Signature-Method', 'md5');
        $request->headers->set('Signature', $validSignature);

        // 这个测试应该不会抛出异常
        $signer->checkRequest($request);
        $this->assertTrue(true); // 如果到达这里，说明验证通过
    }
}
