<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use GuzzleHttp\Exception\RequestException;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class WxMCHPayV3
{
    private $config;
    private static $errMsg = [
        'SYSTEM_ERROR' => '微信系统错误，请稍后重试！',
        'APPID_MCHID_NOT_MATCH' => '商户号和appid没有绑定关系',
        'PARAM_ERROR' => '参数错误',
        'INVALID_REQUEST' => '参数错误',
        'NO_AUTH' => '商户信息不合法',
        'NOT_ENOUGH' => '资金不足',
        'ACCOUNTERROR' => '商户账户付款受限',
        'QUOTA_EXCEED' => '超出商户单日转账额度',
        'FREQUENCY_LIMITED' => '频率超限',
        'UNKNOWN' => '其它未知错误！',
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getV3Client(): ?BuilderChainable
    {
        static $client = null;

        if ($client == null) {
            // 设置参数
            // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
            $merchantPrivateKeyFilePath = "file://".$this->config['pem']['key'];
            $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

            // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
            $platformCertificateFilePath = "file://".$this->config['pem']['cert'];
            $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

            // 从「微信支付平台证书」中获取「证书序列号」
            $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);

            // 构造一个 APIv3 客户端实例
            $client = Builder::factory([
                'mchid' => $this->config['mch_id'],    // 商户号
                'serial' => $this->config['serial'],       // 「商户API证书」的「证书序列号」
                'privateKey' => $merchantPrivateKeyInstance,
                'certs' => [
                    $platformCertificateSerial => $platformPublicKeyInstance,
                ],
            ]);
        }

        return $client;
    }

    protected function getResponse($data)
    {
        try {
            $response = $this->getV3Client()->chain('v3/transfer/batches')->post(['json' => $data]);
            $contents = $response->getBody()->getContents();

            return json_decode($contents);
        } catch (Exception $e) {
            Log::error('v3_transfer', [
                'error' => $e->getMessage(),
            ]);
            if ($e instanceof RequestException && $e->hasResponse()) {
                $r = $e->getResponse();
                $contents = $r->getBody()->getContents();

                return json_decode($contents);
            }
        }

        return err('请求失败，请稍后再试！');
    }

    public function transferTo($openid, $trade_no, $money, string $desc = ''): array
    {
        if ($money < MCH_PAY_MIN_MONEY) {
            return error(State::ERROR, '提现金额不能小于'.number_format(MCH_PAY_MIN_MONEY / 100, 2).'元');
        }

        $data = [
            'appid' => $this->config['appid'],
            'out_batch_no' => $trade_no,
            'batch_name' => $desc,
            'batch_remark' => $desc,
            'total_amount' => $money,
            'total_num' => 1,
            'transfer_detail_list' => [
                [
                    'out_detail_no' => $trade_no,
                    'transfer_amount' => $money,
                    'transfer_remark' => $desc,
                    'openid' => $openid,
                ],
            ],
        ];

        $response = $this->getResponse($data);
        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            $code = $response['code'];
            return err(self::$errMsg[$code] ?? self::$errMsg['UNKNOWN']);
        }

        if ($response['batch_id']) {
            return [
                'partner_trade_no' => $response['out_batch_no'],
                'payment_no' => $response['batch_id'],
            ];
        }

        return err('接口返回数据错误！');
    }
}