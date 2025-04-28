# JSON-RPC Sign Bundle 工作流程

[English](#json-rpc-sign-bundle-workflow) | [中文](#json-rpc-sign-bundle-工作流程)

## JSON-RPC Sign Bundle Workflow

The following diagram illustrates the signature verification process for JSON-RPC requests:

```mermaid
sequenceDiagram
    participant Client
    participant Server as JSON-RPC Server
    participant Signer as Signature Service
    participant ApiCaller as API Caller Repository

    Client->>+Client: Prepare request payload
    Client->>Client: Generate random nonce
    Client->>Client: Get current timestamp
    Client->>Client: Concatenate payload + timestamp + nonce
    Client->>Client: Apply HMAC-SHA1/MD5 with AppSecret
    Client->>Client: Generate signature

    Client->>+Server: Send request with signature headers
    Note over Client,Server: Headers: Signature-AppID, Signature-Nonce, <br> Signature-Timestamp, Signature, etc.
    
    Server->>+Signer: CheckSignSubscriber intercepts request
    Signer->>Signer: Extract AppID from headers
    Signer->>+ApiCaller: Look up caller by AppID
    ApiCaller-->>-Signer: Return caller info with AppSecret
    
    Signer->>Signer: Check if timestamp is within tolerance
    Signer->>Signer: Concatenate payload + timestamp + nonce
    Signer->>Signer: Apply same algorithm with AppSecret
    Signer->>Signer: Compare calculated signature with request signature
    
    alt Signature Valid
        Signer-->>-Server: Allow request to proceed
        Server->>Server: Execute JSON-RPC method
        Server-->>-Client: Return JSON-RPC response
    else Invalid Signature
        Signer-->>-Server: Throw SignErrorException
        Server-->>-Client: Return error response
    end
```

## JSON-RPC Sign Bundle 工作流程

以下图表说明了 JSON-RPC 请求的签名验证过程：

```mermaid
sequenceDiagram
    participant Client as 客户端
    participant Server as JSON-RPC 服务器
    participant Signer as 签名服务
    participant ApiCaller as API 调用方存储库

    Client->>+Client: 准备请求载荷
    Client->>Client: 生成随机字符串(nonce)
    Client->>Client: 获取当前时间戳
    Client->>Client: 连接载荷 + 时间戳 + 随机字符串
    Client->>Client: 使用应用密钥进行 HMAC-SHA1/MD5 计算
    Client->>Client: 生成签名

    Client->>+Server: 发送带签名头的请求
    Note over Client,Server: 头信息：Signature-AppID, Signature-Nonce, <br> Signature-Timestamp, Signature 等
    
    Server->>+Signer: CheckSignSubscriber 拦截请求
    Signer->>Signer: 从头信息中提取 AppID
    Signer->>+ApiCaller: 通过 AppID 查找调用方
    ApiCaller-->>-Signer: 返回包含应用密钥的调用方信息
    
    Signer->>Signer: 检查时间戳是否在容差范围内
    Signer->>Signer: 连接载荷 + 时间戳 + 随机字符串
    Signer->>Signer: 使用应用密钥应用相同的算法
    Signer->>Signer: 比较计算得出的签名与请求签名
    
    alt 签名有效
        Signer-->>-Server: 允许请求继续处理
        Server->>Server: 执行 JSON-RPC 方法
        Server-->>-Client: 返回 JSON-RPC 响应
    else 签名无效
        Signer-->>-Server: 抛出 SignErrorException
        Server-->>-Client: 返回错误响应
    end
```
