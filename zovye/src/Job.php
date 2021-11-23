<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\balanceModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class Job
{
    public static function exit(callable $fn = null)
    {
        if ($fn != null) {
            $fn();
        }
        exit(CtrlServ::HANDLE_OK);
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

    public static function deviceOnlineNotify(deviceModelObj $device, $msg = '上线'): bool
    {
        return CtrlServ::scheduleJob('device_online', ['id' => $device->getId(), 'event' => $msg]);
    }

    public static function createBalanceOrder(balanceModelObj $balance): bool
    {
        return CtrlServ::scheduleJob('create_order_balance', [
            'balance' => $balance->getId(),
        ], LEVEL_HIGH);
    }

    /**
     * @param $order_no
     * @param deviceModelObj|null $device
     * @return bool
     */
    public static function createOrder($order_no, deviceModelObj $device = null): bool
    {
        if ($device && $device->isBlueToothDevice()) {
            return CtrlServ::scheduleJob('create_order', ['orderNO' => $order_no], LEVEL_HIGH);
        }
        return CtrlServ::scheduleJob('create_order_multi', ['orderNO' => $order_no], LEVEL_HIGH);
    }

    public static function orderPayResult($order_no, $start = 0, $timeout = 3): bool
    {
        return CtrlServ::scheduleDelayJob('order_pay_result', ['orderNO' => $order_no, 'start' => $start ?: time()], $timeout);
    }

    public static function orderTimeout($order_no, $timeout = PAY_TIMEOUT): bool
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

    public static function orderStats($order_id): bool
    {
        return CtrlServ::scheduleJob('order_stats', ['id' => $order_id]);
    }

    public static function orderStatsRepair(): bool
    {
        return CtrlServ::scheduleJob('order_stats', ['id' => 0]);
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

    public static function devicePayloadWarning($device_id): bool
    {
        return CtrlServ::scheduleJob('remain_warning', ['id' => $device_id]);
    }

    public static function deviceErrorNotice($device_id, $errno, $err_msg): bool
    {
        return CtrlServ::scheduleJob('device_err', ['id' => $device_id, 'errno' => $errno, 'message' => $err_msg]);
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
            'ip' => '',
        ], $params);

        return CtrlServ::scheduleJob('create_order_account', $params);
    }

    public static function authAccount($agent_id, $accountUID, $total = 0): bool
    {
        return CtrlServ::scheduleDelayJob('auth_account', ['agent' => $agent_id, 'account' => $accountUID, 'total' => $total], 3);
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
}
