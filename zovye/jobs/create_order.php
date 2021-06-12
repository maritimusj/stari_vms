<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\createOrder;

//创建订单
use Exception;
use zovye\model\accountModelObj;
use zovye\base\modelObj;
use zovye\CtrlServ;
use zovye\Device;
use zovye\model\deviceModelObj;
use zovye\EventBus;
use zovye\ExceptionNeedsRefund;
use zovye\model\goods_voucher_logsModelObj;
use zovye\Helper;
use zovye\request;
use zovye\Job;
use zovye\Order;
use zovye\Pay;
use zovye\State;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\ZovyeException;
use function zovye\error;
use function zovye\is_error;
use function zovye\settings;

$op = request::op('default');
$order_no = request::str('orderNO');

if ($op == 'create_order' && CtrlServ::checkJobSign(['orderNO' => $order_no])) {
    try {
        prepare($order_no);
    } catch (ExceptionNeedsRefund $e) {
        $device = $e->getDevice();
        $refund = Helper::NeedAutoRefund($device);
        if ($refund) {
            $res = Job::refund($order_no, $e->getMessage());
            if (empty($res) || is_error($res)) {
                $device->appShowMessage('退款失败，请联系客服，谢谢！');
                return Util::logToFile('order_create', [
                    'orderNO' => $order_no,
                    'msg' => '启动退款任务！',
                    'error' => $res,
                ]);
            } else {
                $device->appShowMessage('正在退款，请稍后再试，谢谢！');
            }
        }
        return Util::logToFile('order_create', [
            'orderNO' => $order_no,
            'refund' => $refund,
            'error' => $e->getMessage(),
        ]);

    } catch (ZovyeException $e) {
        $device = $e->getDevice();
        if ($device) {
            $device->appShowMessage($e->getMessage(), 'error');
        }
        Util::logToFile('order_create_multi', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    } catch (Exception $e) {
        return Util::logToFile('order_create', [
            'orderNO' => $order_no,
            'refund' => false,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * @param string $order_no
 * @throws Exception
 */
function prepare(string $order_no)
{
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        throw new Exception('找不到支付信息！');
    }

    $pay_result = $pay_log->getQueryResult();
    if (empty($pay_result)) {
        $query_result = $pay_log->getPayResult();
        if (empty($query_result)) {
            throw new Exception('订单未支付！');
        }
        $pay_result['from'] = 'cb';
    } else {
        $pay_result['from'] = 'query';
    }

    $device = Device::get($pay_log->getDeviceId());
    if (empty($device)) {
        ExceptionNeedsRefund::throw('找不到指定的设备！');
    }

    $user = User::get($pay_log->getUserOpenid(), true);
    if (empty($user)) {
        ExceptionNeedsRefund::throwWith($device, '找不到指定的用户！');
    }

    if(!$user->lock()) {
        throw new Exception('用户无法锁定！');
    }

    $eventData = [
        'device' => $device,
        'user' => $user,
    ];

    //锁定设备
    $retries = intval(settings('device.lockRetries', 0));
    $delay = intval(settings('device.lockRetryDelay', 1));

    if (!$device->lockAcquire($retries, $delay)) {
        ExceptionNeedsRefund::throwWith($device, '设备被占用！');
    }

    if (Order::exists($order_no)) {
        throw new Exception('订单已经存在！');
    }

    $goods = $device->getGoods($pay_log->getGoodsId());
    if (empty($goods)) {
        ExceptionNeedsRefund::throwWith($device, '找不到对应的商品！');
    }

    if ($goods['num'] < 1) {
        ExceptionNeedsRefund::throwWith($device, '对不起，商品库存为零！');
    }

    if ($goods['lottery']) {
        $mcb_channel = intval($goods['lottery']['size']);
    } else {
        $mcb_channel = Device::cargoLane2Channel($device, $goods['cargo_lane']);
    }

    if ($mcb_channel == Device::CHANNEL_INVALID) {
        ExceptionNeedsRefund::throwWith($device, '商品货道配置不正确！');
    }

    //事件：设备已锁定
    EventBus::on('device.locked', $eventData);

    $log_data = [
        'user' => $user->profile(),
        'goods' => $goods,
        'payload' => $device->getPayload(),
        'account' => isset($acc) ? ['name' => $acc->name(), 'title' => $acc->title()] : [],
        'voucher' => isset($voucher) ? ['id' => $voucher->getId()] : [],
    ];

    $params = [
        'src' => intval($pay_log->getData('src', Order::PAY)),
        'device' => $device,
        'user' => $user,
        'ip' => strval($pay_log->getData('orderData.ip')),
        'payResult' => [
            'result' => 'success',
            'from' => $pay_result['from'],
            'total' => $pay_result['total'],
            'transaction_id' => $pay_result['transaction_id'],
        ],
    ];

    $result = Util::transactionDo(function () use (&$params, $order_no, $goods, $mcb_channel, &$log_data) {

        list($result, $order) = createOrder($params, $order_no, $goods, $mcb_channel);

        if (is_error($result)) {
            $log_data['result'] = $result;
        } else {
            $log_data['result'] = $order->getExtraData('pull.result', []);
        }

        if ($order) {
            $params['order'] = $order;
        }

        //返回true，则失败也会创建订单
        return true;
    });

    //事件：出货成功
    EventBus::on('device.openSuccess', $params);

    $device->updateRemain();

    $device->goodsLog($pay_log->getData('level'), $log_data);

    if (is_error($log_data['result'])) {
        ExceptionNeedsRefund::throwWith($device, $result['message']);
    }
}

/**
 * @param array $params
 * @param string $order_no
 * @param array $goods
 * @param int $mcb_channel
 * @return array
 */
function createOrder(array $params, string $order_no, array $goods, int $mcb_channel): array
{
    /** @var deviceModelObj $device */
    $device = $params['device'];

    /** @var userModelObj $user */
    $user = $params['user'];

    /** @var accountModelObj $acc */
    $acc = $params['account'];

    /** @var goods_voucher_logsModelObj $voucher */
    $voucher = $params['voucher'];

    $order_data = [
        'src' => $params['src'],
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'num' => 1,
        'price' => $goods['price'],
        'balance' => 0,
        'account' => empty($acc) ? '' : $acc->name(),
        'ip' => $params['ip'],
        'extra' => [
            'goods' => $goods,
            'discount' => [
                'total' => User::getUserDiscount($user, $goods),
            ],
            'payResult' => $params['payResult'],
            'device' => [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ],
            'user' => $user->profile(),
        ],
    ];


    if ($voucher) {
        $order_data['src'] = Order::VOUCHER;
        $order_data['extra']['voucher'] = [
            'id' => $voucher->getId(),
        ];
    }

    $agent = $device->getAgent();
    if ($agent) {
        $order_data['extra']['agent'] = $agent->profile();
    }

    $order = Order::create($order_data);
    if (empty($order)) {
        return [error(State::FAIL, '领取失败，创建订单失败'), null];
    }

    unset($params['src']);
    unset($params['ip']);

    $params['order'] = $order;

    try {
        //事件：订单已经创建
        EventBus::on('device.orderCreated', $params);
    } catch (Exception $e) {
        return [error(State::ERROR, $e->getMessage()), null];
    }

    $user->remove('last');

    foreach ($params as $entry) {
        if ($entry instanceof modelObj && !$entry->save()) {
            return [error(State::FAIL, '无法保存数据，请重试'), null];
        }
    }

    $data = [
        'online' => false,
        'channel' => $mcb_channel,
        'timeout' => settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT),
        'userid' => $user->getOpenid(),
        'num' => $order->getNum(),
        'from' => empty($acc) ? '' : $acc->name(),
        'user-agent' => $order->getExtraData('from.user_agent'),
        'ip' => $order->getExtraData('from.ip'),
    ];

    $loc = $device->settings('extra.location', []);
    if ($loc && $loc['lng'] && $loc['lat']) {
        $data['location']['device'] = [
            'lng' => $loc['lng'],
            'lat' => $loc['lat'],
        ];
    }

    $res = $device->pull($data);

    if (is_error($res)) {
        $order->setResultCode($res['errno']);
    } else {
        $order->setResultCode(0);
    }

    //记录出货结果
    $order->setExtraData('pull.result', $res);

    if ($device->isBlueToothDevice()) {
        $order->setBluetoothDeviceBUID($device->getBUID());
    }

    if (!$order->save()) {
        return [error(State::FAIL, '无法保存订单数据！'), null];
    }

    if (is_error($res)) {
        $device->setError($res['errno'], $res['message']);
        $device->scheduleErrorNotifyJob($res['errno'], $res['message']);

        try {
            //事件：出货失败
            EventBus::on('device.openFail', $params);
        } catch (Exception $e) {
        }
    } else {
        if (isset($goods['cargo_lane'])) {
            $device->resetPayload([$goods['cargo_lane'] => -1], "订单：{$order_no}");
        }

        //使用取货码
        if ($voucher) {
            $voucher->setUsedUserId($user->getId());
            $voucher->setUsedtime(time());
            if (!$voucher->save()) {
                return [error(State::ERROR, '使用取货码失败！'), null];
            }
        }
    }

    $device->save();

    /**
     * 始终返回 true，是为了即使失败，仍然创建订单
     */
    return [$res, $order];
}


