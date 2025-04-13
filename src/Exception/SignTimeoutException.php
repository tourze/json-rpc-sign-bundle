<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcException;

/**
 * 签名超时
 *
 * 客户端对数据进行签名时，我们同时会要求客户端传入时间戳。服务端需要对这个时间戳进行时间校验，并允许一定的时间差，一般都是允许几分钟内误差。
 * 通过这种设计，我们可以减少请求被重放的风险。
 */
class SignTimeoutException extends JsonRpcException
{
    public function __construct(string $message = '签名过期', array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct(-32600, $message, $data, $previous);
    }
}
