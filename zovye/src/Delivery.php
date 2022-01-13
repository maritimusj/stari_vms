<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\deliveryModelObj;
use zovye\model\goodsModelObj;

class Delivery
{
    const UNPAID = 0; //未支付
    const PAYED = 1; //已支付
    const REFUND = 2; //已退款

    const SHIPPING = 3; //已发货
    const CONFIRMED = 4; //已确认收货
    const RETURNING = 5;//退货中
    const RETURNED = 6; //已确认退货

    const FINISHED = 100; //已完成

    public static function create($data = []): ?deliveryModelObj
    {
        /** @var goodsModelObj $classname */
        $classname = m('goods')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('delivery')->create($data);
    }

    public static function query(): modelObjFinder
    {
        return m('delivery')->query();
    }

    public static function findOne($condition = []): ?deliveryModelObj
    {
        return self::query()->findOne($condition);
    }

    public static function getList($params = []): array
    {
        $page = max(1, intval($params['page']));
        $page_size = empty($params['pagesize']) ? DEFAULT_PAGE_SIZE : intval($params['pagesize']);

        $query = self::query();

        if (isset($params['user_id'])) {
            $query->where(['user_id' => intval($params['user_id'])]);
        }

        if (isset($params['status'])) {
            $query->where(['status' => intval($params['status'])]);
        }

        if ($params['keyword']) {
            $query->whereOr([
                'name LIKE' => "%{$params['keyword']}",
                'phone_num LIKE' => "%{$params['keyword']}",
                'address LIKE' => "%{$params['keyword']}",
            ]);
        }

        $total = $query->count();

        $total_page = ceil($total / $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $list = [];
        /** @var deliveryModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $user = $entry->getUser();
            $list[] = [
                'id' => $entry->getId(),
                'user' => $user ? $user->profile() : [],
                'name' => $entry->getName(),
                'phoneNum' => $entry->getPhoneNum(),
                'address' => $entry->getAddress(),
                'goods' => $entry->getExtraData('goods', []),
                'num' => $entry->getNum(),
                'createtime' => $entry->getCreatetime(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];
        }

        return [
            'list' => $list,
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => $total_page,
        ];
    }
}