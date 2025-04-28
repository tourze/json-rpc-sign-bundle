# JSON-RPC 签名验证包

[English](README.md) | [中文](README.zh-CN.md)

[![最新版本](https://img.shields.io/packagist/v/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![开源协议](https://img.shields.io/github/license/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://github.com/tourze/json-rpc-sign-bundle/blob/master/LICENSE)

本扩展包为 Symfony 应用中的 JSON-RPC 服务提供签名验证支持，设计灵感来源于阿里云的开放服务签名机制。

## 功能特性

- JSON-RPC 请求签名验证
- 支持多种签名算法（MD5、HMAC-SHA1）
- 基于 PHP 8 Attribute 的 JSON-RPC 方法签名验证
- 基于事件驱动的 JSON-RPC 请求拦截架构
- 可配置的签名时间容差
- API 调用方识别与验证

## 安装

通过 Composer 安装:

```bash
composer require tourze/json-rpc-sign-bundle
```

## 快速开始

### 1. 注册 Bundle

在 `config/bundles.php` 文件中:

```php
<?php

return [
    // ...
    Tourze\JsonRPCSignBundle\JsonRPCSignBundle::class => ['all' => true],
];
```

### 2. 为需要验证签名的方法添加标记

为需要签名验证的类添加 `CheckSign` 属性:

```php
<?php

namespace App\JsonRPC;

use Tourze\JsonRPCSignBundle\Attribute\CheckSign;

#[CheckSign]
class SecureService
{
    public function sensitiveMethod(array $params): array
    {
        // 此方法需要有效的签名才能访问
        return [
            'status' => 'success',
            'data' => $params,
        ];
    }
}
```

### 3. 发送带签名的请求

调用受保护的 JSON-RPC 方法时，需要包含以下头信息:

```
Signature-AppID: 你的应用ID
Signature-Nonce: 32位随机字符串
Signature-Timestamp: 当前UNIX时间戳
Signature-Method: HMAC-SHA1 (或 MD5)
Signature-Version: 1.0
Signature: 计算得出的签名
```

### 4. 签名算法

签名计算方法如下:

1. 将请求载荷、时间戳和随机字符串连接起来
2. 使用应用密钥作为密钥，应用 HMAC-SHA1 或 MD5 算法
3. 将生成的签名包含在请求头中

PHP 签名生成示例代码:

```php
$payload = json_encode($yourData);
$timestamp = time();
$nonce = bin2hex(random_bytes(16)); // 随机字符串
$appSecret = '你的应用密钥';

// HMAC-SHA1 签名
$rawText = $payload . $timestamp . $nonce;
$signature = hash_hmac('sha1', $rawText, $appSecret);

// MD5 签名
$rawText = $payload . $timestamp . $nonce . $appSecret;
$signature = md5($rawText);
```

## 签名工作流程

1. 客户端准备请求数据，包括 AppID、时间戳、随机字符串和请求载荷
2. 客户端使用 HMAC-SHA1 或 MD5 算法计算签名
3. 客户端发送请求，包含生成的签名和相关头信息
4. 服务端接收请求，提取签名信息
5. 服务端使用相同的算法计算签名
6. 服务端比较计算得出的签名与请求中的签名是否一致
7. 如果签名匹配且时间戳在有效期内，允许访问请求的方法
8. 如果验证失败，返回相应的错误信息

## 开源协议

本项目采用 MIT 开源协议。详细信息请查看 [许可证文件](LICENSE)。
