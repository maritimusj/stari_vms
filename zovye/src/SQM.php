<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class SQM 
{
    public static function getCallbackUrl(): string
    {
        return Util::murl('sqm', []);
    }

    public static function getJs(): string
    {
        return strval(settings('custom.SQMPay.js'));
    }

    public static function getGoodsNum(): int 
    {
        $num = settings('custom.SQMPay.goodsNum', 1);
        if ($num < 1) {
            $num = 1;
        }
        return $num;
    }

    public static function checkSign(string $input): bool
    {
        $data = json_decode($input, true);
        if (empty($data)) {
            return false;
        }

        ksort($data);

        $arr = [];
        foreach($data as $index => $v) {
            if ($index == 'sign') {
                continue;
            }
            $arr[] = "{$index}={$v}";
        }

        $app_secret = settings('custom.SQMPay.appSecret', '');
        return strtoupper(md5(implode('&', $arr) . "&appsecret=$app_secret")) === $data['sign'];
    }

    public static function createOrder(deviceModelObj $device, userModelObj $user, $goodsData, $num, $params = []): bool
    {
        $params['level'] = LOG_GOODS_ADVS;
        $params['total'] = $num;

        list($order_no, $pay_log) = Pay::prepareDataWithPay('SQM', $device, $user, $goodsData, $params);

        if (is_error($order_no)) {
            return false;
        }

        $payResult =  [
            'result' => 'success',
            'type' => 'SQM',
            'orderNO' => $order_no,
            'transaction_id' => $params['task_record_id'],
            'total' => $params['price'],
            'paytime' => $params['timestamp'],
            'openid' => $user->getOpenid(),
            'deviceUID' => $device->getImei(),
        ];

        $pay_log->setData('payResult', $payResult);
        
        $pay_log->setData('create_order.createtime', time());
        if (!$pay_log->save()) {
            return false;
        }

        //清除用户最后活动记录
        $user->setLastActiveData();

        return Job::createOrder($order_no, $device);
    }
}