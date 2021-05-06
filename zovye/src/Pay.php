<?php
/**
 * www.zovye.com
 * Author: jjs
 * Date: 2019/12/10
 * Time: 19:42.
 */

namespace zovye;

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

    //省钱码
    const SQM = 'SQM';

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
    private static function prepareData(deviceModelObj $device, userModelObj $user, array $goods, array $pay_data = []): array
    {
        $pay = self::getActivePayObj($device);
        if (is_error($pay)) {
            return $pay;
        }

        list($order_no,) = self::prepareDataWithPay($pay->getName(), $device, $user, $goods, $pay_data);
        if (is_error($order_no)) {
            return ['', $order_no];
        }

        return [$pay, $order_no];
    }

    public static function prepareDataWithPay(string $pay_name, deviceModelObj $device, userModelObj $user, array $goods, array $pay_data = []): array
    {
        $partial = $pay_data['serial'] ? 'E' . strtoupper(substr(sha1($pay_data['serial']), 0, 16)) : str_replace('.', '', 'S' . microtime(true));
        $order_no = "U{$user->getId()}D{$device->getId()}" . $partial;

        $pay_data = array_merge_recursive($pay_data, [
            'device' => $device->getId(),
            'user' => $user->getOpenid(),
            'goods' => $goods['id'],
            'pay' => [
                'name' => $pay_name,
            ],
            'orderData' => [
                'orderNO' => $order_no,
                'num' => empty($pay_data['total']) ? 1 : $pay_data['total'],
                'price' => empty($pay_data['price']) ? $goods['price'] : $pay_data['price'],
                'ip' => CLIENT_IP,
                'extra' => [
                    'goods' => $goods,
                ],
                'createtime' => time(),
            ]
        ]);

        $pay_log = self::createPayLog($user, $order_no, $pay_data);
        if (empty($pay_log)) {
            return [error(State::ERROR, '无法保存支付信息！'), null];
        }

        return [$order_no, $pay_log];
    }

    private static function createPay($fn, deviceModelObj $device, userModelObj $user, array $goods, array $pay_data = []): array
    {
        $result = self::prepareData($device, $user, $goods, $pay_data);
        if (is_error($result)) {
            return ['', $result];
        }

        /** @var IPay $pay */
        list($pay, $order_no) = $result;

        $title = "{$goods['name']}x{$pay_data['total']}{$goods['unit_title']}";
        $price = empty($pay_data['price']) ? $goods['price'] : $pay_data['price'];

        if (is_callable([$pay, $fn])) {
            $res = $pay->$fn($user->getOpenid(),
                $device->getImei(),
                $order_no,
                $price,
                $title
            );
        } else {
            $res = error(State::ERROR, 'unknown pay function');
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
    public static function createXAppPay(deviceModelObj $device, userModelObj $user, array $goods, array $pay_data = []): array
    {
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
    public static function createJsPay(deviceModelObj $device, userModelObj $user, array $goods, array $pay_data = []): array
    {
        return self::createPay('createJsPay', $device, $user, $goods, $pay_data);
    }

    public static function notifyAliXApp(string $input)
    {
        $input_format = json_decode($input, true);
        if (empty($input_format) || $input_format['result_code'] !== '01') {
            return error(State::FAIL, empty($input_format['return_msg']) ? '无法解析input数据' : $input_format['return_msg']);
        }
        $data = [
            'deviceUID' => $input_format['attach'],
            'orderNO' => $input_format['terminal_trace'],
            'total' => intval($input_format['total_fee']),
            'transaction_id' => $input_format['out_trade_no'],
            'raw' => $input_format,
        ];

        if (empty($data)) {
            return error(State::ERROR, '回调数据为空！');
        }

        $device = Device::get($data['deviceUID'], true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $pay_log = self::getPayLog($data['orderNO']);
        if (empty($pay_log)) {
            return error(State::ERROR, '找不对支付记录！');
        }

        $pay_log->setData('payResult', $data);
        $pay_log->setData('create_order.createtime', time());

        if (!$pay_log->save()) {
            return error(State::ERROR, '保存支付记录失败！');
        }

        //创建一个回调执行创建订单，出货任务
        $res = Job::createOrder($data['orderNO'], $device);
        if (empty($res) || is_error($res)) {
            return error(State::ERROR, '创建订单任务失败！');
        }

        return '{"return_code":"01","return_msg":"SUCCESS"}';
    }

    public static function notifyChannel(string $name, string $input)
    {
        $data = json_decode($input, true);
        // if (!ChannelPay::checkSign($data)) {
        //     return err('签名检验失败！');
        // }

        $out_trade_no = $data['outTradeNo'];

        $pay_log = self::getPayLog($out_trade_no);
        if (empty($pay_log)) {
            return err('找不对支付记录！');
        }

        $pay_log->setData('payResult', $data);
        $pay_log->setData('create_order.createtime', time());

        if (!$pay_log->save()) {
            return err('保存支付记录失败！');
        }

        $device = Device::get($pay_log->getDeviceId());
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        //创建一个回调执行创建订单，出货任务
        $res = Job::createOrder($out_trade_no, $device);
        if (empty($res) || is_error($res)) {
            return err('创建订单任务失败！');
        }

        return '{"code":200}';
    }

    /**
     * 处理支付的通知数据
     * @param string $name 支付类型名称
     * @param string $input
     * @return array|string
     */
    public static function notify(string $name, string $input)
    {
        //获取一个临时的pay对象
        $pay = self::makePayObj($name);
        if (is_error($pay)) {
            return $pay;
        }

        $data = $pay->decodeData($input);
        if (is_error($data)) {
            return $data;
        }

        if (empty($data)) {
            return error(State::ERROR, '回调数据为空！');
        }

        $device = Device::get($data['deviceUID'], true);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        //获取一个配置完整的pay对象
        $pay = self::getActivePayObj($device, $name);
        if (is_error($pay)) {
            return $pay;
        }

        if (!$pay->checkResult($data['raw'])) {
            return error(State::ERROR, '回调数据异常！');
        }

        $pay_log = self::getPayLog($data['orderNO']);
        if (empty($pay_log)) {
            return error(State::ERROR, '找不对支付记录！');
        }

        $pay_log->setData('payResult', $data);
        $pay_log->setData('create_order.createtime', time());

        if (!$pay_log->save()) {
            return error(State::ERROR, '保存支付记录失败！');
        }

        //创建一个回调执行创建订单，出货任务
        $res = Job::createOrder($data['orderNO'], $device);
        if (empty($res) || is_error($res)) {
            return error(State::ERROR, '创建订单任务失败！');
        }

        return $pay->getResponse(true);
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
        if (empty($device_id)) {
            return error(State::ERROR, '无效的设备ID！');
        }

        $device = Device::get($device_id);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $pay = self::getActivePayObj($device, $pay_log->getPayName());
        if (is_error($pay)) {
            return $pay;
        }

        $price = $pay_log->getPrice();
        if ($total < 1 || $total > $price) {
            $total = $price;
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

        $device_id = $pay_log->getDeviceId();
        if (empty($device_id)) {
            return error(State::ERROR, '无效的设备ID！');
        }

        $device = Device::get($device_id);
        if (empty($device)) {
            return error(State::ERROR, '找不到这个设备！');
        }

        $pay = self::getActivePayObj($device, $pay_log->getPayName());
        if (is_error($pay)) {
            return $pay;
        }

        return $pay->query($order_no);
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
        if ($user->payLog($order_no, $data)) {
            return self::getPayLog($order_no);
        }
        return null;
    }

    /**
     * 获取支付记录
     * @param string $order_no 订单编号
     * @param int $level 支付类型
     * @return pay_logsModelObj|null
     */
    public static function getPayLog(string $order_no, int $level = LOG_PAY): ?pay_logsModelObj
    {
        $data = We7::uniacid(['title' => $order_no, 'level' => $level]);
        return m('pay_logs')->findOne($data);
    }

    public static function selectPayParams(array $params, string $name): array
    {
        $data = [];
        if ($name) {
            $data = $params[$name];
            if ($data) {
                $data['name'] = $name;
            }
        } else {
            if ($params['lcsw']['enable']) {
                $data = $params['lcsw'];
                $data['name'] = 'lcsw';
            } elseif ($params['SQB']['enable']) {
                $data = $params['SQB'];
                $data['name'] = 'SQB';
            }  elseif ($params['wx']['enable']) {
                $data = $params['wx'];
                $data['name'] = 'wx';
            } elseif ($params['ali']['enable']) {
                $data = $params['ali'];
                $data['name'] = 'ali';
            }
        }
        return is_array($data) ? $data : [];
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
     * @param deviceModelObj|null $device
     * @param string $name
     * @return array
     */
    public static function getPayParams(deviceModelObj $device = null, string $name = ''): array
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

        if (empty($res['enable']) || empty(array_diff_key((array)$res, ['enable' => 1, 'name' => 1]))) {
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
            $dir = PEM_DIR . $str . DIRECTORY_SEPARATOR;

            $pem_file = [
                'cert_filename' => $dir . sha1($pem['cert']) . '.pem',
                'key_filename' => $dir . sha1($pem['key']) . '.pem',
            ];

            if (!$force && file_exists($pem_file['cert_filename']) && file_exists($pem_file['key_filename'])) {
                return $pem_file;
            }

            We7::mkDirs($dir);

            if (file_put_contents($pem_file['cert_filename'], $pem['cert']) !== false &&
                file_put_contents($pem_file['key_filename'], $pem['key']) !== false) {
                return $pem_file;
            } else {
                Util::logToFile("getPEMFile", [
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
            return error(State::ERROR, '不支付支付原生支付！');
        }

        if ($name == 'SQB') {
            return new SQBPay();
        }

        return error(State::ERROR, '不支持的支付类型！');
    }


    /**
     * 根据设备和名称获取已配置好的支付对象
     * @param deviceModelObj $device
     * @param string $name
     * @return array|IPay
     */
    private static function getActivePayObj(deviceModelObj $device, string $name = '')
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
