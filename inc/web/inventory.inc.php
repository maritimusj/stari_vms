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

} elseif ($op == 'stockLog') {

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

    $query = $inventory->logQuery();
    
    if (request::isset('src')) {
        $query->where(['src_inventory_id' => request::int('src')]);
    }

    if (request::has('goods')) {
        $query->where(['goods_id' => request::int('goods')]);
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
        $query->orderBy('id DESC');

        foreach($query->findAll() as $entry) {
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
            $data['memo'] = $entry->getExtraData('memo');
            $data['clr'] = $entry->getExtraData('clr');
            $list[] = $data;
        }
    }

    $tpl_data['list'] = $list;

    app()->showTemplate('web/inventory/log', $tpl_data);

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

    $user = User::get(request::int('userid'));
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $inventory = Inventory::for($user);
    if (empty($inventory)) {
        JSON::fail('找不到用户的仓库！');
    }

    if (!$inventory->acquireLocker()) {
        JSON::fail('锁定仓库失败！');
    }

    $result = Util::transactionDo(function () use ($inventory) {

        $user_ids = request::array('user');
        $goods_ids = request::array('goods');
        $num_arr = request::array('num');

        $logs = [];

        $clr = Util::randColor();
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
                if (!$src_inventory->acquireLocker()) {
                    throw new RuntimeException('锁定源仓库失败！');
                }
            }

            $log = $inventory->stock($src_inventory, $goods, $num, [
                'memo' => '管理员后台入库',
                'clr' => $clr,
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

} elseif ($op == 'editGoods') {

    $inventory = Inventory::get(request::int('id'));
    if (empty($inventory)) {
        JSON::fail('找不到这个仓库！');
    }

    $res = $inventory->query(['goods_id' => request::int('goods')])->findOne();
    if (empty($res)) {
        JSON::fail('找不到这个商品库存！');
    }

    $goods = $res->getGoods();
    if (empty($res)) {
        JSON::fail('找不到这个商品！');
    }

    $content = app()->fetchTemplate('web/inventory/edit_goods', [
        'title' => $inventory->getTitle(),
        'num' => $res->getNum(),
        'goods' => Goods::format($goods, false, true),
    ]);

    JSON::success([
        'title' => '编辑库存商品数量',
        'content' => $content,
    ]);

} elseif ($op == 'saveGoodsNum') {

    $inventory = Inventory::get(request::int('id'));
    if (empty($inventory)) {
        JSON::fail('找不到这个仓库！');
    }

    $goods = Goods::get(request::int('goods'));
    if (empty($goods)) {
        JSON::fail('找不到这个商品！');
    }

    $num = request::int('num');

    if (!$inventory->acquireLocker()) {
        JSON::fail('锁定仓库失败！');
    }

    $result = Util::transactionDo(function () use ($inventory, $goods, $num) {
        $clr = Util::randColor();

        $inventory_goods = $inventory->query(['goods_id' => $goods->getId()])->findOne();
        if (!empty($inventory_goods)) {
            $num = $num - $inventory_goods->getNum();
        }

        $log = $inventory->stock(null, $goods, $num, [
            'memo' => '管理员编辑商品库存',
            'clr' => $clr,
            'serial' => REQUEST_ID,
        ]); 

        if (!$log) {
            throw new RuntimeException('入库失败！');
        }
        return $num;        
    });

    if (is_error($result)) {
        JSON::fail($result['message']);
    }

    JSON::success([
        'msg' => '库存保存成功！',
        'num' => $result > 0 ? "+{$result}" : $result,
    ]);

} elseif ($op == 'removeGoods') {

    $inventory = Inventory::get(request::int('id'));
    if (empty($inventory)) {
        JSON::fail('找不到这个仓库！');
    }

    $goods = $inventory->query(['goods_id' => request::int('goods')])->findOne();
    if (empty($goods)) {
        JSON::fail('找不到这个商品！');
    }

    if (!$inventory->acquireLocker()) {
        JSON::fail('锁定仓库失败！');
    }

    $result = Util::transactionDo(function () use ($inventory, $goods) {
        $clr = Util::randColor();

        if ($goods->getNum() > 0) {
            $log = $inventory->stock(null, $goods->getGoods(), 0 - $goods->getNum(), [
                'memo' => '管理员删除商品库存',
                'clr' => $clr,
                'serial' => REQUEST_ID,
            ]); 

            if (!$log) {
                throw new RuntimeException('保存库存失败！');
            }            
        }

        $goods->destroy();

        return true;        
    });

    if (is_error($result)) {
        JSON::fail($result['message']);
    }

    JSON::success('库存商品已删除！');
}