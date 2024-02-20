<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTimeImmutable;
use zovye\domain\Locker;
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
     * @param int $num 退货数量，0表示全部， -1表示退出错商品
     * @param false $reset_payload
     * @param int $delay 指定时间后才开始检查
     */
    public static function refund($order_no, $message, int $num = 0, bool $reset_payload = false, int $delay = 0): bool
    {
        if ($delay > 0) {
            return CtrlServ::scheduleDelayJob('refund', [
                    'orderNO' => $order_no,
                    'num' => $num,
                    'reset' => $reset_payload ? 1 : 0,
                    'message' => urlencode($message),
                ], $delay) !== false;
        }

        return CtrlServ::scheduleJob('refund', [
                'orderNO' => $order_no,
                'num' => $num,
                'reset' => $reset_payload ? 1 : 0,
                'message' => urlencode($message),
            ]) !== false;
    }

    public static function deviceEventNotify(deviceModelObj $device, string $event): bool
    {
        if (Config::WxPushMessage("config.device.$event.enabled")) {
            return CtrlServ::scheduleJob(
                    'device_event_notify',
                    ['id' => $device->getId(), 'event' => $event]
                ) !== false;
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
            ], JOB_LEVEL_HIGH) !== false;
    }

    public static function createOrder($order_no, deviceModelObj $device = null): bool
    {
        if ($device && $device->isBlueToothDevice()) {
            return CtrlServ::scheduleJob('create_order', ['orderNO' => $order_no], JOB_LEVEL_HIGH) !== false;
        }

        return CtrlServ::scheduleJob('create_order_multi', ['orderNO' => $order_no], JOB_LEVEL_HIGH) !== false;
    }

    public static function orderPayResult($order_no, $start = 0, $timeout = 3): bool
    {
        return CtrlServ::scheduleDelayJob(
                'order_pay_result',
                ['orderNO' => $order_no, 'start' => $start ?: time()],
                $timeout
            ) !== false;
    }

    public static function deviceRenewalPayResult($order_no, $start = 0, $timeout = 3): bool
    {
        return CtrlServ::scheduleDelayJob(
                'device_renewal_pay_result',
                ['orderNO' => $order_no, 'start' => $start ?: time()],
                $timeout
            ) !== false;
    }

    public static function chargingPayResult($serial, $start = 0, $timeout = 3): bool
    {
        return CtrlServ::scheduleDelayJob(
                'charging_pay_result',
                ['orderNO' => $serial, 'start' => $start ?: time()],
                $timeout
            ) !== false;
    }

    public static function fuelingPayResult($serial, $start = 0, $timeout = 3): bool
    {
        return CtrlServ::scheduleDelayJob(
                'fueling_pay_result',
                ['orderNO' => $serial, 'start' => $start ?: time()],
                $timeout
            ) !== false;
    }

    public static function rechargePayResult($serial, $start = 0, $timeout = 3): bool
    {
        return CtrlServ::scheduleDelayJob(
                'recharge_pay_result',
                ['orderNO' => $serial, 'start' => $start ?: time()],
                $timeout
            ) !== false;
    }

    public static function orderTimeout($order_no, $timeout = PAY_TIMEOUT): bool
    {
        return CtrlServ::scheduleDelayJob('order_timeout', ['orderNO' => $order_no], $timeout) !== false;
    }

    public static function adReview($id): bool
    {
        return CtrlServ::scheduleJob('ad_review', ['id' => $id]) !== false;
    }

    public static function agentApplicationNotification($id): bool
    {
        return CtrlServ::scheduleJob('agent_app', ['id' => $id]) !== false;
    }

    public static function newAgent(userModelObj $user): bool
    {
        return CtrlServ::scheduleJob('new_agent', ['id' => $user->getId()]) !== false;
    }

    public static function goodsClone($goods_id): bool
    {
        return CtrlServ::scheduleJob('goods_clone', ['id' => $goods_id]) !== false;
    }

    public static function accountMsg($msg): bool
    {
        $delay = intval($msg['delay']);
        if ($delay > 0) {
            CtrlServ::scheduleDelayJob('account_msg', $msg, $delay) !== false;
        }

        return CtrlServ::scheduleJob('account_msg', $msg) !== false;
    }

    public static function order(orderModelObj $order): bool
    {
        return CtrlServ::scheduleJob('order', ['id' => $order->getId()]) !== false;
    }

    public static function getResult($order_no, $openid): bool
    {
        return CtrlServ::scheduleJob('get_result', ['openid' => $openid, 'orderNO' => $order_no]) !== false;
    }

    public static function withdraw($user_id): bool
    {
        return CtrlServ::scheduleJob('withdraw', ['id' => $user_id]) !== false;
    }

    public static function createThirdPartyPlatformOrder($params = []): bool
    {
        return self::createAccountOrder($params);
    }

    public static function createAccountOrder($params = [], $delay = 0): bool
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

        if ($delay > 0) {
            return CtrlServ::scheduleDelayJob('create_order_account', $params, $delay) !== false;
        }

        return CtrlServ::scheduleJob('create_order_account', $params) !== false;
    }

    public static function createRewardOrder($params = []): bool
    {
        return CtrlServ::scheduleJob('create_order_reward', $params) !== false;
    }

    public static function authAccount($agent_id, $accountUID, $total = 0): bool
    {
        return CtrlServ::scheduleDelayJob(
                'auth_account',
                ['agent' => $agent_id, 'account' => $accountUID, 'total' => $total],
                3
            ) !== false;
    }

    public static function douyinOrder(userModelObj $user, deviceModelObj $device, $account_uid, $time = null): bool
    {
        return CtrlServ::scheduleJob('douyin', [
                'id' => $user->getId(),
                'device' => $device->getId(),
                'uid' => $account_uid,
                'time' => $time ?? time(),
            ]) !== false;
    }

    public static function updateAgentCounter(agentModelObj $agent): bool
    {
        if ($agent->acquireLocker("update_counter")) {
            if (time() - $agent->settings('extra.counter.last', 0) > 300) {
                $agent->updateSettings('extra.counter.last', time());

                return CtrlServ::scheduleJob('update_counter', [
                        'agent' => $agent->getId(),
                        'device' => 0,
                        'datetime' => (new DateTimeImmutable('-1 hour'))->format("Y-m-d H:00:00"),
                    ]) !== false;
            }
        }

        return true;
    }

    public static function updateDeviceCounter(deviceModelObj $device): bool
    {
        if (Locker::try("device:{$device->getId()}:update_counter", REQUEST_ID, 3)) {
            if (time() - $device->settings('extra.counter.last', 0) > 400) {
                $device->updateSettings('extra.counter.last', time());

                return CtrlServ::scheduleJob('update_counter', [
                        'agent' => 0,
                        'device' => $device->getId(),
                        'datetime' => (new DateTimeImmutable('-1 hour'))->format("Y-m-d H:00:00"),
                    ]) !== false;
            }
        }

        return true;
    }

    public static function updateAppCounter(): bool
    {
        $uid = APP_NAME;
        if (Locker::try("app:$uid:update_counter", REQUEST_ID, 3)) {
            if (time() - Config::app('order.counter.last', 0) > 600) {
                Config::app('order.counter.last', time(), true);

                return CtrlServ::scheduleJob('update_counter', [
                        'agent' => 0,
                        'device' => 0,
                        'datetime' => (new DateTimeImmutable('-1 hour'))->format("Y-m-d H:00:00"),
                    ]) !== false;
            }
        }

        return true;
    }

    public static function uploadDeviceInfo($lastId = 0): bool
    {
        return CtrlServ::scheduleJob('upload_device_info', ['lastId' => $lastId]) !== false;
    }

    public static function refreshSettings(): bool
    {
        return CtrlServ::scheduleJob('refresh_settings') !== false;
    }

    public static function chargingStartTimeout(
        $serial,
        $chargerID,
        $device_id,
        $user_id,
        $order_id
    ): bool {
        return CtrlServ::scheduleDelayJob('charging_start_timeout', [
                'uid' => $serial,
                'chargerID' => $chargerID,
                'device' => $device_id,
                'user' => $user_id,
                'order' => $order_id,
                'time' => time(),
            ], 180) !== false;
    }

    public static function chargingStopTimeout($serial): bool
    {
        return CtrlServ::scheduleDelayJob('charging_stop_timeout', [
                'uid' => $serial,
                'time' => time(),
            ], 60) !== false;
    }

    public static function fuelingStartTimeout(
        $serial,
        $chargerID,
        $device_id,
        $user_id,
        $order_id
    ): bool {
        return CtrlServ::scheduleDelayJob('fueling_start_timeout', [
                'uid' => $serial,
                'chargerID' => $chargerID,
                'device' => $device_id,
                'user' => $user_id,
                'order' => $order_id,
                'time' => time(),
            ], 70) !== false;
    }

    public static function fuelingStopTimeout($serial): bool
    {
        return CtrlServ::scheduleDelayJob('fueling_stop_timeout', [
                'uid' => $serial,
                'time' => time(),
            ], 60) !== false;
    }

    public static function createOrderFor(orderModelObj $order): bool
    {
        return CtrlServ::scheduleJob('create_order_for', [
                'orderNO' => $order->getOrderNO(),
            ]) !== false;
    }

    public static function orderNotify(orderModelObj $order): bool
    {
        return CtrlServ::scheduleJob('order_notify', [
                'id' => $order->getId(),
                'device_id' => 0,
                'order' => '',
                'goods' => '',
                'time' => '',
            ]) !== false;
    }

    public static function orderErrorNotify(deviceModelObj $device, array $data = []): bool
    {
        return CtrlServ::scheduleJob('order_notify', [
                'id' => 0,
                'device_id' => $device->getId(),
                'order' => $data['order_no'] ?? '',
                'goods' => $data['goods_name'] ?? '<未指定商品>',
                'time' => $data['time'] ?? date('Y-m-d H:i:s', TIMESTAMP),
            ]) !== false;
    }
}
