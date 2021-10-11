<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\App;
use zovye\Goods;
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

        $query = \zovye\Order::query();
        if (request::bool('all')) {
            $ids = \zovye\Agent::getAllSubordinates($agent);
            $ids[] = $agent->getId();
            $query->where(['agent_id' => $ids]);
        } else {
            $query->where(['agent_id' => $agent->getId()]);
        }

        $goods_id = request::int('goods');
        if ($goods_id > 0) {
            $query->where(['goods_id' => $goods_id]);
        }

        if (request::has('group')) {
            $group = \zovye\Group::get(request::int('group'));
            if (empty($group) || $group->getAgentId() != $agent->getId()) {
                return err('分组不存在！');
            }
            $device_ids = [];
            $device_query = \zovye\Device::query(['group_id' => $group->getId()]);
            $result = $device_query->findAll([], true);
            for ($i = 0; $i < count($result); $i++) {
                $device_ids[] = $result[$i]['id'];
            }
            $query->where([
                'device_id' => $device_ids,
            ]);
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

        $result = [
            'price' => intval($price),
            'price_formatted' => number_format($price / 100, 2),
            'num' => intval($num),
        ];

        if (request::bool('detail')) {
            $list = [];
            $query->groupBy('goods_id');
            $res = $query->getAll(['goods_id', 'count(*) AS num', 'sum(price) AS price']);
            foreach ((array)$res as $entry) {
                $goods = Goods::get($entry['goods_id']);
                if ($goods) {
                    $list[] = [
                        'goods' => Goods::format($goods),
                        'num' => intval($entry['num']),
                        'price' => intval($entry['price']),
                        'price_formatted' => number_format($entry['price'] / 100, 2),
                    ];
                }
            }
            $result['goods'] = $list;
        }

        return $result;
    }
}