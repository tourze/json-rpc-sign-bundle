# JSON-RPC 签名验证包

[English](README.md) | [中文](README.zh-CN.md)

[![最新版本](https://img.shields.io/packagist/v/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![下载量](https://img.shields.io/packagist/dt/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![PHP版本](https://img.shields.io/packagist/php-v/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![开源协议](https://img.shields.io/github/license/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://github.com/tourze/json-rpc-sign-bundle/blob/master/LICENSE)
[![构建状态](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![代码覆盖率](https://img.shields.io/codecov/c/github/tourze/php-monorepo?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)

一个为 Symfony 应用中的 JSON-RPC 服务提供签名验证支持的扩展包，设计灵感来源于阿里云的开放服务签名机制。
该包通过 HMAC-SHA1 或 MD5 算法验证请求签名，确保 API 安全性。

## 功能特性

- **签名验证**：完整的 JSON-RPC 请求签名验证系统
- **多种算法**：支持 MD5 和 HMAC-SHA1 签名算法
- **基于属性**：简单的 `#[CheckSign]` 属性即可保护方法
- **事件驱动**：与 Symfony 事件系统无缝集成
- **时间容差**：可配置的时间戳验证，防止重放攻击
- **API 管理**：内置的 API 调用方识别与验证
- **日志记录**：完整的日志记录，便于调试和监控

## 系统要求

- PHP 8.1 或更高版本
- Symfony 6.4 或更高版本
- ext-hash 扩展

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

```text
Signature-AppID: 你的应用ID
Signature-Nonce: 32位随机字符串
Signature-Timestamp: 当前UNIX时间戳
Signature-Method: HMAC-SHA1 (或 MD5)
Signature-Version: 1.0
Signature: 计算得出的签名
```

### 4. 签名算法

签名计算方法根据算法类型不同而不同：

#### HMAC-SHA1（推荐）

```php
$payload = json_encode($yourData);
$timestamp = time();
$nonce = bin2hex(random_bytes(16)); // 32位随机字符串
$appSecret = '你的应用密钥';

$rawText = $payload . $timestamp . $nonce;
$signature = hash_hmac('sha1', $rawText, $appSecret);
```

#### MD5（安全性较低）

```php
$payload = json_encode($yourData);
$timestamp = time();
$nonce = bin2hex(random_bytes(16)); // 32位随机字符串
$appSecret = '你的应用密钥';

$rawText = $payload . $timestamp . $nonce . $appSecret;
$signature = md5($rawText);
```

## 高级用法

### 环境变量

你可以设置以下环境变量用于开发：

```bash
# 允许使用特殊查询参数绕过签名验证
JSON_RPC_GOD_SIGN=your_secret_bypass_key
```

然后使用 `?__ignoreSign=your_secret_bypass_key` 在开发过程中绕过签名验证。

### 自定义配置

每个 API 调用方可以有：

- `appId`: API 调用方的唯一标识符
- `appSecret`: 用于签名生成的密钥
- `signTimeoutSecond`: 签名验证的自定义超时时间（默认：180 秒）
- `valid`: API 调用方是否有效

## 错误处理

该包为不同的错误场景提供了特定的异常：

- `SignAppIdMissingException`: 当缺少 Signature-AppID 头时
- `SignAppIdNotFoundException`: 当提供的 AppID 未找到或无效时
- `SignNonceMissingException`: 当缺少 Signature-Nonce 头时
- `SignRequiredException`: 当没有提供签名时
- `SignTimeoutException`: 当时间戳超出容差窗口时
- `SignErrorException`: 当签名验证失败时

## 安全考虑

1. **时间戳验证**: 该包验证请求时间戳以防止重放攻击
2. **随机字符串唯一性**: 虽然该包不存储随机字符串，但你应该实现随机字符串跟踪以获得最大安全性
3. **需要 HTTPS**: 在生产环境中始终使用 HTTPS 保护传输中的签名
4. **密钥管理**: 保证应用密钥安全并定期轮换

## 测试

运行测试套件：

```bash
./vendor/bin/phpunit packages/json-rpc-sign-bundle/tests
```

## 贡献

请查看 [CONTRIBUTING.md](CONTRIBUTING.md) 了解如何为此项目贡献。

## 开源协议

本项目采用 MIT 开源协议。详细信息请查看 [许可证文件](LICENSE)。