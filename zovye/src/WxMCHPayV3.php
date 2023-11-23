<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use GuzzleHttp\Exception\RequestException;
use zovye\util\PayUtil;

class WxMCHPayV3
{
    private $config;

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
            'json' => [
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
            ],
        ];

        try {
            $response = PayUtil::getWxPayV3Builder($this->config)
                ->v3->transfer->batches
                ->post($data);

            $result = PayUtil::parseWxPayV3Response($response);

            if (is_error($result)) {
                return $result;
            }

            if (!empty($result['code'])) {
                return err($result['message'] ?? '请求失败！');
            }

            if ($result['batch_id']) {
                return $result;
            }

            return err('接口返回数据错误！');

        } catch (Exception $e) {
            if ($e instanceof RequestException) {
                $res = PayUtil::parseWxPayV3Response($e->getResponse());

                return err($res['message'] ?? '请求失败！');
            }

            return err($e->getMessage());
        }
    }

    /**
     * 转帐订单信息
     */
    public function transferInfo(string $batch_id, string $trade_no = ''): array
    {
        $data = [
            'query' => [
                'need_query_detail' => 'true',
                'detail_status' => 'ALL',
            ],
            'batch_id' => $batch_id,
        ];

        try {
            $response = PayUtil::getWxPayV3Builder($this->config)
                ->v3->transfer->batches->batch_id->_batch_id_
                ->get($data);

            $result = PayUtil::parseWxPayV3Response($response);

            if (is_error($result)) {
                return $result;
            }

            if (!empty($result['code'])) {
                return err($result['message'] ?? '请求失败！');
            }

            $list = (array)$result['transfer_detail_list'];
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

            if ($result['transfer_batch']) {
                $batch = $result['transfer_batch'];
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

        } catch (Exception $e) {
            if ($e instanceof RequestException) {
                $res = PayUtil::parseWxPayV3Response($e->getResponse());

                return err($res['message'] ?? '请求失败！');
            }

            return err($e->getMessage());
        }
    }
}