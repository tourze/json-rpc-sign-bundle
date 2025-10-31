<?php

declare(strict_types=1);

namespace Tourze\JsonRPCSignBundle\Service;

use Carbon\CarbonImmutable;
use Monolog\Attribute\WithMonologChannel;
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

/**
 * 签名处理工具
 *
 * 一般来说，我们都是对整个Request来进行签名计算
 */
#[WithMonologChannel(channel: 'json_rpc_sign')]
final readonly class Signer
{
    public function __construct(
        private ApiCallerService $apiCallerService,
        private LoggerInterface $logger,
    ) {
    }

    public function getRequestNonce(Request $request): string
    {
        $str = $request->headers->get('Signature-Nonce');
        if (null === $str || '' === $str) {
            throw new SignNonceMissingException();
        }

        return $str;
    }

    public function getRequestSignatureMethod(Request $request): string
    {
        return $request->headers->get('Signature-Method', 'HMAC-SHA1') ?? 'HMAC-SHA1';
    }

    public function getRequestSignatureVersion(Request $request): string
    {
        return $request->headers->get('Signature-Version', '1.0') ?? '1.0';
    }

    public function getRequestSignatureAppId(Request $request): string
    {
        $SignatureAppID = $request->headers->get('Signature-AppID');
        if (null === $SignatureAppID || '' === $SignatureAppID) {
            throw new SignAppIdMissingException();
        }

        return $SignatureAppID;
    }

    /**
     * 检查请求是否签名通过
     *
     * @throws SignAppIdMissingException
     * @throws SignAppIdNotFoundException
     * @throws SignErrorException
     * @throws SignNonceMissingException
     * @throws SignRequiredException
     * @throws SignTimeoutException
     */
    public function checkRequest(Request $request): void
    {
        $caller = $this->validateAndGetApiCaller($request);
        $this->validateTimestamp($request, $caller);
        $this->validateSignature($request, $caller);
    }

    private function validateAndGetApiCaller(Request $request): AccessKey
    {
        $SignatureAppID = $this->getRequestSignatureAppId($request);
        $caller = $this->apiCallerService->findValidApiCallerByAppId($SignatureAppID);
        if (null === $caller) {
            throw new SignAppIdNotFoundException();
        }

        return $caller;
    }

    private function validateTimestamp(Request $request, AccessKey $caller): void
    {
        $SignatureTimestamp = $request->headers->get('Signature-Timestamp');
        if (null === $SignatureTimestamp || '' === $SignatureTimestamp) {
            throw new SignTimeoutException();
        }

        $tolerateSeconds = $caller->getSignTimeoutSecond() ?? 60 * 3; // 默认允许3分钟误差
        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $SignatureTimestamp) > $tolerateSeconds) {
            throw new SignTimeoutException();
        }
    }

    private function validateSignature(Request $request, AccessKey $caller): void
    {
        $Signature = $request->headers->get('Signature');
        if (null === $Signature || '' === $Signature) {
            throw new SignRequiredException();
        }

        $nonce = $this->getRequestNonce($request);
        $signMethod = $this->getRequestSignatureMethod($request);
        $signVersion = $this->getRequestSignatureVersion($request);
        $signType = "{$signMethod}-{$signVersion}";
        $SignatureTimestamp = $request->headers->get('Signature-Timestamp');

        $serverSign = $this->calculateServerSignature($request, $caller, $signType, $SignatureTimestamp ?? '', $nonce);

        if ($serverSign !== $Signature) {
            $this->logSignatureError($serverSign, $Signature, $request, $SignatureTimestamp ?? '', $nonce);
            throw new SignErrorException();
        }
    }

    private function calculateServerSignature(Request $request, AccessKey $caller, string $signType, string $timestamp, string $nonce): string
    {
        if ('md5-1.0' === $signType) {
            $rawText = implode('', [
                $request->getContent(),
                $timestamp,
                $nonce,
                $caller->getAppSecret() ?? '',
            ]);

            return md5($rawText);
        }

        if ('HMAC-SHA1-1.0' === $signType || 'sha1-1.0' === $signType) {
            $rawText = implode('', [
                $request->getContent(),
                $timestamp,
                $nonce,
            ]);

            return hash_hmac('sha1', $rawText, $caller->getAppSecret() ?? '');
        }

        throw new SignErrorException();
    }

    private function logSignatureError(string $serverSign, string $submitSign, Request $request, string $timestamp, string $nonce): void
    {
        $rawText = implode('', [
            $request->getContent(),
            $timestamp,
            $nonce,
        ]);

        $this->logger->warning('JsonRPC签名不通过', [
            'serverSign' => $serverSign,
            'submitSign' => $submitSign,
            'rawText' => $rawText,
        ]);
    }
}
