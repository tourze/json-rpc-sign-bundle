<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcException;

class SignAppIdMissingException extends JsonRpcException
{
    public function __construct(string $message = '缺少必要的签名AppID', array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct(-32600, $message, $data, $previous);
    }
}
