<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcException;

class SignRequiredException extends JsonRpcException
{
    public function __construct(string $message = '缺少必要的签名', array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct(-32600, $message, $data, $previous);
    }
}
