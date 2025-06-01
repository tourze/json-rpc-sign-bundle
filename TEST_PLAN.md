# JSON-RPC Sign Bundle 测试计划

## 测试用例清单

### 📁 Attribute 模块
| 文件 | 测试类 | 关注场景 | 状态 | 通过 |
|------|--------|----------|------|------|
| `src/Attribute/CheckSign.php` | `CheckSignTest` | ✅ 属性创建、类目标、应用到类 | ✅ 完成 | ✅ 通过 |

### 📁 DependencyInjection 模块  
| 文件 | 测试类 | 关注场景 | 状态 | 通过 |
|------|--------|----------|------|------|
| `src/DependencyInjection/JsonRPCSignExtension.php` | `JsonRPCSignExtensionTest` | ✅ 加载配置、服务注册 | ✅ 完成 | ✅ 通过 |

### 📁 Exception 模块
| 文件 | 测试类 | 关注场景 | 状态 | 通过 |
|------|--------|----------|------|------|
| `src/Exception/SignAppIdMissingException.php` | `SignAppIdMissingExceptionTest` | ✅ 默认值、自定义消息、前一异常 | ✅ 完成 | ✅ 通过 |
| `src/Exception/SignAppIdNotFoundException.php` | `SignAppIdNotFoundExceptionTest` | ✅ 默认值、自定义消息、前一异常 | ✅ 完成 | ✅ 通过 |
| `src/Exception/SignErrorException.php` | `SignErrorExceptionTest` | ✅ 默认值、自定义消息、前一异常 | ✅ 完成 | ✅ 通过 |
| `src/Exception/SignNonceMissingException.php` | `SignNonceMissingExceptionTest` | ✅ 默认值、自定义消息、前一异常 | ✅ 完成 | ✅ 通过 |
| `src/Exception/SignRequiredException.php` | `SignRequiredExceptionTest` | ✅ 默认值、自定义消息、前一异常 | ✅ 完成 | ✅ 通过 |
| `src/Exception/SignTimeoutException.php` | `SignTimeoutExceptionTest` | ✅ 默认值、自定义消息、前一异常 | ✅ 完成 | ✅ 通过 |

### 📁 Bundle 主类
| 文件 | 测试类 | 关注场景 | 状态 | 通过 |
|------|--------|----------|------|------|
| `src/JsonRPCSignBundle.php` | `JsonRPCSignBundleTest` | 🔄 Bundle 继承、实例创建 | 🔄 进行中 | ⏳ 待测 |

### 📁 EventSubscriber 模块
| 文件 | 测试类 | 关注场景 | 状态 | 通过 |
|------|--------|----------|------|------|
| `src/EventSubscriber/CheckSignSubscriber.php` | `CheckSignSubscriberTest` | 🔄 事件监听、签名检查、忽略标记、日志记录 | 🔄 进行中 | ⏳ 待测 |

### 📁 Service 模块
| 文件 | 测试类 | 关注场景 | 状态 | 通过 |
|------|--------|----------|------|------|
| `src/Service/Signer.php` | `SignerTest` | 🔄 请求解析、签名验证、多种算法、时间校验 | 🔄 进行中 | ⏳ 待测 |

## 测试重点场景

### CheckSignSubscriber 测试重点
- ✅ 无 CheckSign 属性时跳过验证
- ✅ 有 CheckSign 属性时进行签名验证  
- ✅ 忽略签名参数的处理
- ✅ 验证通过后的日志记录
- ✅ 验证失败时的异常抛出

### Signer 测试重点
- ✅ 请求头信息提取（AppID、Nonce、SignatureMethod等）
- ✅ MD5 签名算法验证
- ✅ HMAC-SHA1 签名算法验证  
- ✅ 时间戳校验（容差范围内/外）
- ✅ API 调用方验证（存在/不存在）
- ✅ 各种异常场景的覆盖
- ✅ 边界测试（空值、无效值等）

## 当前进度
- ✅ 已完成：Exception 类测试、Attribute 测试、DI 测试
- 🔄 进行中：Bundle 主类、EventSubscriber、Service 测试
- ⏳ 待完成：综合集成测试

## 测试覆盖率目标
- 目标覆盖率：95%+
- 分支覆盖率：90%+
- 异常场景覆盖：100% 