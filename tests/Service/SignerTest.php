<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\AccessKeyBundle\Entity\AccessKey;
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
        // 集成测试不需要任何 Mock 设置
    }

    private function getSigner(): Signer
    {
        return self::getService(Signer::class);
    }

    private function createAccessKey(string $appId, string $appSecret, int $timeout = 180): AccessKey
    {
        $accessKey = new AccessKey();
        $accessKey->setAppId($appId);
        $accessKey->setAppSecret($appSecret);
        $accessKey->setTitle('Test Access Key for ' . $appId);
        $accessKey->setValid(true);
        $accessKey->setSignTimeoutSecond($timeout);
        $this->persistAndFlush($accessKey);

        return $accessKey;
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
        // 不创建任何 AccessKey，让查询自然返回 null
        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'nonexistent-app');

        $this->expectException(SignAppIdNotFoundException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestMissingTimestamp(): void
    {
        $this->createAccessKey('test-app', 'test-secret', 180);

        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'test-app');
        // 缺少 Signature-Timestamp header

        $this->expectException(SignTimeoutException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestExpiredTimestamp(): void
    {
        $this->createAccessKey('test-app', 'test-secret', 180);

        $signer = $this->getSigner();
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'test-app');
        $request->headers->set('Signature-Timestamp', (string) (time() - 300)); // 5分钟前，超出3分钟允许范围

        $this->expectException(SignTimeoutException::class);
        $signer->checkRequest($request);
    }

    public function testCheckRequestMissingSignature(): void
    {
        $this->createAccessKey('test-app', 'test-secret', 180);

        $signer = $this->getSigner();
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
        $this->createAccessKey('test-app', 'test-secret', 180);

        $signer = $this->getSigner();
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
        $this->createAccessKey('test-app', 'test-secret', 180);

        // 对于需要验证日志行为的测试，使用 Mock Logger
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning'); // 验证成功时不应记录警告
        self::getContainer()->set('monolog.logger.json_rpc_sign', $logger);

        $signer = $this->getSigner();
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
        $this->createAccessKey('test-app', 'test-secret', 180);

        // 对于需要验证日志行为的测试，使用 Mock Logger
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning'); // 验证成功时不应记录警告
        self::getContainer()->set('monolog.logger.json_rpc_sign', $logger);

        $signer = $this->getSigner();
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
