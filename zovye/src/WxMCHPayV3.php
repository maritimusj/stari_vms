<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\PayUtil;

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

    public function transferTo($openid, $trade_no, $money, string $desc = ''): array
    {
        if ($money < MCH_PAY_MIN_MONEY) {
            return err('提现金额不能小于'.number_format(MCH_PAY_MIN_MONEY / 100, 2).'元');
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

        $response = PayUtil::getWxPayV3Client($this->config)->post('v3/transfer/batches', $data);
        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            if ($response['message']) {
                return err($response['message']);
            }

            $code = $response['code'];

            return err(self::$errMsg[$code] ?? self::$errMsg['UNKNOWN']);
        }

        if ($response['batch_id']) {
            return $response;
        }

        return err('接口返回数据错误！');
    }

    /**
     * 转帐订单信息
     */
    public function transferInfo(string $batch_id, string $trade_no = ''): array
    {
        $data = [
            'need_query_detail' => 'true',
            'detail_status' => 'ALL',
        ];

        $response = PayUtil::getWxPayV3Client($this->config)->get("v3/transfer/batches/batch-id/$batch_id", $data);
        if (is_error($response)) {
            return $response;
        }

        if (!empty($response['code'])) {
            if ($response['message']) {
                return err($response['message']);
            }
            $code = $response['code'];

            return err(self::$errMsg[$code] ?? self::$errMsg['UNKNOWN']);
        }

        $list = (array)$response['transfer_detail_list'];
        if ($list) {
            if ($trade_no) {
                foreach ($list as $i) {
                    if ($i && $i['out_detail_no'] == $trade_no) {
                        return $i;
                    }
                }

                return [];
            }

            return $list;
        }

        if ($response['transfer_batch']) {
            $batch = $response['transfer_batch'];
            if ($batch['batch_status'] == 'CLOSED') {
                return [
                    'detail_status' => 'FAIL',
                    'batch_id' => $batch['batch_id'],
                    'out_batch_no' => $batch['out_batch_no'],
                    'close_reason' => $batch['close_reason'],
                ];
            }
        }

        return err('接口返回数据错误！');
    }
}