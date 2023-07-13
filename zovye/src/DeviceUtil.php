<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use Exception;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goods_voucher_logsModelObj;
use zovye\model\keeperModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;

class DeviceUtil
{
    public static function isAssigned($data, deviceModelObj $device): bool
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        if ($data['all']) {
            return true;
        }

        if ($data['agents']) {
            $agent = $device->getAgent();
            if ($agent && in_array($agent->getId(), $data['agents'])) {
                return true;
            }
        }

        if ($data['groups']) {
            $group_id = $device->getGroupId();
            if ($group_id && in_array($group_id, $data['groups'])) {
                return true;
            }
        }

        if ($data['tags']) {
            $tags = $device->getTagsAsId();
            if ($tags && array_intersect($data['tags'], $tags)) {
                return true;
            }
        }

        if ($data['devices']) {
            if (in_array($device->getId(), $data['devices'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param userModelObj|keeperModelObj|null $user
     * @param int|string|deviceModelObj|null $device
     * @param int $lane
     * @param array $params
     *
     * @return array
     */
    public static function test($device, userModelObj $user = null, int $lane = Device::DEFAULT_CARGO_LANE, array $params = []): array
    {
        if (is_string($device)) {
            $device = Device::get($device, true);
        } elseif (is_int($device)) {
            $device = Device::get($device);
        }

        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if ($device->isFuelingDevice() && App::isFuelingDeviceEnabled()) {
            if (!$device->isMcbOnline()) {
                return err('设备不在线！');
            }
            if (Fueling::test($device, 100)) {
                return ['message' => '已发送测试请求！'];
            }

            return err('请求失败！');
        }

        $data = array_merge(
            [
                'online' => true,
                'userid' => isset($user) ? $user->getName() : _W('username'),
                'num' => 1,
                'from' => 'web.admin',
                'test' => true,
                'timeout' => settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT),
            ],
            $params
        );

        if (!$device->lockAcquire()) {
            return err('设备锁定失败，请重试！');
        }

        $goods = Device::getGoodsByLane($device, $lane);

        $pull_data = Helper::preparePullData(null, $device, $user, $goods);
        if (is_error($pull_data)) {
            return $pull_data;
        }

        $log_data = [
            'goods' => $goods,
            'user' => isset($user) ? $user->profile() : _W('username'),
            'params' => $pull_data,
            'payload' => $device->getPayload(),
        ];

        $pull_result = $device->pull($pull_data);

        $log_data['result'] = $pull_result;

        //创建出货记录
        $device->goodsLog(LOG_GOODS_TEST, $log_data);

        if (is_error($pull_result)) {
            return $pull_result;
        }

        //如果是运营人员测试，则不减少库存
        if (empty($params['keeper'])) {
            $locker = $device->payloadLockAcquire(3);
            if (empty($locker)) {
                return err('设备正忙，请重试！');
            }
            $payload = $device->resetPayload([$lane => -1], "设备测试，用户：{$data['userid']}");
            if (is_error($payload)) {
                return err('保存库存失败！');
            }
            $locker->unlock();
            $device->updateAppRemain();
        }

        $device->cleanError();
        $device->save();

        $result = ['message' => '出货成功！'];

        if ($device->isBlueToothDevice()) {
            $result['data'] = $pull_result;
        }

        return $result;
    }

    /**
     * 用户通过指定公众号在指定设备上领取操作.
     * @param array $args
     * @return array
     * @throws Exception
     */
    public static function open(array $args = []): array
    {
        ignore_user_abort(true);
        set_time_limit(0);

        //获取设备参数
        $devices = array_values(
            array_filter($args, function ($entry) {
                return $entry instanceof deviceModelObj;
            })
        );

        if (empty($devices)) {
            return err('设备为空');
        }

        /** @var deviceModelObj $device */
        $device = $devices[0];

        //获取用户参数
        $users = array_values(
            array_filter($args, function ($entry) {
                return $entry instanceof userModelObj;
            })
        );

        if (empty($users)) {
            return err('用户为空');
        }

        /** @var userModelObj $user */
        $user = $users[0];

        //获取订单参数
        $orders = array_values(
            array_filter($args, function ($entry) {
                return $entry instanceof orderModelObj;
            })
        );

        /** @var orderModelObj $order */
        $order = empty($orders) ? null : $orders[0];

        //获取公众号参数
        $accounts = array_values(
            array_filter($args, function ($entry) {
                return $entry instanceof accountModelObj;
            })
        );

        $account = empty($accounts) ? null : $accounts[0];

        //获取优惠券参数
        $vouchers = array_values(
            array_filter($args, function ($entry) {
                return $entry instanceof goods_voucher_logsModelObj;
            })
        );

        //获取商品参数
        /** @var goods_voucher_logsModelObj $voucher */
        $voucher = empty($vouchers) ? null : $vouchers[0];

        $level = intval($args['level']);
        $goods_id = intval($args['goodsId']);

        $params = [
            'device' => $device,
            'user' => $user,
            'account' => $account,
            'voucher' => $voucher,
            'order' => $order,
        ];

        //事件：设备已锁定
        EventBus::on('device.beforeLock', $params);

        //锁定设备
        $retries = intval(settings('device.lockRetries', 0));
        $delay = intval(settings('device.lockRetryDelay', 1));

        if (!$device->lockAcquire($retries, $delay)) {
            return err('设备被占用，请重新扫描设备二维码');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods)) {
            return err('找不到对应的商品');
        }

        if ($goods['num'] < 1) {
            return err('对不起，已经被领完了');
        }

        //事件：设备已锁定
        EventBus::on('device.locked', $params);

        $log_data = [
            'user' => $user->profile(),
            'goods' => $goods,
            'payload' => $device->getPayload(),
            'account' => isset($account) ? [
                'name' => $account->name(),
                'title' => $account->title(),
            ] : [],
            'voucher' => isset($voucher) ? [
                'id' => $voucher->getId(),
            ] : [],
        ];

        if ($order) {
            $params['order'] = $order;
        }

        //开启事务
        $result = DBUtil::transactionDo(
            function () use (&$params, $goods, &$log_data, $args) {
                /** @var deviceModelObj $device */
                $device = $params['device'];

                /** @var userModelObj $user */
                $user = $params['user'];

                /** @var accountModelObj $acc */
                $acc = $params['account'];

                /** @var orderModelObj $order */
                $order = $params['order'];

                /** @var goods_voucher_logsModelObj $voucher */
                $voucher = $params['voucher'];

                $order_data = [
                    'openid' => $user->getOpenid(),
                    'agent_id' => $device->getAgentId(),
                    'device_id' => $device->getId(),
                    'src' => Order::ACCOUNT,
                    'name' => $goods['name'],
                    'goods_id' => $goods['id'],
                    'num' => 1,
                    'price' => 0,
                    'account' => $acc ? $acc->name() : '',
                    'ip' => empty($args['ip']) ? CLIENT_IP : $args['ip'],
                    'extra' => [
                        'goods' => $goods,
                        'device' => [
                            'imei' => $device->getImei(),
                            'name' => $device->getName(),
                        ],
                        'user' => $user->profile(),
                    ],
                ];

                if (App::isTKPromotingEnabled()) {
                    $order_data['extra']['tk'] = [
                        'order_no' => $params['tk_order_no'],
                    ];
                }

                if (App::isGDCVMachineEnabled()) {
                    $order_data['extra']['CV'] = [
                        'profile' => $user->getIDCardVerifiedData(),
                    ];
                }

                //定制功能：零佣金
                if (Helper::isZeroBonus($device, Order::FREE_STR)) {
                    $order_data['agent_id'] = 0;
                    $order_data['device_id'] = 0;
                    $order_data['extra']['custom'] = [
                        'zero_bonus' => true,
                        'device' => $device->getId(),
                        'agent' => $device->getAgentId(),
                    ];
                }

                if ($acc) {
                    $order_data['extra']['account'] = [
                        'name' => $acc->getName(),
                        'type' => $acc->getType(),
                        'clr' => $acc->getClr(),
                        'title' => $acc->getTitle(),
                        'img' => $acc->getImg(),
                    ];
                }

                if ($args['orderId']) {
                    $order_data['order_id'] = $args['orderId'];
                } else {
                    $order_data['order_id'] = Order::makeUID($user, $device);
                }

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

                if ($order) {
                    $order_data['extra'] = orderModelObj::serializeExtra($order_data['extra']);
                    foreach ($order_data as $name => $val) {
                        $setter = 'set'.ucfirst($name);
                        $order->{$setter}($val);
                    }
                    if (!$order->save()) {
                        return err('领取失败，保存订单失败');
                    }
                } else {
                    $order = Order::create($order_data);
                    if (empty($order)) {
                        return err('领取失败，创建订单失败');
                    }

                    $params['order'] = $order;

                    try {
                        //事件：订单已经创建
                        EventBus::on('device.orderCreated', $params);
                    } catch (Exception $e) {
                        return err($e->getMessage());
                    }
                }

                $user->remove('last');

                foreach ($params as $entry) {
                    if ($entry && !$entry->save()) {
                        return err('无法保存数据，请重试');
                    }
                }

                $pull_data = Helper::preparePullData($order, $device, $user, $goods);
                if (is_error($pull_data)) {
                    return $pull_data;
                }

                $res = $device->pull($pull_data);

                $log_data['params'] = $pull_data;
                $log_data['result'] = $res;
                $log_data['order'] = $order->getId();
                $log_data['result'] = $res;

                $order->setExtraData('device.ch', $pull_data['channel']);

                if (is_error($res)) {
                    $order->setResultCode($res['errno']);

                    try {
                        //事件：出货失败
                        EventBus::on('device.openFail', $params);
                    } catch (Exception $e) {
                        //return error($e->getCode(), $e->getMessage());
                    }
                    if (Helper::NeedAutoRefund($device)) {
                        //退款任务
                        Job::refund($order->getOrderNO(), $res['message']);
                    }
                } else {
                    $order->setResultCode(0);

                    if (isset($goods['cargo_lane'])) {
                        $locker = $device->payloadLockAcquire(3);
                        if (empty($locker)) {
                            return err('设备正忙，请重试！');
                        }
                        $v = $device->resetPayload([$goods['cargo_lane'] => -1], "设备出货：{$order->getOrderNO()}");
                        if (is_error($v)) {
                            return err('保存库存失败！');
                        }
                        $locker->unlock();
                    }

                    if ($voucher) {
                        $voucher->setUsedUserId($user->getId());
                        $voucher->setUsedtime(time());
                        if (!$voucher->save()) {
                            return err('出货失败：使用取货码失败！');
                        }
                    }
                }

                //出货失败后，只记录错误，不回退数据
                $order->setExtraData('pull.result', $res);

                if (!$order->save()) {
                    return err('无法保存订单数据！');
                }

                $device->save();

                /**
                 * 始终返回 true，是为了即使失败，仍然创建订单
                 */
                return is_error($res) ? true : $res;
            }
        );

        $device->goodsLog($level, $log_data);

        if (is_error($result)) {
            return $result;
        }

        $device->updateAppRemain();

        //事件：出货成功
        EventBus::on('device.openSuccess', $params);

        $order = $params['order'];

        if ($args['tk_order_no']) {
            TKPromoting::confirmOrder($device, $args['tk_order_no']);
        }

        return [
            'result' => $result,
            'orderId' => isset($order) ? $order->getId() : 0,
            'change' => isset($order) ? -$order->getBalance() : 0,
            'title' => '出货完成',
            'msg' => '请注意，出货完成。如未领取到商品，请扫码重试！',
        ];
    }

    public static function getNearBy(agentModelObj $agent = null): array
    {
        //请求附近设备数据
        $query = $agent ? Device::query(['agent_id' => $agent->getId()]) : Device::query();

        $result = [];

        /** @var deviceModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $location = $entry->settings('extra.location.tencent', $entry->settings('extra.location'));
            if ($location && $location['lat'] && $location['lng']) {
                unset($location['area'], $location['address']);
                $result[] = [
                    'id' => $entry->getId(),
                    'imei' => $entry->getImei(),
                    'name' => $entry->getName(),
                    'location' => $location,
                ];
            }
        }

        return $result;
    }

    public static function descAssignedStatus($assign_data): string
    {
        if (isEmptyArray($assign_data) || (isset($assign_data['all']) && empty($assign_data['all']))) {
            return '没有分配任何设备';
        } elseif ($assign_data['all']) {
            return '已分配全部设备';
        }

        return '已指定部分设备';
    }

    public static function getAds(deviceModelObj $device, $type, $max_total): array
    {
        $result = [];
        foreach ($device->getAds($type) as $item) {
            $data = [
                'id' => $item['id'],
                'title' => $item['title'],
                'data' => $item['extra'],
            ];
            if ($data['data']['image']) {
                $data['data']['image'] = Util::toMedia($data['data']['image']);
            } elseif ($data['data']['images']) {
                foreach ($data['data']['images'] as &$image) {
                    $image = Util::toMedia($image);
                }
            }
            $result[] = $data;
            if ($max_total > 0 && count($result) > $max_total) {
                break;
            }
        }

        return $result;
    }
}