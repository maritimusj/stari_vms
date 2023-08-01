<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTimeImmutable;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;

class Job
{
    public static function exit(callable $fn = null)
    {
        if ($fn != null) {
            $fn();
        }
        exit(CtrlServ::OK);
    }

    /**
     * 启动一个退款任务，如果订单符合退款条件，就会发起退款操作
     * @param $order_no
     * @param $message
     * @param int $num 退货数量，0表示全部， -1表示退出错商品
     * @param false $reset_payload
     * @param int $delay 指定时间后才开始检查
     * @return bool
     */
    public static function refund($order_no, $message, int $num = 0, bool $reset_payload = false, int $delay = 0): bool
    {
        if ($delay > 0) {
            return CtrlServ::scheduleDelayJob('refund', [
                'orderNO' => $order_no,
                'num' => $num,
                'reset' => $reset_payload,
                'message' => urlencode($message),
            ], $delay);
        }

        return CtrlServ::scheduleJob('refund', [
            'orderNO' => $order_no,
            'num' => $num,
            'reset' => $reset_payload,
            'message' => urlencode($message),
        ]);
    }

    public static function deviceEventNotify(deviceModelObj $device, string $event): bool
    {
        if (Config::WxPushMessage("config.device.event.$event.enabled")) {
            return CtrlServ::scheduleJob('device_event', ['id' => $device->getId(), 'event' => $event]);
        }

        return false;
    }

    public static function createBalanceOrder(
        $order_no,
        userModelObj $user,
        deviceModelObj $device,
        $goods_id,
        $num,
        $ip
    ): bool {
        return CtrlServ::scheduleJob('create_order_balance', [
                'order_no' => $order_no,
                'user' => $user->getId(),
                'device' => $device->getId(),
                'goods' => $goods_id,
                'num' => $num,
                'ip' => $ip,
            ], LEVEL_HIGH) !== false;
    }

    /**
     * @param $order_no
     * @param deviceModelObj|null $device
     * @return mixed
     */
    public static function createOrder($order_no, deviceModelObj $device = null)
    {
        if ($device && $device->isBlueToothDevice()) {
            return CtrlServ::scheduleJob('create_order', ['orderNO' => $order_no], LEVEL_HIGH);
        }

        return CtrlServ::scheduleJob('create_order_multi', ['orderNO' => $order_no], LEVEL_HIGH);
    }

    public static function orderPayResult($order_no, $start = 0, $timeout = 3)
    {
        return CtrlServ::scheduleDelayJob(
            'order_pay_result',
            ['orderNO' => $order_no, 'start' => $start ?: time()],
            $timeout
        );
    }

    public static function deviceRenewalPayResult($order_no, $start = 0, $timeout = 3)
    {
        return CtrlServ::scheduleDelayJob(
            'device_renewal_pay_result',
            ['orderNO' => $order_no, 'start' => $start ?: time()],
            $timeout
        );
    }

    public static function chargingPayResult($serial, $start = 0, $timeout = 3)
    {
        return CtrlServ::scheduleDelayJob(
            'charging_pay_result',
            ['orderNO' => $serial, 'start' => $start ?: time()],
            $timeout
        );
    }

    public static function fuelingPayResult($serial, $start = 0, $timeout = 3)
    {
        return CtrlServ::scheduleDelayJob(
            'fueling_pay_result',
            ['orderNO' => $serial, 'start' => $start ?: time()],
            $timeout
        );
    }

    public static function rechargePayResult($serial, $start = 0, $timeout = 3)
    {
        return CtrlServ::scheduleDelayJob(
            'recharge_pay_result',
            ['orderNO' => $serial, 'start' => $start ?: time()],
            $timeout
        );
    }

    public static function orderTimeout($order_no, $timeout = PAY_TIMEOUT)
    {
        return CtrlServ::scheduleDelayJob('order_timeout', ['orderNO' => $order_no], $timeout);
    }

    public static function advReview($id): bool
    {
        return CtrlServ::scheduleJob('adv_review', ['id' => $id]);
    }

    public static function advReviewResult($id): bool
    {
        return CtrlServ::scheduleJob('adv_review_result', ['id' => $id]);
    }

    public static function newAgent($user_id): bool
    {
        return CtrlServ::scheduleJob('new_agent', ['id' => $user_id]);
    }

    public static function agentMsgNotice($msgId): bool
    {
        return CtrlServ::scheduleJob('agent_msg', ['id' => $msgId]);
    }

    public static function agentApplyNotice($id): bool
    {
        return CtrlServ::scheduleJob('agent_app', ['id' => $id]);
    }

    public static function agentAppForward($id, $target_ids = []): bool
    {
        $job = [
            'id' => $id,
            'agentIds' => urlencode(serialize($target_ids)),
        ];

        return CtrlServ::scheduleJob('forward_agent_app', $job);
    }

    public static function goodsClone($goods_id): bool
    {
        return CtrlServ::scheduleJob('goods_clone', ['id' => $goods_id]);
    }

    public static function accountMsg($msg): bool
    {
        $delay = intval($msg['delay']);
        if ($delay > 0) {
            CtrlServ::scheduleDelayJob('account_msg', $msg, $delay);
        }

        return CtrlServ::scheduleJob('account_msg', $msg);
    }

    public static function order($order_id): bool
    {
        $queue = Config::app('queue', []);
        if (empty($queue['max_size']) || $queue['size'] < $queue['max_size']) {
            $queue['size'] = CtrlServ::scheduleJob('order', ['id' => $order_id]);
            $queue['updatetime'] = time();
            Config::app('queue', $queue, true);

            return $queue['size'] !== false;
        }

        return false;
    }

    public static function getResult($order_no, $openid): bool
    {
        return CtrlServ::scheduleJob('get_result', ['openid' => $openid, 'orderNO' => $order_no]);
    }

    public static function withdraw($user_id, $amount): bool
    {
        return CtrlServ::scheduleJob('withdraw', ['id' => $user_id, 'amount' => $amount]);
    }

    public static function createThirdPartyPlatformOrder($params = []): bool
    {
        return self::createAccountOrder($params);
    }

    public static function createAccountOrder($params = []): bool
    {
        $params = array_merge([
            'account' => '',
            'device' => '',
            'user' => '',
            'goods' => '',
            'orderUID' => '',
            'ignoreGoodsNum' => 0,
            'ip' => '',
        ], $params);

        return CtrlServ::scheduleJob('create_order_account', $params);
    }

    public static function createRewardOrder($params = []): bool
    {
        return CtrlServ::scheduleJob('create_order_reward', $params);
    }

    public static function authAccount($agent_id, $accountUID, $total = 0): bool
    {
        return CtrlServ::scheduleDelayJob(
            'auth_account',
            ['agent' => $agent_id, 'account' => $accountUID, 'total' => $total],
            3
        );
    }

    public static function repairAgentMonthStats($agent_id, $month): bool
    {
        $key = "repair.$agent_id.month:$month";
        if (time() - Config::agent($key) < 300) {
            return false;
        }
        if (CtrlServ::scheduleJob('repair', ['agent' => $agent_id, 'month' => $month]) !== false) {
            Config::agent($key, time(), true);

            return true;
        }

        return false;
    }

    public static function douyinOrder(userModelObj $user, deviceModelObj $device, $account_uid, $time = null)
    {
        return CtrlServ::scheduleJob('douyin', [
            'id' => $user->getId(),
            'device' => $device->getId(),
            'uid' => $account_uid,
            'time' => $time ?? time(),
        ]);
    }

    public static function updateAgentCounter(agentModelObj $agent)
    {
        if ($agent->acquireLocker("update_counter")) {
            if (time() - $agent->settings('extra.counter.last', 0) > 300) {
                $agent->updateSettings('extra.counter.last', time());

                return CtrlServ::scheduleJob('update_counter', [
                    'agent' => $agent->getId(),
                    'device' => 0,
                    'datetime' => (new DateTimeImmutable('-1 hour'))->format("Y-m-d H:00:00"),
                ]);
            }
        }

        return true;
    }

    public static function updateDeviceCounter(deviceModelObj $device)
    {
        if (Locker::try("device:{$device->getId()}:update_counter", REQUEST_ID, 3)) {
            if (time() - $device->settings('extra.counter.last', 0) > 400) {
                $device->updateSettings('extra.counter.last', time());

                return CtrlServ::scheduleJob('update_counter', [
                    'agent' => 0,
                    'device' => $device->getId(),
                    'datetime' => (new DateTimeImmutable('-1 hour'))->format("Y-m-d H:00:00"),
                ]);
            }
        }

        return true;
    }

    public static function updateAppCounter()
    {
        $uid = APP_NAME;
        if (Locker::try("app:$uid:update_counter", REQUEST_ID, 3)) {
            if (time() - Config::app('order.counter.last', 0) > 600) {
                Config::app('order.counter.last', time(), true);

                return CtrlServ::scheduleJob('update_counter', [
                    'agent' => 0,
                    'device' => 0,
                    'datetime' => (new DateTimeImmutable('-1 hour'))->format("Y-m-d H:00:00"),
                ]);
            }
        }

        return true;
    }

    public static function uploadDeviceInfo($lastId = 0): bool
    {
        if (CtrlServ::scheduleJob('upload_device_info', ['lastId' => $lastId]) !== false) {
            return true;
        }

        return false;
    }

    public static function refreshSettings(): bool
    {
        if (CtrlServ::scheduleJob('refresh_settings') !== false) {
            return true;
        }

        return false;
    }

    public static function chargingStartTimeout(
        $serial,
        $chargerID,
        $device_id,
        $user_id,
        $order_id
    ): bool {
        if (CtrlServ::scheduleDelayJob('charging_start_timeout', [
                'uid' => $serial,
                'chargerID' => $chargerID,
                'device' => $device_id,
                'user' => $user_id,
                'order' => $order_id,
                'time' => time(),
            ], 70) !== false) {
            return true;
        }

        return false;
    }

    public static function chargingStopTimeout($serial): bool
    {
        if (CtrlServ::scheduleDelayJob('charging_stop_timeout', [
                'uid' => $serial,
                'time' => time(),
            ], 60) !== false) {
            return true;
        }

        return false;
    }

    public static function fuelingStartTimeout(
        $serial,
        $chargerID,
        $device_id,
        $user_id,
        $order_id
    ): bool {
        if (CtrlServ::scheduleDelayJob('fueling_start_timeout', [
                'uid' => $serial,
                'chargerID' => $chargerID,
                'device' => $device_id,
                'user' => $user_id,
                'order' => $order_id,
                'time' => time(),
            ], 70) !== false) {
            return true;
        }

        return false;
    }

    public static function fuelingStopTimeout($serial): bool
    {
        if (CtrlServ::scheduleDelayJob('fueling_stop_timeout', [
                'uid' => $serial,
                'time' => time(),
            ], 60) !== false) {
            return true;
        }

        return false;
    }

    public static function createOrderFor(orderModelObj $order): bool
    {
        if (CtrlServ::scheduleJob('create_order_for', [
                'orderNO' => $order->getOrderNO(),
            ]) !== false) {
            return true;
        }

        return false;
    }

    public static function orderNotify(orderModelObj $order): bool
    {
        if (CtrlServ::scheduleJob('order_notify', [
                'id' => $order->getId(),
            ]) !== false) {
            return true;
        }

        return false;
    }
}
