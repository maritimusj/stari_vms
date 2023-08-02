<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\deliveryModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;

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

    public static function query($condition = []): modelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('delivery')->where($condition);
        }

        return m('delivery')->query(We7::uniacid([]))->where($condition);
    }

    public static function get($id): ?deliveryModelObj
    {
        return self::query()->findOne(['id' => $id]);
    }

    public static function findOne($condition = []): ?deliveryModelObj
    {
        return self::query()->findOne($condition);
    }

    public static function makeUID(userModelObj $user, $nonce = ''): string
    {
        return substr("U{$user->getId()}P$nonce".Util::random(32, true), 0, MAX_ORDER_NO_LEN);
    }

    public static function formatStatus($status): string
    {
        static $status_title = [
            self::UNPAID => '未支付',
            self::PAYED => '已支付',
            self::REFUND => '已退款',
            self::SHIPPING => '已发货',
            self::CONFIRMED => '已确认',
            self::RETURNING => '退货中',
            self::RETURNED => '已退货',
            self::FINISHED => '已完成',
        ];

        return $status_title[$status] ?? '未知状态';
    }

    public static function getList($params = []): array
    {
        $query = self::query();

        if (isset($params['user_id'])) {
            $query->where(['user_id' => intval($params['user_id'])]);
        }

        if (isset($params['status'])) {
            $query->where(['status' => intval($params['status'])]);
        }

        if ($params['keyword']) {
            $query->whereOr([
                'order_no LIKE' => "%{$params['keyword']}",
                'name LIKE' => "%{$params['keyword']}",
                'phone_num LIKE' => "%{$params['keyword']}",
                'address LIKE' => "%{$params['keyword']}",
            ]);
        }

        $result = [];

        $total = $query->count();
        $result['total'] = $total;

        $page_size = empty($params['pagesize']) ? DEFAULT_PAGE_SIZE : intval($params['pagesize']);

        if ($params['page']) {
            $page = max(1, intval($params['page']));
            $query->page($page, $page_size);

            $result['page'] = $page;
            $result['total_page'] = ceil($total / $page_size);

        } else {
            if ($params['last_id']) {
                $last_id = $params['last_id'];
                $result['list_id'] = $last_id;
                $query->where(['id <' => $last_id]);
            }

            $query->limit($page_size);
        }

        $query->orderBy('id DESC');

        $list = [];
        /** @var deliveryModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $user = $entry->getUser();
            $data = [
                'id' => $entry->getId(),
                'orderNO' => $entry->getOrderNo(),
                'user' => $user ? $user->profile(false) : [],
                'recipient' => [
                    'name' => $entry->getName(),
                    'phoneNum' => $entry->getPhoneNum(),
                    'address' => $entry->getAddress(),
                ],
                'goods' => $entry->getExtraData('goods', []),
                'num' => $entry->getNum(),
                'status' => $entry->getStatus(),
                'status_formatted' => self::formatStatus($entry->getStatus()),
                'createtime' => $entry->getCreatetime(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            $balance = $entry->getExtraData('balance', []);
            if ($balance) {
                $data['balance'] = abs($balance['xval']);
            }

            $package = $entry->getExtraData('package', []);
            if (!isEmptyArray($package)) {
                $data['package'] = $package;
            }

            $list[] = $data;
        }

        $result['list'] = $list;
        $result['pagesize'] = $page_size;

        return $result;
    }
}