<?php

namespace Tourze\JsonRPCSignBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AccessKeyBundle\AccessKeyBundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class JsonRPCSignBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            AccessKeyBundle::class => ['all' => true],
        ];
    }
}
