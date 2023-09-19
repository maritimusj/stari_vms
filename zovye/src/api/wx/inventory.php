<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\domain\Goods;
use zovye\model\inventory_goodsModelObj;
use zovye\model\inventory_logModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use function zovye\err;

class inventory
{
    public static function list(userModelObj $user): array
    {
        $inventory = \zovye\domain\Inventory::for($user);
        if (empty($inventory)) {
            return err('无法打开该用户的库存数据！');
        }

        $query = $inventory->query();

        $total = $query->count();

        $result = [
            'total' => $total,
            'list' => [],
        ];

        if ($total > 0) {
            $page = max(1, Request::int('page'));
            $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

            $query->page($page, $page_size);
            $query->orderBy('id ASC');

            /** @var inventory_goodsModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $goods = $entry->getGoods();
                if ($goods) {
                    $result['list'][] = [
                        'goods' => Goods::format($goods, true, true),
                        'num' => $entry->getNum(),
                    ];
                }
            }
        }

        return $result;
    }

    public static function logs(userModelObj $user): array
    {
        $inventory = \zovye\domain\Inventory::for($user);
        if (empty($inventory)) {
            return err('无法打开该用户的库存数据！');
        }

        $query = $inventory->logQuery();

        $total = $query->count();

        $result = [
            'total' => $total,
            'list' => [],
        ];

        if ($total > 0) {
            $page = max(1, Request::int('page'));
            $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

            $query->page($page, $page_size);
            $query->orderBy('id DESC');

            /** @var inventory_logModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'num' => $entry->getNum(),
                    'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
                $src = $entry->getSrcInventory();
                if ($src) {
                    $data['src'] = $src->format();
                }
                $goods = $entry->getGoods();
                if ($goods) {
                    $data['goods'] = Goods::format($goods, true, true);
                }
                $data['extra'] = $entry->getExtraData();
                $result['list'][] = $data;
            }
        }

        return $result;
    }
}