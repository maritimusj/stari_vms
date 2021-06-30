<?php
namespace zovye;

use RuntimeException;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = Inventory::query();

    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'title LIKE' => "%{$keywords}%",
        ]);
    }

    $total = $query->count();
    $inventories = [
        'page' => 0,
        'total' => 0,
        'totalpage' => 0,
        'list' => [],
    ];

    $pager = '';

    if ($total > 0) {
        $total_page = ceil($total / $page_size);
        if ($page > $total_page) {
            $page = 1;
        }

        $pager = We7::pagination($total, $page, $page_size);

        $inventories['total'] = $total;
        $inventories['page'] = $page;
        $inventories['totalpage'] = $total_page;        

        $query->orderBy('id DESC');
        foreach($query->findAll() as $entry) {
            $inventories['list'][] = $entry->format();
        }
    }

    if (request::is_ajax()) {
        $content = app()->fetchTemplate(
            'web/inventory/choose',
            [
                'pager' => $pager,
                's_keywords' => $keywords,
                'list' => $inventories['list'],
            ]
        );
    
        JSON::success(['title' => "库存列表", 'content' => $content]);
    }

    app()->showTemplate('web/inventory/default', [
        'op' => $op,
        'pager' => $pager,
        'inventories' => $inventories,
    ]);

} elseif ($op == 'search') {
    $query = Inventory::query();
    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'title LIKE' => "%{$keywords}%",
        ]);
    }

    $query->limit(100)->orderBy('id DESC');
    $result = [];
    foreach($query->findAll() as $entry) {
        $result[] = $entry->format();
    }

    JSON::success($result);

} elseif ($op == 'add' || $op == 'edit') {

    $tpl_data = [
        'op' => $op,
    ];

    if ($op == 'edit') {
        $inventory = Inventory::get(request::int('id'));
        if (empty($inventory)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $tpl_data['inventory'] = $inventory;
    }

    app()->showTemplate('web/inventory/edit', $tpl_data);

} elseif ($op == 'save') {

    $id = request::int('id');
    if ($id > 0) {
        $inventory = Inventory::get($id);
        if (empty($inventory)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $inventory->setTitle(request::trim('title'));
        $inventory->save();
        Util::itoast('保存成功！', '', 'success');
    }

    $data = [];
    $data['title'] = request::trim('title');

    $user_id = request::int('userId');
    if ($user_id > 0) {
        $user = User::get($user_id);
        if (empty($user)) {
            Util::itoast('找不到这个用户！', '', 'error');
        }
        $uid = Inventory::getUID($user);
        if (Inventory::exists($uid)) {
            Util::itoast('仓库已经存在！', '', 'error');
        }
        $data['uid'] = $uid;
        $data['extra'] = [
            'user' => $user->profile(),
        ];
    }

    $parent_inventory_id = request::int('parentId');
    if ($parent_inventory_id > 0) {
        $parent_inventory = Inventory::get($parent_inventory_id);
        if (empty($parent_inventory)) {
            Util::itoast('找不到指定的仓库！', '', 'error');
        }
        $data['parent_id'] = $parent_inventory->getId();
    }

    $inventory = Inventory::create($data);
    if ($inventory) {
        Util::itoast('创建成功！', '', 'success');
    }

    Util::itoast('创建失败！', '', 'error');

} elseif ($op == 'getUserInventory') {

    $user_id = request::int('user_id');
    $user = User::get($user_id);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }
    $inventory = Inventory::find($user);
    if (empty($inventory)) {
        JSON::fail('找不到指定的仓库！');
    }
    JSON::success([
        'id' => $inventory->getId(),
        'uid' => $inventory->getUid(),
        'title' => $inventory->getTitle(),
        'createtime' => $inventory->getCreatetime(),
        'createtime_formatted' => date('Y-m-d H:i:s', $inventory->getCreatetime()),
    ]);

} elseif ($op == 'detail') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $inventory = Inventory::for($user);
    if (empty($inventory)) {
        JSON::fail('无法打开该用户的库存数据！');
    }

    $tpl_data = [
        'title' => $inventory->getTitle(),
        'user' => $user->getId(),
        'id' => $inventory->getId(),
    ];

    $query = $inventory->query();

    if (request::has('agentId')) {
        $agent = Agent::get(request::int('agentId'));
        if (empty($agent)) {
            JSON::fail('找不到这个代理商！');
        }
        $query->where(['agent_id' => $agent->getId()]);
    }

   //搜索关键字
   $keywords = request::trim('keywords');
   if ($keywords) {
       $query->whereOr([
           'name LIKE' => "%{$keywords}%",
       ]);
   }

   $total = $query->count();
   $list = [];

   if ($total > 0) {
        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

        $total_page = ceil($total / $page_size);
        if ($page > $total_page) {
            $page = 1;
        }
        
        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);     
        
        $query->page($page, $page_size);
        $query->orderBy('id ASC');

        foreach($query->findAll() as $entry) {
            $goods = $entry->getGoods();
            if ($goods) {
                    $list[] = [
                        'goods' => Goods::format($goods, true, true),
                        'num' => $entry->getNum(),
                    ];
            }
        }        
   }

   $tpl_data['list'] = $list;

   if (request::is_ajax()) {
        $content = app()->fetchTemplate('web/inventory/choose', [
            'list' => $list,
            'pager' => $tpl_data['pager'],
            'backer' => $keywords ? true : false,
        ]);

        JSON::success([
            'title' => '选择商品',
            'content' => $content,
        ]);
   }

   app()->showTemplate('web/inventory/detail', $tpl_data);

} elseif ($op == 'stockOut') {

    $tpl_data = [];
    app()->showTemplate('web/inventory/stock_out', $tpl_data);

} elseif ($op == 'stockIn') {

    $id = request::int('id');

    $inventory = Inventory::get(request::int('id'));
    if (empty($inventory)) {
        Util::itoast('找不到这个仓库！', '', 'error');
    }

    $tpl_data = [
        'id' => $id,
        'user' => request::int('user'),
        'title' => $inventory->getTitle(),
    ];
    app()->showTemplate('web/inventory/stock_in', $tpl_data);

} elseif ($op == 'saveStockIn') {

    $result = Util::transactionDo(function () {
        $user = User::get(request::int('userid'));
        if (empty($user)) {
            throw new RuntimeException('找不到这个用户！');
        }

        $inventory = Inventory::for($user);
        if (empty($inventory)) {
            throw new RuntimeException('找不到用户的仓库！');
        }

        $user_ids = request::array('user');
        $goods_ids = request::array('goods');
        $num_arr = request::array('num');

        $logs = [];

        foreach ($goods_ids as $index => $goods_id) {
            if (empty($goods_id)) {
                continue;
            }
            $goods = Goods::get($goods_id);
            if (empty($goods)) {
                throw new RuntimeException('找不到这个商品！');
            }
            $num = isset($num_arr[$index]) ? intval($num_arr[$index]) : 0;
            if ($num <= 0) {
                continue;
            }

            $src_inventory = null;
            $user_id = isset($user_ids[$index]) ? intval($user_ids[$index]) : 0;
            if (!empty($user_id)) {
                $from = User::get($user_id);
                if (empty($from)) {
                    throw new RuntimeException('找不到源用户！');
                }
                $src_inventory = Inventory::for($from);
                if (empty($src_inventory)) {
                    throw new RuntimeException('找不到源用户仓库！');
                }
            }
            $log = $inventory->stock($src_inventory, $goods, $num, [
                'memo' => '管理员后台入库',
                'serial' => REQUEST_ID,
            ]);
            if (!$log) {
                throw new RuntimeException('入库失败！');
            }
            $logs[] = $log;
        }

        return $logs;
    });

    if (is_error($result)) {
        JSON::fail($result['message']);
    }

    if (empty($result)) {
        JSON::fail('没有指定商品或者商品数量！');
    }
    JSON::success('入库成功！');
}