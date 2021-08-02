<?php

namespace zovye;

use zovye\model\deviceModelObj;

class Job
{
    public static function exit()
    {
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
    public static function refund($order_no, $message, $num = 0, $reset_payload = false, $delay = 0): bool
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
        return CtrlServ::scheduleDelayJob('order_pay_result', ['orderNO' => $order_no, 'start' => $start ? $start : time()], $timeout);
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
        return CtrlServ::scheduleJob('order', ['id' => $order_id]);
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

    public static function createSpecialAccountOrder($params = []): bool
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

    public static function authAccount($agent_id, $accountUID): bool
    {
        return CtrlServ::scheduleDelayJob('auth_account', ['agent' => $agent_id, 'account' => $accountUID], 3);
    }

    public static function repairAgentMonthStats($agent_id, $month): bool
    {
        return CtrlServ::scheduleJob('repair', ['agent' => $agent_id, 'month' => $month]);
    }
}
