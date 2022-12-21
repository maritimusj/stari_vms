<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\Contract\IPay;
use zovye\model\pay_logsModelObj;
use zovye\payment\SQBPay;
use zovye\payment\WXPay;
use zovye\payment\LCSWPay;
use zovye\model\userModelObj;
use zovye\model\deviceModelObj;

class Pay
{
    //微信公众号
    const WX = 'wx';

    //微信小程序
    const WxAPP = 'wxapp';

    //支付宝
    const ALI = 'ali';

    //扫呗
    const LCSW = 'lcsw';

    //收钱吧
    const SQB = 'SQB';

    static $names = [
        self::WX => '微信支付',
        self::WxAPP => '微信小程序支付',
        self::ALI => '支付宝',
        self::LCSW => '扫呗',
        self::SQB => '收钱吧',
    ];

    public static function getTitle($name): string
    {
        return self::$names[$name] ?? '未知';
    }

    /**
     * 获取支付需要的Js，函数会根据指定的设备和用户，获取特定的支付配置
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return mixed
     */
    public static function getPayJs(deviceModelObj $device, userModelObj $user)
    {
        $res = self::getActivePayObj($device);
        if (is_error($res)) {
            return $res;
        }

        return $res->getPayJs($device, $user);
    }

    /**
     * $pay_data['total']指定商品数量，未指定则默认为1
     * $pay_data['price']指定总价格，未指定则使用单个商品价格
     *
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param array $goods
     * @param array $pay_data
     * @return array
     */
    private static function prepareData(
        deviceModelObj $device,
        userModelObj $user,
        array $goods,
        array $pay_data = []
    ): array {
        $pay = self::getActivePayObj($device);
        if (is_error($pay)) {
            return $pay;
        }

        list($order_no,) = self::prepareDataWithPay($pay->getName(), $device, $user, $goods, $pay_data);
        if (is_error($order_no)) {
            return $order_no;
        }

        return [$pay, $order_no];
    }

    public static function prepareDataWithPay(
        string $pay_name,
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
                'name' => $pay_name,
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
            return [error(State::ERROR, '无法保存支付信息！'), null];
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
            $res = error(State::ERROR, 'unknown pay function:'.$fn);
        }

        if (is_error($res)) {
            return ['', $res];
        }

        return [$order_no, $res];
    }


    /**
     * 创建支付订单
     * @param deviceModelObj $device 设备
     * @param userModelObj $user 用户
     * @param array $goods 商品信息
     * @param array $pay_data
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
     * @param array $pay_data
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

    /**
     * 处理支付的通知数据
     * @param string $name 支付类型名称
     * @param string $input
     * @return array|string
     */
    public static function notify(string $name, string $input)
    {
        $pay = null;
        try {
            //获取一个临时的pay对象
            $pay = self::makePayObj($name);
            if (is_error($pay)) {
                throw new Exception($pay['message']);
            }

            $data = $pay->decodeData($input);
            if (empty($data)) {
                throw new Exception('回调数据为空！');
            }

            if (is_error($data)) {
                throw new Exception($data['message']);
            }

            //获取一个配置完整的pay对象
            $pay = self::getActivePayObj($data['deviceUID'], $name);
            if (is_error($pay)) {
                throw new Exception($pay['message']);
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
                    $res = $user->recharge($pay_log);
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
                'name' => $name,
                'input' => $input,
            ]);

            return $pay instanceof IPay ? $pay->getResponse(false) : $e->getMessage();
        }
    }

    /**
     * 请求退款
     * @param string $order_no
     * @param int $total
     * @param array $data
     * @return mixed
     */
    public static function refund(string $order_no, int $total = 0, array $data = [])
    {
        $pay_log = self::getPayLog($order_no);
        if (empty($pay_log)) {
            return error(State::ERROR, '找不到支付记录！');
        }

        $device_id = $pay_log->getDeviceId();

        if ($device_id == 0) {
            $device = Device::getDummyDevice();
        } else {
            $device = Device::get($device_id);
            if (empty($device)) {
                return error(State::ERROR, '找不到这个设备！');
            }
        }

        $pay = self::getActivePayObj($device, $pay_log->getPayName());
        if (is_error($pay)) {
            return $pay;
        }

        $price_total = $pay_log->getPrice();
        if ($total < 1 || $total > $price_total) {
            $total = $price_total;
        }

        $res = $pay->refund($order_no, $total);
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

    public static function queryFor(pay_logsModelObj $pay_log)
    {
        $device_id = $pay_log->getDeviceId();

        if ($device_id == 0) {
            $device = Device::getDummyDevice();
        } else {
            $device = Device::get($device_id);
            if (empty($device)) {
                return error(State::ERROR, '找不到这个设备！');
            }
        }

        $pay = self::getActivePayObj($device, $pay_log->getPayName());
        if (is_error($pay)) {
            return $pay;
        }

        $order_no = $pay_log->getOrderNO();
        return $pay->query($order_no);
    }

    /**
     * 查询指定支付信息
     * @param string $order_no
     * @return mixed
     */
    public static function query(string $order_no)
    {
        $pay_log = self::getPayLog($order_no);
        if (empty($pay_log)) {
            return error(State::ERROR, '找不到支付记录！');
        }
        return self::queryFor($pay_log);
    }

    /**
     * 为用户创建一条支付记录
     * @param userModelObj $user
     * @param string $order_no
     * @param array $data
     * @return mixed
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
        return m('pay_logs')->findOne(['id' => $id]);
    }

    /**
     * 获取支付记录
     * @param string $order_no 订单编号
     * @param int $level 支付类型
     * @return pay_logsModelObj|null
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

        $data = We7::uniacid(['title' => $order_no, 'level' => $level]);

        return m('pay_logs')->findOne($data);
    }

    public static function selectPayParams(array $params, string $name): array
    {
        if ($name) {
            $data = $params[$name];
            if ($data) {
                $data['name'] = $name;
                unset($data['wx'], $data['ali'], $data['wxapp']);

                return $data;
            }

            return [];
        }

        $fn = function ($name) use ($params) {
            $data = $params[$name] ?? [];
            if ($data['enable']) {
                if ((App::isWxAppUser() && (!isset($data['wxapp']) || $data['wxapp'])) ||
                    (App::isWxUser() && !App::isWxAppUser() && (!isset($data['wx']) || $data['wx'])) ||
                    (App::isAliUser() && (!isset($data['ali']) || $data['ali']))) {
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

        $wx = $params[Pay::WX] ?? [];
        if ($wx['enable']) {
            $wx['name'] = Pay::WX;

            return $wx;
        }

        $ali = $params[Pay::ALI] ?? [];
        if ($ali['enable']) {
            $ali['name'] = Pay::ALI;

            return $ali;
        }

        return [];
    }

    /******************************************************************************************************************/
    /*  以下为内部函数
     * ****************************************************************************************************************/

    /**
     * @param string $name
     * @return mixed
     */
    public static function getDefaultPayParams(string $name = ''): array
    {
        $params = settings('pay', []);

        return self::selectPayParams($params, $name);
    }

    /**
     * 获取设备关联的支付配置
     * @param mixed $device
     * @param string $name
     * @return array
     */
    public static function getPayParams($device = null, string $name = ''): array
    {
        if (!empty($device) && is_string($device)) {
            $device = Device::get($device, true);
            if (empty($device)) {
                return error(State::ERROR, '找不到这个设备！');
            }
        }

        $res = [];
        if ($device instanceof deviceModelObj) {
            $agent = $device->getAgent();
            if ($agent) {
                $res = Agent::getPayParams($agent, $name);
            }
        }

        if (empty($res) || empty($res['enable']) || empty(array_diff_key($res, ['enable' => 1, 'name' => 1]))) {
            $res = self::getDefaultPayParams($name);
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
     *
     * @param array $pem
     * @param bool $force
     *
     * @return array
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

            We7::mkDirs($dir);

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


    /**
     * 获取一个临时的支付对象
     * @param string $name
     * @return array|IPay
     */
    private static function makePayObj(string $name)
    {
        //lcsw(扫呗）第三方支付
        if ($name == self::LCSW) {
            return new LCSWPay();
        }

        //收钱吧
        if ($name == self::SQB) {
            return new SQBPay();
        }

        //微信公众号支付或小程序支付
        if ($name == self::WX || $name == self::WxAPP) {
            return new WXPay();
        }

        //支付宝支付
        if ($name == self::ALI) {
            return error(State::ERROR, '支付宝原生支付暂不可用！');
        }

        return error(State::ERROR, '不支持的支付类型！');
    }


    /**
     * 根据设备和名称获取已配置好的支付对象
     * @param string|deviceModelObj $device
     * @param string $name
     * @return array|IPay
     */
    public static function getActivePayObj($device, string $name = '')
    {
        $res = self::getPayParams($device, $name);
        if (is_error($res)) {
            return $res;
        }

        $pay = self::makePayObj(strval($res['name']));
        if (is_error($pay)) {
            return $pay;
        }

        $pay->setConfig($res);

        return $pay;
    }
}
