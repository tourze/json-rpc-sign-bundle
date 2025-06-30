<?php

namespace Tourze\JsonRPCSignBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCSignBundle\Exception\RequestNotAvailableException;

class RequestNotAvailableExceptionTest extends TestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new RequestNotAvailableException('Request is not available');
        
        $this->assertInstanceOf(RequestNotAvailableException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Request is not available', $exception->getMessage());
    }
    
    public function testExceptionCanBeCreatedWithCodeAndPreviousException(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new RequestNotAvailableException('Request error', 500, $previous);
        
        $this->assertEquals('Request error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}