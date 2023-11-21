<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\business\Charging;
use zovye\business\Fueling;
use zovye\contract\IPay;
use zovye\domain\Agent;
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

    public static function rebuildPay(pay_logsModelObj $log): ?IPay
    {
        $config_id = $log->getPayConfigId();
        if (empty($config_id)) {
            return null;
        }

        $config = PaymentConfig::get($config_id);
        if (!$config) {
            return null;
        }

        return self::from($config);
    }

    public static function selectPay(deviceModelObj $device, userModelObj $user, string $scene): ?IPay
    {
        if ($user->isWxUser() || $user->isWXAppUser()) {
            $names = [self::LCSW, self::SQB, self::WX_V3, self::WX];
        } elseif ($user->isAliUser()) {
            $names = [self::LCSW, self::SQB];
        } else {
            return null;
        }

        $agent = $device->getAgent();
        if ($agent) {
            foreach ($names as $name) {
                /** @var payment_configModelObj $config */
                $config = PaymentConfig::getFor($agent, $name);
                if (!$config) {
                    continue;
                }
                if ($user->isWxUser() && $config->isEnabled("wx.$scene")) {
                    return self::from($config);
                }
                if ($user->isAliUser() && $config->isEnabled("ali.$scene")) {
                    return self::from($config);
                }
            }
        }

        foreach ($names as $name) {
            /** @var payment_configModelObj $config */
            $config = PaymentConfig::getByName($name);
            if (!$config) {
                continue;
            }
            if ($user->isWxUser() && $config->isEnabled("wx.$scene")) {
                return self::from($config);
            }
            if ($user->isAliUser() && $config->isEnabled("ali.$scene")) {
                return self::from($config);
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
        $pay = self::selectPay($device, $user, 'h5');
        if (!$pay) {
            return err('暂时无法支付！');
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
        string $scene,
        array $goods,
        array $pay_data = []
    ): array {
        $pay = self::selectPay($device, $user, $scene);
        if (!$pay) {
            return err('暂时无法支付！');
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

        $more = [
            'device' => $device->getId(),
            'user' => $user->getOpenid(),
            'pay' => [
                'config_id' => $pay->getConfig()['config_id'],
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
        $scene,
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        $result = self::prepareData($device, $user, $scene, $goods, $pay_data);
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

            Log::debug('pay', [
                'prepareData' => $result,
                'pay' => $pay,
                'fn' => $fn,
                'goods' => $goods,
                'data' => $pay_data,
                'user' => $user->profile(),
                'res' => $res,
            ]);

        } else {
            Log::debug('pay', [
                'result' => $result,
                'pay' => $pay,
                'fn' => $fn,
                'goods' => $goods,
                'data' => $pay_data,
                'user' => $user->profile(),
            ]);
            $res = err('unknown pay function:'.$fn);
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
        return self::createPay('createXAppPay', 'miniapp', $device, $user, $goods, $pay_data);
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
        return self::createPay('createJsPay', 'h5', $device, $user, $goods, $pay_data);
    }

    public static function createQrcodePay(
        deviceModelObj $device,
        string $code,
        array $goods,
        array $pay_data = []
    ): array {
        return self::createPay('createQrcodePay', 'qrcode',  $device, User::getPseudoUser($code, '<匿名用户>'), $goods, $pay_data);
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
                throw new Exception('不正确的支付配置id！');
            }

            $pay = self::from($config);
            if (!$pay) {
                throw new Exception('支付配置出错！');
            }

            $data = $pay->decodeData($input);
            if (empty($data)) {
                throw new Exception('回调数据为空！');
            }

            if (is_error($data)) {
                throw new Exception($data['message']);
            }

            if (!$pay->checkResult($data['raw'])) {
                throw new Exception('回调数据异常！');
            }

            if (!Locker::try("pay:{$data['orderNO']}", REQUEST_ID, 3)) {
                throw new Exception('无法锁定支付记录！');
            }

            $pay_log = self::getPayLog($data['orderNO']);
            if (empty($pay_log)) {
                throw new Exception('找不到支付记录！');
            }

            $pay_log->setData('payResult', $data);
            $pay_log->setData('create_order.createtime', time());

            if (!$pay_log->save()) {
                throw new Exception('保存支付记录失败！');
            }

            if ($pay_log->getLevel() == LOG_RECHARGE) {
                $user = $pay_log->getOwner();
                if ($user) {
                    $res = CommissionBalance::recharge($user, $pay_log);
                    if (is_error($res) && $res['errno'] < 0) {
                        throw new Exception($res['message']);
                    }

                    return $pay->getResponse();
                }
                throw new Exception('处理充值失败！');

            } elseif ($pay_log->getLevel() == LOG_CHARGING_PAY) {

                $res = Charging::startFromPayLog($pay_log);
                if (is_error($res)) {
                    throw new Exception($res['message']);
                }

                return $pay->getResponse(false);

            } elseif ($pay_log->getLevel() == LOG_FUELING_PAY) {

                $res = Fueling::startFromPayLog($pay_log);
                if (is_error($res)) {
                    throw new Exception($res['message']);
                }

                return $pay->getResponse(false);
            }

            $device = Device::get($data['deviceUID'], true);
            if (empty($device)) {
                throw new Exception('找不到这个设备:'.$data['deviceUID']);
            }

            //创建一个回调执行创建订单，出货任务
            $res = Job::createOrder($data['orderNO'], $device);
            if (empty($res) || is_error($res)) {
                throw new Exception('创建订单任务失败！');
            }

            return $pay->getResponse();

        } catch (Exception $e) {
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

        $pay = self::rebuildPay($pay_log);
        if (!$pay) {
            return err('支付配置不正确！');
        }

        return $pay->close($order_no);
    }

    /**
     * 请求退款
     */
    public static function refund(string $order_no, int &$total = 0, array $data = [])
    {
        $pay_log = self::getPayLog($order_no);
        if (empty($pay_log)) {
            return err('找不到支付记录！');
        }

        return self::refundByLog($pay_log, $total, $data);
    }

    public static function refundByLog(pay_logsModelObj $pay_log, int &$total = 0, array $data = [])
    {
        $pay = self::rebuildPay($pay_log);
        if (is_error($pay)) {
            return $pay;
        }

        $price_total = $pay_log->getPrice();
        if ($total < 1 || $total > $price_total) {
            $total = $price_total;
        }

        $res = $pay->refund($pay_log->getOrderNO(), $total);
        if (is_error($res)) {
            $pay_log->setData('refund_fail', ['result' => $res]);
            $pay_log->save();

            return $res;
        }

        $data['result'] = $res;
        if (empty($data['createtime'])) {
            $data['createtime'] = time();
        }

        $pay_log->setData('refund', $data);
        $pay_log->save();

        return $res;
    }

    public static function queryFor(pay_logsModelObj $pay_log): array
    {
        $pay = self::rebuildPay($pay_log);
        if (!$pay) {
            return err('支付配置不正确！');
        }

        $order_no = $pay_log->getOrderNO();

        if (empty($order_no)) {
            return err('订单号不正确！');
        }

        return $pay->query($order_no);
    }

    /**
     * 查询指定支付信息
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

    public static function selectPayConfiguration(array $config, string $name = ''): array
    {
        if ($name) {
            $data = $config[$name];
            if ($data) {
                $data['name'] = $name;
                unset($data['wx'], $data['ali'], $data['wxapp']);

                return $data;
            }

            return [];
        }

        $fn = function ($name) use ($config) {
            $data = $config[$name] ?? [];
            if ($data['enable']) {
                if ((Session::isWxAppUser() && (!isset($data['wxapp']) || $data['wxapp'])) ||
                    (Session::isWxUser() && !Session::isWxAppUser() && (!isset($data['wx']) || $data['wx'])) ||
                    (Session::isAliUser() && (!isset($data['ali']) || $data['ali']))) {

                    $data['name'] = $name;
                    unset($data['wx'], $data['ali'], $data['wxapp']);

                    return $data;
                }
            }

            return [];
        };

        $lcsw = $fn(Pay::LCSW);
        if ($lcsw) {
            return $lcsw;
        }

        $SQB = $fn(Pay::SQB);
        if ($SQB) {
            return $SQB;
        }

        $wx = $config[Pay::WX] ?? [];
        if ($wx['enable']) {
            $wx['name'] = Pay::WX;

            return $wx;
        }

        $ali = $config[Pay::ALI] ?? [];
        if ($ali['enable']) {
            $ali['name'] = Pay::ALI;

            return $ali;
        }

        return [];
    }

    /******************************************************************************************************************/
    /*  以下为内部函数
     * ****************************************************************************************************************/

    public static function getDefaultConfiguration(string $name = ''): array
    {
        $params = settings('pay', []);

        return self::selectPayConfiguration($params, $name);
    }

    /**
     * 获取设备关联的支付配置
     * @param deviceModelObj|null $device
     */
    public static function getPayConfiguration(deviceModelObj $device = null, string $name = ''): array
    {
        $res = [];
        if ($device) {
            $agent = $device->getAgent();
            if ($agent) {
                $res = Agent::getPayConfiguration($agent, $name);
                if ($res && $res['name'] == Pay::WX) {
                    $default = self::getDefaultConfiguration(self::WX);
                    if (empty($default['v3'])) {
                        return err('需要配置微信v3支付参数！');
                    }
                    $config = $default['v3'];
                    $config['appid'] = $default['appid'];
                    $config['wxappid'] = $default['wxappid'];
                    $config['mch_id'] = $default['mch_id'];
                    $config['sub_mch_id'] = $res['mch_id'];

                    return $config;
                }
            }
        }

        if (empty($res) || empty($res['enable']) || empty(array_diff_key($res, ['enable' => 1, 'name' => 1]))) {
            $res = self::getDefaultConfiguration($name);
        }

        if ($res['pem'] && !empty($res['pem']['cert']) && !empty($res['pem']['key'])) {
            $file = self::getPEMFile($res['pem']);
            if (!is_error($file)) {
                $res['pem']['cert'] = $file['cert_filename'];
                $res['pem']['key'] = $file['key_filename'];
            }
        }

        return $res;
    }


    /**
     * 保存证书到文件并返回路径
     */
    public static function getPEMFile(array $pem, bool $force = false): array
    {
        if ($pem['cert'] && $pem['key']) {

            $str = App::uid(8);
            $dir = PEM_DIR.$str.DIRECTORY_SEPARATOR;

            $pem_file = [
                'cert_filename' => $dir.sha1($pem['cert']).'.pem',
                'key_filename' => $dir.sha1($pem['key']).'.pem',
            ];

            if (!$force && file_exists($pem_file['cert_filename']) && file_exists($pem_file['key_filename'])) {
                return $pem_file;
            }

            We7::make_dirs($dir);

            if (
                file_put_contents($pem_file['cert_filename'], $pem['cert']) !== false &&
                file_put_contents($pem_file['key_filename'], $pem['key']) !== false
            ) {
                return $pem_file;
            } else {
                Log::error("getPEMFile", [
                    'pem' => $pem_file,
                    'error' => '写入PEM文件出错！',
                ]);
            }
        }

        return [];
    }

    public static function from(payment_configModelObj $config): ?IPay
    {
        if ($config->getName() == self::LCSW) {
            return new LCSWPay($config->toArray());
        }

        if ($config->getName() == self::SQB) {
            return new SQBPay($config->toArray());
        }

        if ($config->getName() == self::WX) {
            return new WXPay($config->toArray());
        }

        if ($config->getName() == self::WX_V3) {
            return new WXPay($config->toArray());
        }

        return null;
    }

    public static function isWxPayQrcode($code): bool
    {
        $str = substr($code, 0, 2);

        return in_array($str, ['10', '11', '12', '13', '14', '15']);
    }

    public static function getWxPayClientFor(userModelObj $user)
    {
        $params = Pay::getDefaultConfiguration(Pay::WX);
        if (empty($params)) {
            throw new RuntimeException('没有配置微信打款信息！');
        }

        if (!isEmptyArray($params['v3'])) {
            $config = $params['v3'];
            $config['mch_id'] = $params['mch_id'];

            if ($user->isWxUser()) {
                $config['appid'] = $params['appid'];
            } elseif ($user->isWXAppUser()) {
                $config['appid'] = $params['wxappid'];
            } else {
                throw new RuntimeException('只能给微信或微信小程序用户转账！');
            }

            return new WxMCHPayV3($config);
        }

        $file = Pay::getPEMFile($params['pem']);
        if (is_error($file)) {
            throw new RuntimeException($file['message']);
        }

        $params['pem']['cert'] = $file['cert_filename'];
        $params['pem']['key'] = $file['key_filename'];

        return new WxMCHPay($params);
    }

    public static function getMCHPayResult(userModelObj $user, $transaction, $trade_no): array
    {
        return (self::getWxPayClientFor($user))->transferInfo($transaction, $trade_no);
    }

    /**
     * 给用户打款.
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
            $client = Pay::getWxPayClientFor($user);

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
