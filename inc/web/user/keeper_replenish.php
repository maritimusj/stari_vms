<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

//补货记录
use zovye\domain\Goods;
use zovye\domain\Replenish;
use zovye\domain\User;
use zovye\model\goodsModelObj;
use zovye\model\replenishModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);
$pager = '';

$user = User::get(Request::int('id'));
$reps = [];
$goods_assoc = [];
if ($user->isKeeper()) {
    $keeper = $user->getKeeper();
    if ($keeper) {

        $query = Replenish::query(['keeper_id' => $keeper->getId()]);
        $total = $query->count();

        $goods_arr = [];
        if ($total > 0) {
            $pager = We7::pagination($total, $page, $page_size);
            $query->orderBy('createtime DESC');
            $query->page($page, $page_size);

            $replenish_res = $query->findAll();
            /** @var replenishModelObj $item */
            foreach ($replenish_res as $item) {
                $data = [
                    'num' => $item->getNum(),
                    'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
                    'goods_id' => $item->getGoodsId(),
                ];
                $d_data = json_decode($item->getExtra());
                $d_name = $d_data->device->name ?? '';
                $goods_arr[] = $item->getGoodsId();
                $data['device_name'] = $d_name;

                $reps[] = $data;
            }

            if (!empty($goods_arr)) {
                $goods_arr = array_unique($goods_arr);
                $goods_res = Goods::query()->where('id IN ('.implode(',', $goods_arr).')')->findAll();
                /** @var goodsModelObj $item */
                foreach ($goods_res as $item) {
                    $goods_assoc[$item->getId()] = [
                        'name' => $item->getName(),
                    ];
                }
            }

        }
    }
}

Response::templateJSON(
    'web/user/keeper_replenish',
    '',
    [
        'goods_assoc' => $goods_assoc,
        'reps' => $reps,
        'pager' => $pager,
    ]
);