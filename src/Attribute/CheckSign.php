<?php

namespace Tourze\JsonRPCSignBundle\Attribute;

/**
 * 如果方法做了这个标记，就会检查这个接口是否提交了签名数据
 */
#[\Attribute(flags: \Attribute::TARGET_CLASS)]
class CheckSign
{
    public function __construct()
    {
    }
}
