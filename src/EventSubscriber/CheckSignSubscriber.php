<?php

namespace Tourze\JsonRPCSignBundle\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\DoctrineHelper\ReflectionHelper;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPCSignBundle\Attribute\CheckSign;
use Tourze\JsonRPCSignBundle\Service\Signer;

/**
 * 签名检查，参考了阿里云开放服务的签名设计，做了一些调整。
 * 如果开启签名的话，接口变动可能很多，所以暂时没全部使用到。
 *
 * 签名算法：
 * 1. 将AppId加入到header，示例 Signature-AppID: 111111
 * 2. 获取一个32位内的随机字符串，传入header。如 Signature-Nonce: test123
 * 3. 获取当前系统时间戳，传入header。如 Signature-Timestamp: 1678856989
 * 4. 将要发送出去的payload、时间戳、Nonce合并成一个字符串，然后使用HMAC-SHA1生成签名，key使用AppSecret，参考代码 hash_hmac('sha1', $rawText, (string) $caller->getAppSecret());；
 * 5. 签名字符串加入到header，如 Signature: f383cba92442ad95f8ce7d61f6d5da5661381b6d
 *
 * @see https://help.aliyun.com/document_detail/131955.html
 */
#[WithMonologChannel('procedure')]
class CheckSignSubscriber
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Signer $signer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsEventListener]
    public function beforeMethodApply(BeforeMethodApplyEvent $event): void
    {
        $CheckSign = ReflectionHelper::getClassReflection($event->getMethod())->getAttributes(CheckSign::class);
        if (!$CheckSign) {
            // 不需要做签名处理
            return;
        }

        // TODO 这个对象最好是通过事件来传递
        $request = $this->requestStack->getMainRequest();
        if ($request->query->get('__ignoreSign') === ($_ENV['JSON_RPC_GOD_SIGN'] ?? 'god')) {
            return;
        }

        $this->signer->checkRequest($request);

        $this->logger->info('签名校验通过，允许访问接口', [
            'method' => $event->getMethod(),
            'request' => $request,
        ]);
    }
}
