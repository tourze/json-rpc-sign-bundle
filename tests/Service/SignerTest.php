<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Tests\Service;

use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\JsonRPCCallerBundle\Entity\ApiCaller;
use Tourze\JsonRPCCallerBundle\Repository\ApiCallerRepository;
use Tourze\JsonRPCSignBundle\Exception\SignAppIdMissingException;
use Tourze\JsonRPCSignBundle\Exception\SignAppIdNotFoundException;
use Tourze\JsonRPCSignBundle\Exception\SignErrorException;
use Tourze\JsonRPCSignBundle\Exception\SignNonceMissingException;
use Tourze\JsonRPCSignBundle\Exception\SignRequiredException;
use Tourze\JsonRPCSignBundle\Exception\SignTimeoutException;
use Tourze\JsonRPCSignBundle\Service\Signer;

class SignerTest extends TestCase
{
    private Signer $signer;
    private ApiCallerRepository&MockObject $callerRepository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->callerRepository = $this->createMock(ApiCallerRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->signer = new Signer($this->callerRepository, $this->logger);
    }

    public function testGetRequestNonce_withValidNonce(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Nonce', 'test-nonce-12345');

        $nonce = $this->signer->getRequestNonce($request);

        $this->assertEquals('test-nonce-12345', $nonce);
    }

    public function testGetRequestNonce_missingNonce(): void
    {
        $request = Request::create('/', 'POST');

        $this->expectException(SignNonceMissingException::class);
        $this->signer->getRequestNonce($request);
    }

    public function testGetRequestNonce_emptyNonce(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Nonce', '');

        $this->expectException(SignNonceMissingException::class);
        $this->signer->getRequestNonce($request);
    }

    public function testGetRequestSignatureMethod_withCustomMethod(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Method', 'MD5');

        $method = $this->signer->getRequestSignatureMethod($request);

        $this->assertEquals('MD5', $method);
    }

    public function testGetRequestSignatureMethod_defaultMethod(): void
    {
        $request = Request::create('/', 'POST');

        $method = $this->signer->getRequestSignatureMethod($request);

        $this->assertEquals('HMAC-SHA1', $method);
    }

    public function testGetRequestSignatureVersion_withCustomVersion(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-Version', '2.0');

        $version = $this->signer->getRequestSignatureVersion($request);

        $this->assertEquals('2.0', $version);
    }

    public function testGetRequestSignatureVersion_defaultVersion(): void
    {
        $request = Request::create('/', 'POST');

        $version = $this->signer->getRequestSignatureVersion($request);

        $this->assertEquals('1.0', $version);
    }

    public function testGetRequestSignatureAppId_withValidAppId(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', 'app123');

        $appId = $this->signer->getRequestSignatureAppId($request);

        $this->assertEquals('app123', $appId);
    }

    public function testGetRequestSignatureAppId_missingAppId(): void
    {
        $request = Request::create('/', 'POST');

        $this->expectException(SignAppIdMissingException::class);
        $this->signer->getRequestSignatureAppId($request);
    }

    public function testGetRequestSignatureAppId_emptyAppId(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Signature-AppID', '');

        $this->expectException(SignAppIdMissingException::class);
        $this->signer->getRequestSignatureAppId($request);
    }

    public function testCheckRequest_appIdNotFound(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '{"method":"test"}');
        $request->headers->set('Signature-AppID', 'nonexistent');
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature-Timestamp', (string) time());
        $request->headers->set('Signature', 'test-signature');

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'nonexistent', 'valid' => true])
            ->willReturn(null);

        $this->expectException(SignAppIdNotFoundException::class);
        $this->signer->checkRequest($request);
    }

    public function testCheckRequest_signatureTimeout(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(60);

        $oldTimestamp = time() - 300; // 5分钟前
        $request = Request::create('/', 'POST', [], [], [], [], '{"method":"test"}');
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature-Timestamp', (string) $oldTimestamp);
        $request->headers->set('Signature', 'test-signature');

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        $this->expectException(SignTimeoutException::class);
        $this->signer->checkRequest($request);
    }

    public function testCheckRequest_missingSignature(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);

        $request = Request::create('/', 'POST', [], [], [], [], '{"method":"test"}');
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature-Timestamp', (string) time());

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        $this->expectException(SignRequiredException::class);
        $this->signer->checkRequest($request);
    }

    public function testCheckRequest_md5SignatureValid(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);
        $caller->method('getAppSecret')->willReturn('secret123');

        $payload = '{"method":"test"}';
        $timestamp = time();
        $nonce = 'test-nonce';
        $secret = 'secret123';

        // 生成正确的MD5签名
        $rawText = $payload . $timestamp . $nonce . $secret;
        $expectedSignature = md5($rawText);

        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', $nonce);
        $request->headers->set('Signature-Timestamp', (string) $timestamp);
        $request->headers->set('Signature-Method', 'md5');
        $request->headers->set('Signature-Version', '1.0');
        $request->headers->set('Signature', $expectedSignature);

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        // 应该不抛出异常
        $this->signer->checkRequest($request);
        $this->assertTrue(true); // 如果到达这里说明没有抛出异常
    }

    public function testCheckRequest_hmacSha1SignatureValid(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);
        $caller->method('getAppSecret')->willReturn('secret123');

        $payload = '{"method":"test"}';
        $timestamp = time();
        $nonce = 'test-nonce';
        $secret = 'secret123';

        // 生成正确的HMAC-SHA1签名
        $rawText = $payload . $timestamp . $nonce;
        $expectedSignature = hash_hmac('sha1', $rawText, $secret);

        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', $nonce);
        $request->headers->set('Signature-Timestamp', (string) $timestamp);
        $request->headers->set('Signature-Method', 'HMAC-SHA1');
        $request->headers->set('Signature-Version', '1.0');
        $request->headers->set('Signature', $expectedSignature);

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        // 应该不抛出异常
        $this->signer->checkRequest($request);
        $this->assertTrue(true); // 如果到达这里说明没有抛出异常
    }

    public function testCheckRequest_sha1SignatureValid(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);
        $caller->method('getAppSecret')->willReturn('secret123');

        $payload = '{"method":"test"}';
        $timestamp = time();
        $nonce = 'test-nonce';
        $secret = 'secret123';

        // 生成正确的SHA1签名（兼容旧版本）
        $rawText = $payload . $timestamp . $nonce;
        $expectedSignature = hash_hmac('sha1', $rawText, $secret);

        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', $nonce);
        $request->headers->set('Signature-Timestamp', (string) $timestamp);
        $request->headers->set('Signature-Method', 'sha1');
        $request->headers->set('Signature-Version', '1.0');
        $request->headers->set('Signature', $expectedSignature);

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        // 应该不抛出异常
        $this->signer->checkRequest($request);
        $this->assertTrue(true); // 如果到达这里说明没有抛出异常
    }

    public function testCheckRequest_invalidSignature(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);
        $caller->method('getAppSecret')->willReturn('secret123');

        $request = Request::create('/', 'POST', [], [], [], [], '{"method":"test"}');
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature-Timestamp', (string) time());
        $request->headers->set('Signature-Method', 'HMAC-SHA1');
        $request->headers->set('Signature-Version', '1.0');
        $request->headers->set('Signature', 'invalid-signature');

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('JsonRPC签名不通过', $this->anything());

        $this->expectException(SignErrorException::class);
        $this->signer->checkRequest($request);
    }

    public function testCheckRequest_unsupportedSignatureMethod(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);
        $caller->method('getAppSecret')->willReturn('secret123');

        $request = Request::create('/', 'POST', [], [], [], [], '{"method":"test"}');
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature-Timestamp', (string) time());
        $request->headers->set('Signature-Method', 'UNSUPPORTED');
        $request->headers->set('Signature-Version', '1.0');
        $request->headers->set('Signature', 'test-signature');

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        $this->expectException(SignErrorException::class);
        $this->signer->checkRequest($request);
    }

    public function testCheckRequest_defaultTimeoutValue(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(null); // 返回null使用默认值
        $caller->method('getAppSecret')->willReturn('secret123');

        // 使用超过默认3分钟的时间戳
        $oldTimestamp = time() - 240; // 4分钟前，超过默认3分钟容差

        $request = Request::create('/', 'POST', [], [], [], [], '{"method":"test"}');
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', 'test-nonce');
        $request->headers->set('Signature-Timestamp', (string) $oldTimestamp);
        $request->headers->set('Signature', 'test-signature');

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        $this->expectException(SignTimeoutException::class);
        $this->signer->checkRequest($request);
    }

    public function testCheckRequest_emptyPayload(): void
    {
        $caller = $this->createMock(ApiCaller::class);
        $caller->method('getSignTimeoutSecond')->willReturn(300);
        $caller->method('getAppSecret')->willReturn('secret123');

        $payload = '';
        $timestamp = time();
        $nonce = 'test-nonce';
        $secret = 'secret123';

        // 生成空载荷的正确签名
        $rawText = $payload . $timestamp . $nonce;
        $expectedSignature = hash_hmac('sha1', $rawText, $secret);

        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('Signature-AppID', 'app123');
        $request->headers->set('Signature-Nonce', $nonce);
        $request->headers->set('Signature-Timestamp', (string) $timestamp);
        $request->headers->set('Signature-Method', 'HMAC-SHA1');
        $request->headers->set('Signature-Version', '1.0');
        $request->headers->set('Signature', $expectedSignature);

        $this->callerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['appId' => 'app123', 'valid' => true])
            ->willReturn($caller);

        // 应该不抛出异常
        $this->signer->checkRequest($request);
        $this->assertTrue(true);
    }
} 