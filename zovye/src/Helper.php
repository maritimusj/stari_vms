<?php


namespace zovye;

use zovye\model\device_logsModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;

class Helper
{
    /**
     * 设备故障时，订单是否需要自动退款
     * @param null $obj
     * @return bool
     */
    public static function NeedAutoRefund($obj = null): bool
    {
        if ($obj instanceof deviceModelObj) {
            $device = $obj;
        } elseif ($obj instanceof orderModelObj) {
            $device = $obj->getDevice();
        }

        if (isset($device)) {
            $agent = $device->getAgent();
            if ($agent) {
                $agent_auto_refund = intval($agent->settings('agentData.misc.auto_ref'));
                if ($agent_auto_refund == 1) {
                    return true;
                } elseif ($agent_auto_refund == 2) {
                    return false;
                }
            }
        }

        return settings('order.rollback.enabled', false);
    }

    /**
     * 是否设置必须关注公众号以后才能购买商品
     * @param deviceModelObj $device
     * @return bool
     */
    public static function MustFollowAccount(deviceModelObj $device): bool
    {
        if (!App::isMustFollowAccountEnabled()) {
            return false;
        }

        $enabled = $device->settings('extra.mfa.enable');
        if (isset($enabled) && $enabled != -1) {
            return boolval($enabled);
        }

        $agent = $device->getAgent();
        if ($agent) {
            $enabled = $agent->settings('agentData.mfa.enable');
            if (isset($enabled) && $enabled != -1) {
                return boolval($enabled);
            }
        }

        $enabled = settings('mfa.enable');
        return boolval($enabled);
    }

    public static function getOrderPullLog(orderModelObj $order): array
    {
        $condition = We7::uniacid([
            'createtime >=' => $order->getCreatetime(),
            'createtime <' => $order->getCreatetime() + 3600,
            'data REGEXP' => "s:5:\"order\";i:{$order->getId()};",
        ]);
    
        $device = $order->getDevice();
        if ($device) {
            $condition['title'] = $device->getImei();
        }
    
        $query = m('device_logs')->where($condition);
    
        $list = [];
        /** @var device_logsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'imei' => $entry->getTitle(),
                'title' => Device::formatPullTitle($entry->getLevel()),
                'price' => $entry->getData('price'),
                'goods' => $entry->getData('goods'),
                'user' => $entry->getData('user'),
            ];
    
            $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);
    
            $result = $entry->getData('result');
            if (is_array($result)) {
                if (isset($result['errno'])) {
                    $data['result'] = [
                        'errno' => intval($result['errno']),
                        'message' => $result['message'],
                    ];
                } elseif (isset($result['data']['errno'])) {
                    $data['result'] = [
                        'errno' => intval($result['data']['errno']),
                        'message' => $result['data']['message'],
                    ];
                } else {
                    $data['result'] = [
                        'errno' => -1,
                        'message' => '<未知>',
                    ];
                }
            } else {
                $data['result'] = [
                    'errno' => empty($result),
                    'message' => empty($result) ? '失败' : '成功',
                ];
            }
    
            $list[] = $data;
        }

        return $list;
    }

    public static function isZeroBonus(deviceModelObj $device): bool
    {
        if (App::isZeroBonusEnabled()) {
            $v = $device->settings('extra.custom.bonus.zero.v', -1.0);
            if ($v < 0) {
                $agent = $device->getAgent();
                if ($agent) {
                    $v = $agent->settings('agentData.custom.bonus.zero.v', -1.0);
                }
                if ($v < 0) {
                    $v = settings('custom.bonus.zero.v', -1.0);
                }
            }
            return $v > 0 && mt_rand(1, 10000) <= intval($v * 100);
        }

        return false;
    }
}