<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcException;

class SignNonceMissingException extends JsonRpcException
{
    public function __construct(string $message = '缺少必要的随机字符串', array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct(-32600, $message, $data, $previous);
    }
}
