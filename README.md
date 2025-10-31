# JSON-RPC Sign Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-sign-bundle)
[![License](https://img.shields.io/github/license/tourze/json-rpc-sign-bundle.svg?style=flat-square)](https://github.com/tourze/json-rpc-sign-bundle/blob/master/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)

A Symfony bundle that provides signature verification support for JSON-RPC services, 
inspired by Alibaba Cloud's signature design. This bundle ensures API security by 
validating request signatures using HMAC-SHA1 or MD5 algorithms.

## Features

- **Signature Verification**: Complete JSON-RPC request signature verification system
- **Multiple Algorithms**: Support for MD5 and HMAC-SHA1 signature algorithms
- **Attribute-based**: Simple `#[CheckSign]` attribute for method protection
- **Event-driven**: Seamless integration with Symfony's event system
- **Time Tolerance**: Configurable timestamp validation to prevent replay attacks
- **API Management**: Built-in API caller identification and validation
- **Logging**: Comprehensive logging for debugging and monitoring

## Requirements

- PHP 8.1 or higher
- Symfony 6.4 or higher
- ext-hash extension

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

```text
Signature-AppID: your_app_id
Signature-Nonce: random_32_character_string
Signature-Timestamp: current_unix_timestamp
Signature-Method: HMAC-SHA1 (or MD5)
Signature-Version: 1.0
Signature: your_calculated_signature
```

### 4. Signature Algorithm

The signature is calculated differently based on the algorithm:

#### HMAC-SHA1 (Recommended)

```php
$payload = json_encode($yourData);
$timestamp = time();
$nonce = bin2hex(random_bytes(16)); // 32-character random string
$appSecret = 'your_app_secret';

$rawText = $payload . $timestamp . $nonce;
$signature = hash_hmac('sha1', $rawText, $appSecret);
```

#### MD5 (Less Secure)

```php
$payload = json_encode($yourData);
$timestamp = time();
$nonce = bin2hex(random_bytes(16)); // 32-character random string
$appSecret = 'your_app_secret';

$rawText = $payload . $timestamp . $nonce . $appSecret;
$signature = md5($rawText);
```

## Advanced Usage

### Environment Variables

You can set the following environment variable for development:

```bash
# Allow bypassing signature verification with special query parameter
JSON_RPC_GOD_SIGN=your_secret_bypass_key
```

Then use `?__ignoreSign=your_secret_bypass_key` to bypass signature verification 
during development.

### Custom Configuration

Each API caller can have:

- `appId`: Unique identifier for the API caller
- `appSecret`: Secret key for signature generation
- `signTimeoutSecond`: Custom timeout for signature validation (default: 180 seconds)
- `valid`: Whether the API caller is active

## Error Handling

The bundle provides specific exceptions for different error scenarios:

- `SignAppIdMissingException`: When Signature-AppID header is missing
- `SignAppIdNotFoundException`: When the provided AppID is not found or invalid
- `SignNonceMissingException`: When Signature-Nonce header is missing
- `SignRequiredException`: When no signature is provided
- `SignTimeoutException`: When the timestamp is outside the tolerance window
- `SignErrorException`: When signature verification fails

## Security Considerations

1. **Timestamp Validation**: The bundle validates request timestamps to prevent 
   replay attacks
2. **Nonce Uniqueness**: While the bundle doesn't store nonces, you should implement 
   nonce tracking for maximum security
3. **HTTPS Required**: Always use HTTPS in production to protect signatures in transit
4. **Secret Management**: Keep your app secrets secure and rotate them regularly

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit packages/json-rpc-sign-bundle/tests
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to 
this project.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.