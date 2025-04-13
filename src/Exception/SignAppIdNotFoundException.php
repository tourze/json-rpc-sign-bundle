<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcException;

class SignAppIdNotFoundException extends JsonRpcException
{
    public function __construct(string $message = '找不到AppID', array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct(-32600, $message, $data, $previous);
    }
}
