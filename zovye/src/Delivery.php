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

        $total = $query->count();

        $total_page = ceil($total / $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $list = [];
        /** @var deliveryModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $list[] = [
                'id' => $entry->getId(),
                'user' => $entry->getUserId(),
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