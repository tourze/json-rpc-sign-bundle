<?php

namespace Tourze\JsonRPCSignBundle\Service;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\JsonRPCCallerBundle\Repository\ApiCallerRepository;
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
class Signer
{
    public function __construct(
        private readonly ApiCallerRepository $callerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getRequestNonce(Request $request): string
    {
        $str = $request->headers->get('Signature-Nonce');
        if (empty($str)) {
            throw new SignNonceMissingException();
        }

        return $str;
    }

    public function getRequestSignatureMethod(Request $request): string
    {
        return $request->headers->get('Signature-Method', 'HMAC-SHA1');
    }

    public function getRequestSignatureVersion(Request $request): string
    {
        return $request->headers->get('Signature-Version', '1.0');
    }

    public function getRequestSignatureAppId(Request $request): string
    {
        $SignatureAppID = $request->headers->get('Signature-AppID');
        if (empty($SignatureAppID)) {
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
        $SignatureAppID = $this->getRequestSignatureAppId($request);
        $caller = $this->callerRepository->findOneBy([
            'appId' => $SignatureAppID,
            'valid' => true,
        ]);
        if (empty($caller)) {
            throw new SignAppIdNotFoundException();
        }

        $nonce = $this->getRequestNonce($request);
        $signMethod = $this->getRequestSignatureMethod($request);
        $signVersion = $this->getRequestSignatureVersion($request);
        $signType = "{$signMethod}-{$signVersion}";

        // 如果客户端时间跟服务端时间相差太大，就不允许继续
        $SignatureTimestamp = $request->headers->get('Signature-Timestamp');
        if (empty($SignatureTimestamp)) {
            throw new SignTimeoutException();
        }
        $tolerateSeconds = $caller->getSignTimeoutSecond() ?: 60 * 3; // 默认允许3分钟误差
        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $SignatureTimestamp) > $tolerateSeconds) {
            throw new SignTimeoutException();
        }

        $Signature = $request->headers->get('Signature');
        if (!empty($Signature)) {
            $ServerSign = null;
            $rawText = '';
            if ('md5-1.0' === $signType) {
                // 这里的签名很简单，只是将payload+时间戳+随机字符串+秘钥合并起来
                $rawText = implode('', [
                    $request->getContent(),
                    $SignatureTimestamp,
                    $nonce,
                    $caller->getAppSecret(),
                ]);
                $ServerSign = md5($rawText);
            }

            if ('HMAC-SHA1-1.0' === $signType || 'sha1-1.0' === $signType) {
                $rawText = implode('', [
                    $request->getContent(),
                    $SignatureTimestamp,
                    $nonce,
                ]);
                $ServerSign = hash_hmac('sha1', $rawText, (string) $caller->getAppSecret());
            }

            if (null === $ServerSign) {
                // 找不到对应算法，说明可能不支持这个签名算法
                throw new SignErrorException();
            }

            if ($ServerSign !== $Signature) {
                $this->logger->warning('JsonRPC签名不通过', [
                    'serverSign' => $ServerSign,
                    'submitSign' => $Signature,
                    'rawText' => $rawText,
                ]);
                throw new SignErrorException();
            }
        } else {
            throw new SignRequiredException();
        }
    }
}
