<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\goods_voucher_logsModelObj;
use zovye\model\userModelObj;
use zovye\model\agentModelObj;
use zovye\model\goodsModelObj;
use zovye\model\goods_voucherModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class GoodsVoucher
{
    public static function query($condition = []): base\modelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('goods_voucher')->where($condition);
        }

        return m('goods_voucher')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function get($id)
    {
        return self::query()->findOne(['id' => $id]);
    }

    /**
     * @param $code
     * @return mixed
     */
    public static function from($code)
    {
        return self::query()->findOne(['code' => $code]);
    }

    /**
     * @param $agent
     * @param $goods
     * @param int $total
     * @param int $begin
     * @param int $end
     * @param array $limit_goods
     * @param array $assign
     * @return mixed
     */
    public static function create(
        $agent,
        $goods,
        int $total = 1,
        int $begin = 0,
        int $end = 0,
        array $limit_goods = [],
        array $assign = []
    ) {
        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('goods_voucher')->objClassname();

        $limit_goods = array_filter(array_unique($limit_goods), function ($e) {
            return $e != -1;
        });

        $data = [
            'enable' => 1,
            'agent_id' => $agent instanceof agentModelObj ? $agent->getId() : '',
            'goods_id' => $goods instanceof goodsModelObj ? $goods->getId() : -1,
            'total' => $total,
            'extra' => $classname::serializeExtra([
                'limitGoods' => $limit_goods,
                'assign' => $assign,
            ]),
            'used' => 0,
            'begin' => $begin,
            'end' => $end,
        ];

        return m('goods_voucher')->create(We7::uniacid($data));
    }

    public static function format(goods_voucherModelObj $voucher, $detail = false): array
    {
        static $agent_levels = null;
        if ($agent_levels == null) {
            $agent_levels = settings('agent.levels', []);
        }

        $data = [
            'id' => intval($voucher->getId()),
            'enabled' => $voucher->getEnable() ? 1 : 0,
            'agentId' => intval($voucher->getAgentId()),
            'goodsId' => intval($voucher->getGoodsId()),
            'total' => intval($voucher->getTotal()),
            'usedTotal' => $voucher->getUsedTotal(),
            'limitGoods' => $voucher->getExtraData('limitGoods', []),
            'createtime_formatted' => date('Y-m-d H:i:s', $voucher->getCreatetime()),
        ];
        if ($voucher->getBegin() > 0) {
            $data['begin'] = $voucher->getBegin();
            $data['begin_formatted'] = date('Y-m-d', $data['begin']);
        }
        if ($voucher->getEnd() > 0) {
            $data['end'] = $voucher->getEnd();
            $data['end_formatted'] = date('Y-m-d', $voucher->getEnd());
        }
        if ($detail) {
            $data['limitGoods'] = (array)$voucher->getExtraData('limitGoods', []);
            $data['assigned'] = (array)$voucher->getExtraData('assigned', []);
        } else {
            $assign_data = $voucher->getExtraData('assigned', []);
            $data['assigned'] = count((array)$assign_data);
            $data['assignedStatus'] = DeviceUtil::descAssignedStatus($assign_data);
        }
        if ($data['goodsId']) {
            $goods = Goods::get($data['goodsId']);
            $data['goods'] = Goods::format($goods, false, true);
        }
        if ($data['agentId']) {
            $agent = Agent::get($data['agentId']);
            if ($agent) {
                $data['agent'] = [
                    'id' => $agent->getId(),
                    'level_clr' => $agent_levels[$agent->settings('agentData.level')]['clr'],
                    'nickname' => $agent->settings('agentData.name') ?: $agent->getNickname(),
                    'avatar' => $agent->getAvatar(),
                ];
            }
        }
        $data['limitGoodsNum'] = count($data['limitGoods']);

        return $data ?? [];
    }

    public static function logs($cond = []): base\modelObjFinder
    {
        return m('goods_voucher_logs')->where(We7::uniacid([]))->where($cond);
    }

    public static function logList($params = []): array
    {
        $query = self::logs();

        if ($params['voucherId'] > 0) {
            $query->where(['voucher_id' => $params['voucherId']]);
        }

        if ($params['ownerId']) {
            $query->where(['owner_id' => $params['ownerId']]);
        }

        if ($params['usedUserId']) {
            $query->where(['used_user_id' => $params['usedUserId']]);
        }

        $now = time();
        if ($params['type'] == 'unused') {
            $query->where(['used_user_id' => 0])->whereOr(['end' => 0, 'end >' => $now])->orderBy('id DESC');
        } elseif ($params['type'] == 'used') {
            $query->where(['used_user_id >' => 0])->orderBy('used_time DESC');
        } elseif ($params['type'] == 'expired') {
            $query->where('end != 0')->where(['used_user_id' => 0, 'end <' => $now])->orderBy('end DESC');
        }

        $page = max(1, intval($params['page']));
        if (isset($params['pagesize'])) {
            $page_size = max(1, intval($params['pagesize']));
        } else {
            $page_size = DEFAULT_PAGE_SIZE;
        }

        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $logs = [];

        /** @var goods_voucherModelObj $entry */
        foreach ($query->page($page, $page_size)->orderBy('id DESC')->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'code' => $entry->getCode(),
                'ownerId' => intval($entry->getOwnerId()),
                'usedUserId' => intval($entry->getUsedUserId()),
                'voucherId' => intval($entry->getVoucherId()),
                'goodsId' => intval($entry->getGoodsId()),
                'usedTotal' => $entry->getUsedTotal(),
                'deviceId' => $entry->getDeviceId(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            $goods = Goods::get($data['goodsId']);
            if ($goods) {
                $data['goods'] = Goods::format($goods);
            }
            $user = User::get($data['ownerId']);
            if ($user) {
                $data['owner'] = $user->profile();
            }
            if ($data['usedUserId'] > 0) {
                $user = User::get($data['usedUserId']);
                if ($user) {
                    $data['usedUser'] = $user->profile();
                }
            }
            if ($data['deviceId'] > 0) {
                $device = Device::get($data['deviceId']);
                if ($device) {
                    $data['device'] = [
                        'name' => $device->getName(),
                    ];
                }
            }
            $begin = $entry->getBegin();
            if ($begin > 0) {
                $data['begin_formatted'] = date('Y-m-d', $begin);
            }
            $end = $entry->getEnd();
            if ($end > 0) {
                $data['end_formatted'] = date('Y-m-d', $end);
            }

            $used_time = $entry->getUsedTime();
            if ($used_time > 0) {
                $data['usedtime_formatted'] = date('Y-m-d H:i:s', $used_time);
            } elseif ($end > 0 && $end < $now) {
                $data['expired'] = true;
            }

            $logs[] = $data;
        }

        return [
            'total' => $total,
            'page' => $page,
            'pagesize' => $page_size,
            'type' => $params['type'],
            'logs' => $logs,
        ];
    }

    /**
     * @param userModelObj $user
     * @param mixed $voucher_ids
     * @param callable|null $fn 检查提货券是否可用
     * @return array
     */
    public static function give(userModelObj $user, $voucher_ids, callable $fn = null): array
    {
        $result = [];
        $ids = is_array($voucher_ids) ? $voucher_ids : [$voucher_ids];
        foreach ($ids as $id) {
            $voucher = self::get($id);
            if ($voucher && $voucher->getEnable()) {
                if ($fn != null && !$fn($voucher)) {
                    continue;
                }
                $begin = $voucher->getBegin();
                if ($begin > 0 && $begin > time()) {
                    continue;
                }
                $end = $voucher->getEnd();
                if ($end > 0 && $end < time()) {
                    continue;
                }

                $usedTotal = m('goods_voucher_logs')->where(['voucher_id' => $id])->count();
                if ($voucher->getTotal() <= $usedTotal) {
                    continue;
                }

                for (; ;) {
                    $code = Util::random(6, true);
                    if (!m('goods_voucher_logs')->exists(['code' => $code])) {
                        break;
                    }
                }

                $data = [
                    'voucher_idd' => $voucher->getId(),
                    'code' => $code,
                    'owner_id' => $user->getId(),
                    'goods_id' => $voucher->getGoodsId(),
                    'begin' => $begin,
                    'end' => $end,
                    'used_time' => 0,
                    'used_user_id' => 0,
                    'device_id' => 0,
                    'createtime' => time(),
                ];

                $v = m('goods_voucher_logs')->create(We7::uniacid($data));
                if ($v) {
                    $data['id'] = $v->getId();
                    $result[] = $data;
                } else {
                    Log::error('Voucher', ['error' => '创建 voucher 记录失败！']);
                }
            }
        }

        return $result;
    }

    /**
     * @param $code
     * @return goods_voucher_logsModelObj|null
     */
    public static function getLogByCode($code): ?goods_voucher_logsModelObj
    {
        return m('goods_voucher_logs')->findOne(We7::uniacid(['code' => $code]));
    }

    /**
     * @param int $id
     * @return goods_voucher_logsModelObj|null
     */
    public static function getLogById(int $id): ?goods_voucher_logsModelObj
    {
        return m('goods_voucher_logs')->findOne(['id' => $id]);
    }
}