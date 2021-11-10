<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class AliTicket
{
    const API_URL = 'https://c.api.aqiinfo.com/ChannelAliApi/AliTicket';
    const API_VM_URL = 'https://c.api.aqiinfo.com/ChannelApi';

    const HEAD_IMAGE_URL = MODULE_URL . 'static/img/tmall.jpeg';

    const RESPONSE = '{"code":200}';

    private $app_key;
    private $app_secret;

    public function __construct(string $app_key, string $app_secret)
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
    }

    public function fetchOne(userModelObj $user, deviceModelObj $device)
    {
        $profile = $user->profile();
        $params = [
            'appKey' => $this->app_key,
            'exUid' => $profile['openid'],
            'city'  => $profile['city'],
            'vmid'  => $device->getImei(),
            'extra' => "zovye:{$device->getShadowId()}",
            'time'  => time(),
        ];

        $params['ufsign'] = self::sign($params, $this->app_secret);

        $result = Util::post(self::API_URL, $params, false);

        Util::logToFile('aliTicket', [
            'method' => 'fetch',
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

    public function syncVM($device_uid, $params = [])
    {
        $params = [
            'appKey' => $this->app_key,
            'vmid' => $device_uid,
            'province' => strval($params['province']),
            'city' => strval($params['city']),
            'district' => strval($params['district']),
            'name' => strval($params['name']),
            'addressDetail' => strval($params['addressDetail']),
            'floor' => strval($params['floor']),
            'firstScene' => strval($params['firstScene']),
            'secondScene' => strval($params['secondScene']),
            'deviceType' => strval($params['deviceType']),
            'deviceModel' => strval($params['deviceModel']),
            'time' => time(),
        ];

        $params['ufsign'] = self::sign($params, $this->app_secret);

        $result = Util::post(self::API_VM_URL . '/SyncVm', $params, false);

        Util::logToFile('aliTicket', [
            'method' => 'syncVm',
            'params' => $params,
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

        return true;
    }

    public function vmStatus($device_uid)
    {
        $params = [
            'appKey' => $this->app_key,
            'vmid' => $device_uid,
            'time' => time(),
        ];

        $params['ufsign'] = self::sign($params, $this->app_secret);

        $result = Util::post(self::API_VM_URL . '/VmStatus', $params, false);

        Util::logToFile('aliTicket', [
            'method' => 'VmStatus',
            'params' => $params,
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

        return true;
    }

    public function CancelVm($device_uid)
    {
        $params = [
            'appKey' => $this->app_key,
            'vmid' => $device_uid,
            'time' => time(),
        ];

        $params['ufsign'] = self::sign($params, $this->app_secret);

        $result = Util::post(self::API_VM_URL . '/CancelVm', $params, false);

        Util::logToFile('aliTicket', [
            'method' => 'cancelVm',
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

        return true;
    }

    public function verifyData($params)
    {
        if ($params['appKey'] !== $this->app_key || self::sign($params, $this->app_secret) !== $params['ufsign']) {
            return err('签名校验失败！');
        }
        return true;
    }


    public static function sign($data, $secret): string
    {
        ksort($data);

        $arr = [];
        foreach ($data as $key => $val) {
            if ($key == 'ufsign') {
                continue;
            }
            $arr[] = "$key=$val";
        }

        $str = implode('&', $arr);
        return md5(hash_hmac('sha1', $str, $secret, true));
    }

    public static function getDeviceJoinStatus(deviceModelObj $device)
    {
        $config = settings('custom.aliTicket', []);
        if (isEmptyArray($config) || empty($config['key'])) {
            return err('未配置！');
        }

        return (new AliTicket($config['key'], $config['secret']))->vmStatus($device->getImei());
    }

    public static function registerDevice(deviceModelObj $device)
    {
        $config = settings('custom.aliTicket', []);
        if (isEmptyArray($config) || empty($config['key'])) {
            return err('未配置！');
        }
        $params = $device->settings('aliTicket', []);
        $params['deviceModel'] = $device->getDeviceModel();
        $params['name'] = $device->getName();
        return (new AliTicket($config['key'], $config['secret']))->syncVM($device->getImei(), $params);
    }

    public static function unregisterDevice(deviceModelObj $device)
    {
        $config = settings('custom.aliTicket', []);
        if (isEmptyArray($config) || empty($config['key'])) {
            return err('未配置！');
        }
        $params = $device->settings('aliTicket', []);
        $params['deviceModel'] = $device->getDeviceModel();
        $params['name'] = $device->getName();
        return (new AliTicket($config['key'], $config['secret']))->CancelVm($device->getImei());
    }

    public static function getCallbackUrl(): string
    {
        return Util::murl('ali', ['op' => 'ticket']);
    }

    public static function fetch(userModelObj $user, deviceModelObj $device)
    {
        $config = settings('custom.aliTicket', []);
        if (isEmptyArray($config) || empty($config['key'])) {
            return err('未配置！');
        }

        return (new AliTicket($config['key'], $config['secret']))->fetchOne($user, $device);
    }

    public static function fetchAsAccount(userModelObj $user, deviceModelObj $device, $redirect = false)
    {
        $result = AliTicket::fetch($user, $device);
        if (empty($result) || empty($result['ticket']) || empty($result['url'])) {
            return err('返回数据不正确！');
        }

        if (is_error($result)) {
            return $result;
        }

        $data = [
            'name' => $result['name'],
            'title' => settings('custom.aliTicket.title', $result['name']),
            'descr' => $result['name'],
            'clr' => Util::randColor(),
            'img' => AliTicket::HEAD_IMAGE_URL,
        ];

        if ($redirect) {
            $data['redirect_url'] = $result['url'];
        } else {
            $res = Util::createQrcodeFile("ali_ticket{$result['ticket']}", $result['url']);
            if (is_error($res)) {
                Util::logToFile('aliTicket', [
                    'error' => 'fail to createQrcode file',
                    'result' => $res,
                ]);            
            } else {
                $data['qrcode'] = Util::toMedia($res);
                $data['url'] = Util::toMedia($res);
            }
        }        
        return $data;
    }

    public static function cb()
    {
        $config = settings('custom.aliTicket', []);
        if (empty($config) || empty($config['key']) || empty($config['secret'])) {
            return err('配置不正确！');
        }

        $raw = request::raw();
        if (empty($raw)) {
            return err('数据为空！');
        }

        parse_str($raw, $data);

        $res = (new AliTicket($config['key'], $config['secret']))->verifyData($data);
        if (is_error($res)) {
            return $res;
        }

        $user = User::get($data['exUid'], true);
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        $extra = explode(':', $data['extra'], 3);
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

        $total = max(1, intval($config['goodsNum']));
        if ($goods['num'] < $total) {
            return err('商品库存数量不够！');
        }

        $price = intval($config['bonus']);
        list($order_no, $pay_log) = Pay::prepareDataWithPay('ali_ticket', $device, $user, $goods, [
            'order_no' => $data['tradeNo'],
            'src' => Order::ALI_TICKET,
            'level' => LOG_GOODS_ADVS,
            'total' => $total,
            'price' => $price,
        ]);

        if (is_error($order_no)) {
            return $order_no;
        }

        $payResult =  [
            'result' => 'success',
            'type' => 'AliTicket',
            'orderNO' => $order_no,
            'transaction_id' => $data['tradeNo'],
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

    public static function getSceneList(): array
    {
        return [
            '政府机构' => ['政府机构'],
            '医院/医疗机构' => [
                '综合医院',
                '妇幼医院/产科',
                '体检中心',
                '口腔齿科',
                '儿科医院',
                '综合药房/保健品销售',
                '社区医院',
                '专科医院',
                '其他诊疗机构',
            ],
            '学校' => [
                '小学',
                '中学',
                '高中',
                '普通高等学校',
                '成人高等学校',
            ],
            '休闲娱乐' => [
                '电影院',
                '演出/展览馆/文化艺术',
                'KTV/酒吧',
                '按摩/足疗/洗浴/汗蒸',
                '游乐游艺',
                '中医养生',
                '游乐园',
                '温泉',
            ],
            '网吧网咖' => [
                '网咖',
                '电竞馆',
            ],
            '生活服务' => [
                '健身/体育/游泳/球类运动/舞蹈',
                '亲子/早教/儿童服务',
                '美容/美甲',
                '美发沙龙',
                '银行/邮局/储蓄',
                '房屋地产',
                '宠物医院',
                '宠物店',
                '通信营业厅',
                '彩票销售点/保险营业厅',
                '物流配送站',
                '社会公共服务机构',
                '其他',
            ],
            '社区' => [
                '中高端社区',
                '普通社区',
            ],
            '商超' => [
                '大卖场/连锁超市',
                '便利店',

            ],
            '酒店' => [
                '高档酒店（4~5星）',
                '经济连锁',
                '民宿',
                '度假村',
                '公寓',
            ],
            '街边零售' => [
                '单一品牌零售店',
                '集合品牌零售店',
                '体验型零售店/书店/手作',
            ],
            '街边餐饮' => [
                '中高端餐厅（客单100起）',
                '快餐小吃/面包甜点/咖啡',
            ],
            '交通出行' => [
                '机场',
                '火车站',
                '地铁站',
                '公交站',
                '汽车站',
                '加油站',
                '轮船站',
                '高速公路服务区',
                '停车场',
                '过境口岸',
            ],
            '公共区域/设施' => [
                '城市观光道',
                '城市绿化带',
                '城市公园/景区',
            ],
            '办公场所/园区' => [
                'CBD',
                '工业园区',
                '创业园区',
                '联合办公/SOHO',
                '部队',
                '写字楼',
                '研究场所',
                '员工宿舍',
            ],
            '百货/购物中心' => [
                '（场内）电影院',
                '（场内）演出/展览馆/文化艺术',
                '（场内）KTV/酒吧',
                '（场内）游乐游艺',
                '（场内）健身/体育/游泳/球类运动/舞蹈',
                '（场内）亲子/早教/儿童服务',
                '（场内）美容/美甲',
                '（场内）美发沙龙',
                '（场内）中高端餐厅（客单100起）',
                '（场内）快餐小吃/面包甜点/咖啡',
                '（场内）单一品牌零售店',
                '（场内）集合品牌零售店',
                '（场内）体验型零售店/书店/手作',
                '（场内）大卖场/连锁超市',
                '（场内）便利店',
                '大堂/中厅/走道',
                '步行街',
                '商业街',
                '小吃街',
                '家具建材城',
                '百货批发',
                '数码城',
            ],
            '爱车' => [
                '汽车保养维修中心',
                '4S店/汽车销售',
                '汽车租赁店',
                '驾校',
            ],
        ];
    }

    public static function getDeviceTypes(): array
    {
        return [
            '售货机',
            '摇摇车',
            '共享打印机',
            '共享纸巾',
            '体重秤',
            '洗衣机',
            '聚合支付',
            '兑币机',
            '唱歌机',
            '按摩椅',
            '智慧迎宾屏',
            '共享充电宝',
            '互动拍照',
            '娃娃机',
            '店头互动游戏',
            '商场大屏互动游戏',
            '互动游戏',            
        ];
    }
}
