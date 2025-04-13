<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcException;

/**
 * 签名错误报错
 */
class SignErrorException extends JsonRpcException
{
    public function __construct(string $message = '签名错误', array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct(-32600, $message, $data, $previous);
    }
}
