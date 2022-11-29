<?php

namespace zovye;

use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class WxPayV3
{
    public static function getClient(array $config): ?BuilderChainable
    {
        // 设置参数
        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyInstance = Rsa::from($config['pem']['key']);

        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformPublicKeyInstance = Rsa::from($config['pem']['cert'], Rsa::KEY_TYPE_PUBLIC);

        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($config['pem']['cert']);

        // 构造一个 APIv3 客户端实例
        return Builder::factory([
            'mchid' => $config['mch_id'],         // 商户号
            'serial' => $config['serial'],        // 「商户API证书」的「证书序列号」
            'privateKey' => $merchantPrivateKeyInstance,
            'certs' => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
    }
}