<?php

namespace Tourze\JsonRPCSignBundle\Exception;

use Tourze\JsonRPC\Core\Exception\JsonRpcExceptionInterface;

class SignRequiredException extends \RuntimeException implements JsonRpcExceptionInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $message = '缺少必要的签名', private array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, -32600, $previous);
    }

    public function getErrorCode(): int
    {
        return $this->getCode();
    }

    public function getErrorMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorData(): array
    {
        return $this->data;
    }
}
