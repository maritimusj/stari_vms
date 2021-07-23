<?php

namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\App;
use zovye\model\userModelObj;
use zovye\request;
use function zovye\err;

class misc
{
    public static function getLowRemainDeviceTotal($agent): int
    {
        $remainWarning = App::remainWarningNum($agent);
        return \zovye\Device::query(['agent_id' => $agent->getId(), 'remain <' => $remainWarning])->count();
    }

    public static function deviceStats(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $total = $user->getDeviceCount();
        $all_devices = $total;
        $low_remain_total = self::getLowRemainDeviceTotal($agent);

        /** @var userModelObj $sub */
        $list = [];
        \zovye\Agent::getAllSubordinates($agent, $list, true);
        foreach ($list as $sub) {
            if ($sub->isAgent()) {
                $sa = $sub->agent();
                if ($sa) {
                    $all_devices += $sa->getDeviceCount();
                    $low_remain_total += self::getLowRemainDeviceTotal($sa);
                }
            }
        }

        return [
            'total' => $total,
            'all' => $all_devices,
            'low_remain' => $low_remain_total,
        ];
    }

    public static function orderStats(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $query = \zovye\Order::query(['agent_id' => $agent->getId()]);

        $goods_id = request::int('goods');
        if ($goods_id > 0) {
            $query->where(['goods_id' => $goods_id]);
        }

        if (request::has('start')) {
            try {
                $start = new DateTime(request::trim('start'));
                $query->where(['createtime >=' => $start->getTimestamp()]);
            } catch (Exception $e) {
                return err('起始时间不正确！');
            }            
        }

        if (request::has('end')) {
            try {
                $end = new DateTime(request::trim('end'));
                $end->modify('next day 00:00');
                $query->where(['createtime <' => $end->getTimestamp()]);
            } catch (Exception $e) {
                return err('结束时间不正确！');
            }            
        }

        list($price, $num) = $query->get(['sum(price)', 'count(num)']);

        return [
            'price' => intval($price),
            'price_formatted' => number_format($price / 100, 2),
            'num' => intval($num),
        ];
    }
}