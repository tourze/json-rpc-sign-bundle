# JSON-RPC Sign Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![License](https://img.shields.io/github/license/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://github.com/tourze/json-rpc-sign-bundle/blob/master/LICENSE)

This bundle provides signature verification support for JSON-RPC services in Symfony applications, inspired by Alibaba Cloud's signature design.

## Features

- JSON-RPC request signature verification
- Support for different signature algorithms (MD5, HMAC-SHA1)
- Attribute-based signature verification for JSON-RPC methods
- Event-driven architecture for JSON-RPC request interception
- Configurable time tolerance for signature verification
- API caller identification and validation

## Installation

Install via Composer:

```bash
composer require tourze/json-rpc-sign-bundle
```

## Quick Start

### 1. Register the Bundle

In your `config/bundles.php`:

```php
<?php

return [
    // ...
    Tourze\JsonRPCSignBundle\JsonRPCSignBundle::class => ['all' => true],
];
```

### 2. Mark Methods for Signature Verification

Add the `CheckSign` attribute to any class that needs signature verification:

```php
<?php

namespace App\JsonRPC;

use Tourze\JsonRPCSignBundle\Attribute\CheckSign;

#[CheckSign]
class SecureService
{
    public function sensitiveMethod(array $params): array
    {
        // This method requires a valid signature
        return [
            'status' => 'success',
            'data' => $params,
        ];
    }
}
```

### 3. Making Signed Requests

When calling a protected JSON-RPC method, include the following headers:

```
Signature-AppID: your_app_id
Signature-Nonce: random_32_character_string
Signature-Timestamp: current_unix_timestamp
Signature-Method: HMAC-SHA1 (or MD5)
Signature-Version: 1.0
Signature: your_calculated_signature
```

### 4. Signature Algorithm

The signature is calculated as follows:

1. Concatenate the request payload, timestamp, and nonce
2. Apply HMAC-SHA1 or MD5 algorithm with the app secret as the key
3. Include the resulting signature in the request headers

Example PHP code for generating a signature:

```php
$payload = json_encode($yourData);
$timestamp = time();
$nonce = bin2hex(random_bytes(16)); // random string
$appSecret = 'your_app_secret';

// For HMAC-SHA1
$rawText = $payload . $timestamp . $nonce;
$signature = hash_hmac('sha1', $rawText, $appSecret);

// For MD5
$rawText = $payload . $timestamp . $nonce . $appSecret;
$signature = md5($rawText);
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
