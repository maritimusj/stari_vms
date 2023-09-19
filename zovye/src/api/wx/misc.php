<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\App;
use zovye\domain\Goods;
use zovye\model\agentModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Helper;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;

class misc
{
    public static function getLowRemainDeviceTotal($agent): int
    {
        $remainWarning = App::getRemainWarningNum($agent);

        return \zovye\domain\Device::query(['agent_id' => $agent->getId(), 'remain <' => $remainWarning])->count();
    }

    public static function deviceStats(agentModelObj $agent): array
    {
        $total = $agent->getDeviceCount();
        $all_devices = $total;
        $low_remain_total = self::getLowRemainDeviceTotal($agent);

        /** @var userModelObj $sub */
        $list = [];
        \zovye\domain\Agent::getAllSubordinates($agent, $list, true);
        foreach ($list as $sub) {
            if ($sub->isAgent()) {
                $sa = $sub->agent();
                $all_devices += $sa->getDeviceCount();
                $low_remain_total += self::getLowRemainDeviceTotal($sa);
            }
        }

        return [
            'total' => $total,
            'all' => $all_devices,
            'low_remain' => $low_remain_total,
        ];
    }

    public static function orderStats(agentModelObj $agent): array
    {
        $query = \zovye\domain\Order::query();
        if (Request::bool('all')) {
            $ids = \zovye\domain\Agent::getAllSubordinates($agent);
            $ids[] = $agent->getId();
            $query->where(['agent_id' => $ids]);
        } else {
            $query->where(['agent_id' => $agent->getId()]);
        }

        $goods_id = Request::int('goods');
        if ($goods_id > 0) {
            $query->where(['goods_id' => $goods_id]);
        }

        if (Request::has('group')) {
            $group = \zovye\domain\Group::get(Request::int('group'));
            if (empty($group) || $group->getAgentId() != $agent->getId()) {
                return err('分组不存在！');
            }
            $device_ids = [];
            $device_query = \zovye\domain\Device::query(['group_id' => $group->getId()]);
            $result = $device_query->findAll([], true);
            for ($i = 0; $i < count($result); $i++) {
                $device_ids[] = $result[$i]['id'];
            }
            $query->where([
                'device_id' => $device_ids,
            ]);
        }

        if (Request::has('start')) {
            try {
                $start = new DateTime(Request::trim('start'));
                $query->where(['createtime >=' => $start->getTimestamp()]);
            } catch (Exception $e) {
                return err('起始时间不正确！');
            }
        }

        if (Request::has('end')) {
            try {
                $end = new DateTime(Request::trim('end'));
                $end->modify('next day 00:00');
                $query->where(['createtime <' => $end->getTimestamp()]);
            } catch (Exception $e) {
                return err('结束时间不正确！');
            }
        }

        list($price, $num, $amount) = $query->get(['sum(price)', 'count(*)', 'sum(num)']);

        $result = [
            'price' => intval($price),
            'price_formatted' => number_format($price / 100, 2),
            'num' => intval($num),
            'amount' => intval($amount),
        ];

        if (Request::bool('detail')) {
            $list = [];
            $query->groupBy('goods_id');
            $res = $query->getAll(['goods_id', 'count(*) AS num', 'sum(price) AS price', 'sum(num) AS amount']);
            foreach ((array)$res as $entry) {
                $goods = Goods::get($entry['goods_id']);
                if ($goods) {
                    $list[] = [
                        'goods' => Goods::format($goods),
                        'num' => intval($entry['num']),
                        'amount' => intval($entry['amount']),
                        'price' => intval($entry['price']),
                        'price_formatted' => number_format($entry['price'] / 100, 2),
                    ];
                }
            }
            $result['goods'] = $list;
        }

        return $result;
    }

    public static function updateUserQRCode(userModelObj $user, $type): array
    {
        $res = Helper::upload('pic', $type);
        if (is_error($res)) {
            return $res;
        }

        $user_qrcode = $user->settings('qrcode', []);
        $user_qrcode[$type] = $res;

        if ($user->updateSettings('qrcode', $user_qrcode)) {
            return ['status' => 'success', 'msg' => '上传成功！'];
        }

        return err('保存用户信息失败！');
    }

    public static function getUserQRCode(userModelObj $user): array
    {
        $user_qrcode = $user->settings('qrcode', []);
        if (isset($user_qrcode['wx'])) {
            $user_qrcode['wx'] = Util::toMedia($user_qrcode['wx']);
        }
        if (isset($user_qrcode['ali'])) {
            $user_qrcode['ali'] = Util::toMedia($user_qrcode['ali']);
        }

        return (array)$user_qrcode;
    }

    public static function setUserBank(userModelObj $user): array
    {
        $bankData = [
            'realname' => Request::trim('realname'),
            'bank' => Request::trim('bank'),
            'branch' => Request::trim('branch'),
            'account' => Request::trim('account'),
            'address' => [
                'province' => Request::trim('province'),
                'city' => Request::trim('city'),
            ],
        ];

        $result = $user->updateSettings('agentData.bank', $bankData);

        return $result ? ['msg' => '保存成功！'] : err('保存失败！');
    }

    public static function getUserBank(userModelObj $user): array
    {
        return $user->settings(
            'agentData.bank',
            [
                'realname' => '',
                'bank' => '',
                'branch' => '',
                'account' => '',
                'address' => [
                    'province' => '',
                    'city' => '',
                ],
            ]
        );
    }
}