<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;
use zovye\business\Charging;
use zovye\business\Fueling;
use zovye\contract\IPay;
use zovye\domain\CommissionBalance;
use zovye\domain\Device;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\domain\PayLogs;
use zovye\domain\PaymentConfig;
use zovye\domain\User;
use zovye\model\deviceModelObj;
use zovye\model\pay_logsModelObj;
use zovye\model\payment_configModelObj;
use zovye\model\userModelObj;
use zovye\payment\LCSWPay;
use zovye\payment\SQBPay;
use zovye\payment\WXPay;
use zovye\payment\WxPayV3Merchant;
use zovye\payment\WxPayV3Partner;

class Pay
{
    //微信公众号
    const WX = 'wx';

    //微信支付v3
    const WX_V3 = 'wx_v3';

    //支付宝
    const ALI = 'ali';

    //扫呗
    const LCSW = 'lcsw';

    //收钱吧
    const SQB = 'SQB';

    static $names = [
        self::WX => '微信支付',
        self::WX_V3 => '微信支付v3',
        self::ALI => '支付宝',
        self::LCSW => '扫呗',
        self::SQB => '收钱吧',
    ];

    public static function getTitle($name): string
    {
        return self::$names[$name] ?? '未知';
    }

    /**
     * 从支付记录创建支付对象
     * @param pay_logsModelObj $log
     * @return IPay
     */
    public static function rebuildPay(pay_logsModelObj $log): IPay
    {
        $config_id = $log->getPayConfigId();
        if (empty($config_id)) {
            //尝试兼容原系统的支付配置
            $device = $log->getDevice();
            if ($device) {
                $user = User::get($log->getUserOpenid(), true);
                if ($user) {
                    $pay = self::selectPay($device, $user);
                    if ($pay) {
                        return $pay;
                    }
                }
            }
            throw new RuntimeException('支付不可用！[01]');
        }

        $config = PaymentConfig::get($config_id);
        if (!$config) {
            throw new RuntimeException('找不到这个支付配置！');
        }

        $pay = self::makeWithDevice($config, $log->getDevice());
        if (!$pay) {
            throw new RuntimeException('支付不可用！[02]');
        }

        return $pay;
    }

    /**
     * 根据设备和用户，选择对应的支付对象
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return IPay|null
     */
    public static function selectPay(deviceModelObj $device, userModelObj $user): ?IPay
    {
        if ($user->isAliUser() || Session::isAliUser()) {
            $names = [self::LCSW, self::SQB];
        } else {
            $names = [self::LCSW, self::SQB, self::WX_V3, self::WX];
        }

        $matchFN = function (userModelObj $user, payment_configModelObj $config) {
            if (($user->isWxUser() || Session::isWxUser()) && $config->isEnabled('wx.h5')) {
                return true;
            }

            if (($user->isWXAppUser() || Session::isWxAppUser()) && $config->isEnabled('wx.mini_app')) {
                return true;
            }

            if (($user->isAliUser() || Session::isAliUser()) && $config->isEnabled('ali')) {
                return true;
            }

            return false;
        };

        $agent = $device->getAgent();
        if ($agent && PaymentConfig::hasAny($agent)) {
            foreach ($names as $name) {
                /** @var payment_configModelObj $config */
                $config = PaymentConfig::getFor($agent, $name);

                if ($config && $matchFN($user, $config)) {
                    return self::makeWithDevice($config, $device);
                }
            }

            //如果代理商开启了独立支付，在没有匹配到支付配置的情况下，直接返回null
            return null;
        }

        foreach ($names as $name) {
            /** @var payment_configModelObj $config */
            $config = PaymentConfig::getDefaultByName($name);

            if ($config && $matchFN($user, $config)) {
                return self::make($config);
            }
        }

        return null;
    }

    /**
     * 获取支付需要的Js，函数会根据指定的设备和用户，获取特定的支付配置
     * @return mixed
     */
    public static function getPayJs(deviceModelObj $device, userModelObj $user)
    {
        $pay = self::selectPay($device, $user);
        if (!$pay) {
            return err('支付暂时不可用！');
        }

        return $pay->getPayJs($device, $user);
    }

    /**
     * $pay_data['total']指定商品数量，未指定则默认为1
     * $pay_data['price']指定总价格，未指定则使用单个商品价格
     *
     */
    private static function prepareData(
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        $pay = self::selectPay($device, $user);
        if (!$pay) {
            return err('支付暂时不可用！');
        }

        list($order_no,) = self::prepareDataWithPay($pay, $device, $user, $goods, $pay_data);
        if (is_error($order_no)) {
            return $order_no;
        }

        return [$pay, $order_no];
    }

    public static function prepareDataWithPay(
        IPay $pay,
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        if ($pay_data['order_no']) {
            $order_no = $pay_data['order_no'];
        } else {
            $order_no = Order::makeUID($user, $device, $pay_data['serial'] ?? time());
        }

        $config = $pay->getConfig();

        $more = [
            'device' => $device->getId(),
            'user' => $user->getOpenid(),
            'pay' => [
                'config_id' => $config['config_id'],
                'name' => $pay->getName(),
            ],
            'orderData' => [
                'orderNO' => $order_no,
                'num' => $pay_data['total'] ?? 1,
                'price' => $pay_data['price'] ?? $goods['price'],
                'ip' => CLIENT_IP,
                'extra' => [],
                'createtime' => time(),
            ],
        ];

        if (!empty($goods['is_package'])) {
            $more['package'] = $goods['id'];
            $more['orderData']['extra']['package'] = $goods;
        } else {
            $more['goods'] = $goods['id'];
            $more['orderData']['extra']['goods'] = $goods;
        }

        $pay_data = array_merge_recursive($pay_data, $more);

        $pay_log = self::createPayLog($user, $order_no, $pay_data);
        if (empty($pay_log)) {
            return [err('无法保存支付信息！'), null];
        }

        return [$order_no, $pay_log];
    }

    private static function createPay(
        $fn,
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        $result = self::prepareData($device, $user, $goods, $pay_data);
        if (is_error($result)) {
            return ['', $result];
        }

        /** @var IPay $pay */
        list($pay, $order_no) = $result;

        $goods_name = !empty($goods['name']) ? $goods['name'] : (!empty($goods['title']) ? $goods['title'] : '未命名');
        if (!empty($pay_data['total'])) {
            $title = "{$goods_name}x{$pay_data['total']}{$goods['unit_title']}";
        } else {
            $title = $goods_name;
        }

        $price = empty($pay_data['price']) ? $goods['price'] : $pay_data['price'];

        if (is_callable([$pay, $fn])) {
            $res = $pay->$fn($user->getOpenid(), $device->getImei(), $order_no, $price, $title);
        } else {
            Log::debug('pay', [
                'result' => $result,
                'pay' => $pay,
                'fn' => $fn,
                'goods' => $goods,
                'data' => $pay_data,
                'user' => $user->profile(),
            ]);
            trigger_error('无效的支付函数！', E_USER_ERROR);
        }

        return [$order_no, $res];
    }

    /**
     * 创建支付订单
     * @param deviceModelObj $device 设备
     * @param userModelObj $user 用户
     * @param array $goods 商品信息
     * @return mixed error或者支付数据
     */
    public static function createXAppPay(
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        return self::createPay('createXAppPay', $device, $user, $goods, $pay_data);
    }

    /**
     * 创建支付订单
     * @param deviceModelObj $device 设备
     * @param userModelObj $user 用户
     * @param array $goods 商品信息
     * @return mixed error或者支付数据
     */
    public static function createJsPay(
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        return self::createPay('createJsPay', $device, $user, $goods, $pay_data);
    }

    public static function createQRCodePay(
        deviceModelObj $device,
        string $code,
        array $goods,
        array $pay_data = []
    ): array {
        return self::createPay(
            'createQRCodePay',
            $device,
            User::getPseudoUser($code, '<匿名用户>'),
            $goods,
            $pay_data
        );
    }

    /**
     * 处理支付的通知数据
     * @param int $config_id
     * @param string $input
     * @return mixed
     */
    public static function notify(int $config_id, string $input)
    {
        $pay = null;
        try {
            $config = PaymentConfig::get($config_id);
            if (!$config) {
                throw new RuntimeException('不正确的支付配置id！');
            }

            $pay = self::make($config);
            if (!$pay) {
                throw new RuntimeException('支付不可用！');
            }

            $data = $pay->decodeData($input);
            if (empty($data)) {
                throw new RuntimeException('回调数据为空！');
            }

            if (is_error($data)) {
                throw new RuntimeException($data['message']);
            }

            if (!$pay->checkResult($data['raw'])) {
                throw new RuntimeException('回调数据异常！');
            }

            if (!Locker::try("pay:{$data['orderNO']}", REQUEST_ID, 3)) {
                throw new RuntimeException('无法锁定支付记录！');
            }

            $pay_log = self::getPayLog($data['orderNO']);
            if (empty($pay_log)) {
                throw new RuntimeException('找不到支付记录！');
            }

            $pay_log->setData('payResult', $data);
            $pay_log->setData('create_order.createtime', time());

            if (!$pay_log->save()) {
                throw new RuntimeException('保存支付记录失败！');
            }

            if ($pay_log->getLevel() == LOG_RECHARGE) {
                $user = $pay_log->getOwner();
                if ($user) {
                    $res = CommissionBalance::recharge($user, $pay_log);
                    if (is_error($res) && $res['errno'] < 0) {
                        throw new RuntimeException($res['message']);
                    }

                    return $pay->getResponse();
                }
                throw new RuntimeException('处理充值失败！');

            } elseif ($pay_log->getLevel() == LOG_CHARGING_PAY) {

                $res = Charging::startFromPayLog($pay_log);
                if (is_error($res)) {
                    throw new RuntimeException($res['message']);
                }

                return $pay->getResponse(false);

            } elseif ($pay_log->getLevel() == LOG_FUELING_PAY) {

                $res = Fueling::startFromPayLog($pay_log);
                if (is_error($res)) {
                    throw new RuntimeException($res['message']);
                }

                return $pay->getResponse(false);
            }

            $device = Device::get($data['deviceUID'], true);
            if (empty($device)) {
                throw new RuntimeException('找不到这个设备:'.$data['deviceUID']);
            }

            //创建一个回调执行创建订单，出货任务
            $res = Job::createOrder($data['orderNO'], $device);
            if (empty($res) || is_error($res)) {
                throw new RuntimeException('创建订单任务失败！');
            }

            return $pay->getResponse();

        } catch (RuntimeException $e) {
            Log::error('pay', [
                'error' => $e->getMessage(),
                'config_id' => $config_id,
                'input' => $input,
            ]);

            return $pay instanceof IPay ? $pay->getResponse(false) : $e->getMessage();
        }
    }

    /**
     * 关闭订单
     */
    public static function close(string $order_no): array
    {
        $pay_log = self::getPayLog($order_no);
        if (empty($pay_log)) {
            return err('找不到支付记录！');
        }

        try {
            return (self::rebuildPay($pay_log))->close($order_no);
        } catch (RuntimeException $e) {
            return err($e->getMessage());
        }
    }

    /**
     * 根据订单号请求退款
     */
    public static function refund(string $order_no, int &$total = 0, array $data = [])
    {
        $pay_log = self::getPayLog($order_no);
        if (empty($pay_log)) {
            return err('找不到支付记录！');
        }

        return self::refundByLog($pay_log, $total, $data);
    }

    /**
     * 根据支付记录请求退款
     */
    public static function refundByLog(pay_logsModelObj $pay_log, int &$total = 0, array $data = [])
    {
        $price_total = $pay_log->getPrice();
        if ($total < 1 || $total > $price_total) {
            $total = $price_total;
        }

        try {
            $result = (self::rebuildPay($pay_log))->refund($pay_log->getOrderNO(), $total);
            if (is_error($result)) {
                $pay_log->setData('refund_fail', ['result' => $result]);
                $pay_log->save();

                return $result;
            }

            $data['result'] = $result;
            if (empty($data['createtime'])) {
                $data['createtime'] = time();
            }

            $pay_log->setData('refund', $data);
            $pay_log->save();

            return $result;

        } catch (RuntimeException $e) {
            return err($e->getMessage());
        }
    }

    /**
     * 根据支付记录请求支付结果
     */
    public static function queryFor(pay_logsModelObj $pay_log): array
    {
        $order_no = $pay_log->getOrderNO();

        if (empty($order_no)) {
            return err('订单号不正确！');
        }

        try {
            return (self::rebuildPay($pay_log))->query($order_no);
        } catch (RuntimeException $e) {
            return err($e->getMessage());
        }
    }

    /**
     * 根据订单号查询支付结果
     */
    public static function query(string $order_no): array
    {
        $pay_log = self::getPayLog($order_no);
        if (empty($pay_log)) {
            return err('找不到支付记录！');
        }

        return self::queryFor($pay_log);
    }

    /**
     * 为用户创建一条支付记录
     */
    public static function createPayLog(userModelObj $user, string $order_no, array $data = []): ?pay_logsModelObj
    {
        $level = intval($data['level'] ?? LOG_GOODS_PAY);
        if ($user->payLog($order_no, $level, $data)) {
            return self::getPayLog($order_no, $level);
        }

        return null;
    }

    public static function getPayLogById(int $id): ?pay_logsModelObj
    {
        return PayLogs::get($id);
    }

    /**
     * 获取支付记录
     * @param string $order_no 订单编号
     * @param int $level 支付类型
     */
    public static function getPayLog(string $order_no, int $level = 0): ?pay_logsModelObj
    {
        if (empty($level)) {
            $level = [
                LOG_PAY,
                LOG_GOODS_PAY,
                LOG_CHARGING_PAY,
                LOG_FUELING_PAY,
                LOG_RECHARGE,
            ];
        }

        return PayLogs::findOne(['level' => $level, 'title' => $order_no]);
    }

    public static function makeWithDevice(payment_configModelObj $config, deviceModelObj $device = null): ?IPay
    {
        if (App::isDevicePayConfigEnabled() && $config->getname() == self::WX_V3 && $device) {
            $sub_mch_id = $device->settings('extra.wx_v3.sub_mch_id', '');
            if ($sub_mch_id) {
                $config->setExtraData('sub_mcb_id', $sub_mch_id);
            }
        }

        return self::make($config);
    }

    /**
     * 根据支付配置创建支付对象
     * @param payment_configModelObj $config
     * @return null|IPay
     */
    public static function make(payment_configModelObj $config): ?IPay
    {
        try {
            switch ($config->getName()) {
                case self::LCSW:
                    return new LCSWPay($config->toArray());
                case self::SQB:
                    return new SQBPay($config->toArray());
                case self::WX:
                    return new WXPay($config->toArray());
                case self::WX_V3:
                    if ($config->getAgentId() == 0) {
                        $data = $config->toArray();
                    } else {
                        /** @var payment_configModelObj $default */
                        $default = PaymentConfig::getDefaultByName(Pay::WX_V3);
                        if (!$default) {
                            throw new RuntimeException('不正确的支付配置！');
                        }

                        $data = $default->getExtraData();
                        $data['sub_mch_id'] = $config->getExtraData('sub_mch_id', '');
                    }

                    return empty($data['sub_mch_id']) ? new WxPayV3Merchant($data) : new WxPayV3Partner($data);
                default:
                    throw new RuntimeException('不正确的支付配置！');
            }
        } catch (RuntimeException $e) {
            Log::error('pay', [
                'config' => $config->toArray(),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 判断付款码是否为微信付款码
     * @param $code
     * @return bool
     */
    public static function isWxPayQRCode($code): bool
    {
        return in_array(substr($code, 0, 2), ['10', '11', '12', '13', '14', '15']);
    }

    /**
     * 获取一个微信转账对象
     * @return WxMCHPay|WxMCHPayV3
     */
    public static function getWxMCHPayClient()
    {
        /** @var payment_configModelObj $config */
        $config = PaymentConfig::getDefaultByName(Pay::WX_V3);
        if ($config) {
            return new WxMCHPayV3($config->toArray());
        }

        /** @var payment_configModelObj $config */
        $config = PaymentConfig::getDefaultByName(Pay::WX);
        if ($config) {
            return new WxMCHPay($config->toArray());
        }

        throw new RuntimeException('没有支付配置！');
    }

    /**
     * 查询微信转账结果
     * @param $transaction
     * @param $trade_no
     * @return array
     */
    public static function getMCHPayResult($transaction, $trade_no): array
    {
        return (self::getWxMCHPayClient())->transferInfo($transaction, $trade_no);
    }

    /**
     * 给用户转账
     *
     * @param userModelObj $user
     * @param $num
     * @param $trade_no
     * @param string $desc
     *
     * @return array
     */
    public static function MCHPay(userModelObj $user, $num, $trade_no, string $desc = ''): array
    {
        if ($trade_no && $num > 0) {
            $client = Pay::getWxMCHPayClient();

            $res = $client->transferTo($user->getOpenid(), $trade_no, $num, $desc);
            if (is_error($res)) {
                return $res;
            }

            if ($res) {
                if ($res['batch_id']) {
                    $info = $client->transferInfo($res['batch_id'], $trade_no);
                    if ($info && $info['detail_status'] == 'SUCCESS') {
                        return $info;
                    }

                    return $res;
                }
                if ($res['partner_trade_no'] == $trade_no && isset($res['payment_no'])) {
                    return $res;
                }
            }

            return err('打款失败！');
        }

        return err('参数不正确！');
    }
}
