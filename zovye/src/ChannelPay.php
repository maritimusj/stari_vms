<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;

class ChannelPay
{
    const API_URL = 'https://c.api.aqiinfo.com/ChannelApi/TouchOrderTicket';

    protected $app_key;
    protected $app_secret;

    /**
     * ChannelPay constructor.
     * @param $app_key
     * @param $app_secret
     */
    public function __construct($app_key, $app_secret)
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
    }

    public function makeSign($params = []): string
    {
        ksort($params);

        $aQuery = [];
        foreach ($params as $sKey => $sVal) {
            if ($sKey == 'ufsign') {
                continue;
            }
            $aQuery[] = "{$sKey}={$sVal}";
        }

        $sSignStr = implode('&', $aQuery);
        $sStr = hash_hmac('sha1', $sSignStr, $this->app_secret, true);
        return md5($sStr);
    }

    public function create($params = []): array
    {
        $params['appKey'] = $this->app_key;
        $params['ufsign'] = $this->makeSign($params);

        return Util::post(self::API_URL, $params, false);
    }

    public static function checkSign($data): bool
    {
        $settings = settings('pay.channel', []);
        if (empty($settings) || empty($settings['key']) || empty($settings['secret'])) {
            return false;
        }
        return (new ChannelPay($settings['key'], $settings['secret']))->makeSign($data) === $data['ufsign'];
    }

    /**
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param goodsModelObj $goods
     * @param int $num
     * @return mixed
     */
    public static function createOrder(deviceModelObj $device, userModelObj $user, goodsModelObj $goods, int $num = 1)
    {
        $settings = settings('pay.channel', []);
        if (empty($settings) || empty($settings['key']) || empty($settings['secret'])) {
            return err('没有配置！');
        }

        $discount = User::getUserDiscount($user, Goods::format($goods), $num);
        $price = $goods->getPrice() * $num - $discount;
        if ($price < 1) {
            return err('支付金额不能为零！');
        }

        list($order_no, $pay_log) = Pay::prepareDataWithPay('channel', $device, $user, Goods::format($goods), [
            'level' => LOG_GOODS_PAY,
            'total' => $num,
            'price' => $price,
            'discount' => $discount,
        ]);

        if (is_error($order_no)) {
            return $order_no;
        }

        $data = [
            'exUid' => $user->getOpenid(),
            'price' => $goods->getPrice(),
            'amount' => $goods->getPrice() * $num,
            'exSkuId' => "goods{$goods->getId()}x{$num}",
            'exSkuImg' => Util::toMedia($goods->getImg()),
            'exSkuName' => $goods->getName(),
            'time' => time(),
            'vmid' => $device->getImei(),
            'outTradeNo' => $order_no,
        ];

        $result = (new ChannelPay($settings['key'], $settings['secret']))->create($data);

        Util::logToFile('channel', [
            'request' => $data,
            'response' => $result,
        ]);

        if ($result['code'] != 200) {
            return err($result['msg']);
        }

        if (empty($result['data'])) {
            return err('返回数据为空！');
        }

        $pay_log->setData('channel', $result);

        return $result['data'];
    }
}