<?php
namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class AliTicket
{
    const API_URL = 'https://c.api.aqiinfo.com/ChannelAliApi/AliTicket';
    const RESPONSE = '{"code":200}';

    private $app_key;
    private $app_secret;

    public static function getCallbackUrl()
    {
        return Util::murl('ali', ['op' => 'ticket']);
    }

    public function __construct(string $app_key, string $app_secret)
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
    }

    public function fetch(userModelObj $user, deviceModelObj $device)
    {
        $profile = $user->profile();
        $params = [
            'appKey'=> $this->app_key,
            'exUid' => $profile['openid'],
            'city'  => $profile['city'],
            'vmid'  => $device->getImei(),
            'sex'   => $profile['sex'],
            'extra' => "zovye:{$device->getShadowId()}",
            'time'  => time(),
        ];

        $params['ufsign'] = self::sign($params, $this->app_secret);

        $result = Util::post(self::API_URL, $params);

        Util::logToFile('ali_ticket', [
            'request' => $params,
            'response' => $result,
        ]);

        if (empty($result)) {
            return err('返回数据为空！');
        }

        if (is_error($result)) {
            return $result;
        }

        if ($result['code'] != 200) {
            return err(empty($result['msg']) ? '接口返回错误！' : $result['msg']);
        }

        if (empty($result['data'])) {
            return err('接口返回空数据！');
        }

        return $result['data'];
    }

    public static function sign($data, $secret)
    {
        unset($data['ufsign']);

        ksort($data);

        $arr = [];
        foreach ($data as $key => $val) {
            $arr[] = "{$key}={$val}";
        }

        $str = implode('&', $arr);
        return md5(hash_hmac('sha1', $str, $secret, true));
    }

    public function verifyData($params)
    {
        if (!App::isCustomAliTicketEnabled()) {
            return err('该功能没有启用！');
        }
        if ($params['appKey'] !== $this->app_key || self::sign($params, $this->app_secret) !== $params['ufsign']) {
            return err('签名校验失败！');
        }
        return true;
    }

    public static function cb()
    {
        $config = settings('custom.aliTicket', []);
        if (empty($config) || empty($config['key']) || empty($config['secret'])) {
            return err('配置不正确！');
        }
        $res = (new AliTicket($config['key'], $config['secret']))->verifyData(request::json());
        if (is_error($res)) {
            return $res;
        }

        $user = User::get(request::json('exUid'), true);
        if (empty($user)) {
            return err('找不到这个用户！');
        } 

        $extra = explode(':', request::json('extra'), 3);
        if (count($extra) != 2 || $extra[0] !== 'zovye') {
            return err('回调数据不正确！');
        }

        $device = Device::findOne(['shadow_id' => $extra[1]]);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $goods = $device->getGoodsByLane(0);
        if (empty($goods)) {
            return err('找不到商品！');
        }

        if ($goods['num'] < $config['goodsNum']) {
            return err('商品库存数量不够！');
        }

        $price = intval($config['price']);
        list($order_no, $pay_log) = Pay::prepareDataWithPay('ali_ticket', $device, $user, $goods, [
            'level' => LOG_GOODS_ADVS,
            'total' => $config['goodsNum'],
            'price' => $price,
            'order_no' => request::json('tradeNo'),
        ]);

        if (is_error($order_no)) {
            return $order_no;
        }

        $payResult =  [
            'result' => 'success',
            'type' => 'AliTicket',
            'orderNO' => $order_no,
            'transaction_id' => request::json('tradeNo'),
            'total' => $price,
            'paytime' => time(),
            'openid' => $user->getOpenid(),
            'deviceUID' => $device->getImei(),
        ];

        $pay_log->setData('payResult', $payResult);
        
        $pay_log->setData('create_order.createtime', time());
        if (!$pay_log->save()) {
            return err('无法保存数据！');
        }

        $res = Job::createOrder($order_no);
        if (!$res) {
            return err('无法启动出货任务！');
        }

        return true;
    }
}