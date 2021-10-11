<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

$tpl_data = [
    'op' => $op,
];

if ($op == 'default' || $op == 'goods') {

    $params = [
        'page' => request::int('page'),
        'pagesize' => request::int('pagesize', 20),
    ];

    $keywords = trim(urldecode(request::trim('keywords')));
    if (!empty($keywords)) {
        $params['keywords'] = $keywords;
        $tpl_data['s_keywords'] = $keywords;
    }

    $agent_id = request::int('agentId');
    if ($agent_id > 0) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            Util::itoast('找不到这个代理商！', $this->createWebUrl('goods'), 'error');
        }
        $params['agent_id'] = $agent->getId();
        $tpl_data['s_agent'] = $agent->profile();
        $tpl_data['s_agentId'] = $agent->getId();
    } elseif ($agent_id == -1) {
        $params['agent_id'] = 0;
        $tpl_data['s_agentId'] = -1;
    }

    $result = Goods::getList($params);

    $tpl_data['goods_list'] = $result['list'];
    $tpl_data['pager'] = We7::pagination($result['total'], $result['page'], $result['pagesize']);
    $tpl_data['backer'] = $keywords || $agent_id != 0;

    if (request::is_ajax()) {
        $content = app()->fetchTemplate('web/goods/choose', $tpl_data);

        JSON::success([
            'title' => '选择商品',
            'content' => $content,
        ]);
    }
    app()->showTemplate('web/goods/default', $tpl_data);

} elseif ($op == 'search') {

    $params = [
        'page' => request::int('page'),
        'pagesize' => request::int('pagesize'),
        'keywords' => trim(urldecode(request::trim('keywords'))),
        'default_goods' => false,
    ];

    $result = Goods::getList($params);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success($result);

} elseif ($op == 'addGoods' || $op == 'editGoods') {

    $params = [];

    if ($op == 'editGoods') {
        $goods_id = request::int('id');
        $params['goods'] = Goods::data($goods_id, ['detail']);
        if (empty($params['goods'])) {
            Util::itoast('找不到这个商品！', '', 'error');
        }
        if ($params['goods']['name_original']) {
            $params['goods']['name'] = $params['goods']['name_original'];
        }
    }

    $lottery = request::str('type') == 'lottery';
    if ($lottery) {
        app()->showTemplate('web/goods/edit_lottery', $params);
    } else {
        app()->showTemplate('web/goods/edit', $params);
    }

} elseif ($op == 'saveGoods') {

    $params = [];
    parse_str(request::raw(), $params);

    if (empty($params['goodsName'])) {
        Util::itoast('商品名称不能为空！', '', 'error');
    }

    if ($params['agentId']) {
        $agent = Agent::get($params['agentId']);
        if (empty($agent)) {
            Util::itoast('找不到这个代理商！', '', 'error');
        }
    }

    if ($params['costPrice'] < 0 || $params['goodsPrice'] < 0 || $params['costPrice'] > $params['goodsPrice']) {
        Util::itoast('成本价不能高于售价！', '', 'error');
    }

    if ($params['discountPrice'] < 0 || $params['goodsPrice'] < 0 || $params['discountPrice'] > $params['goodsPrice']) {
        Util::itoast('优惠价不能高于售价！', '', 'error');
    }

    if (isset($params['goodsId'])) {
        $goods = Goods::get($params['goodsId']);
        if (empty($goods)) {
            Util::itoast('找不到这个商品！', '', 'error');
        }

        if (isset($params['goodsLaneID'])) {
            if ($params['goodsLaneID'] != $goods->getExtraData('lottery.size')) {
                $goods->setExtraData('lottery.size', intval($params['goodsLaneID']));
            }
        }

        if (isset($params['costPrice'])) {
            $goods->setExtraData('costPrice', floatval($params['costPrice'] * 100));
        }

        $goods->setExtraData('cw', empty($params['goodsCW']) ? 0 : 1);

        if (isset($params['discountPrice'])) {
            $goods->setExtraData('discountPrice', floatval($params['discountPrice'] * 100));
        }

        if ($params['agentId'] != $goods->getAgentId()) {
            $goods->setAgentId($params['agentId']);
        }

        if ($params['goodsName'] != $goods->getName()) {
            $goods->setName($params['goodsName']);
        }

        $img = trim($params['goodsImg']);
        if ($img != $goods->getImg()) {
            $goods->setImg($img);
        }

        $img = trim($params['detailImg']);
        if ($img != $goods->getDetailImg()) {
            $goods->setDetailImg($img);
        }

        if ($params['syncAll'] != $goods->getSync()) {
            $goods->setSync($params['syncAll']);
        }

        $price = intval(round($params['goodsPrice'] * 100));
        if ($price != $goods->getPrice()) {
            $goods->setPrice($price);
        }

        if ($params['goodsBalance'] != $goods->getBalance()) {
            $goods->setBalance($params['goodsBalance']);
        }

        if ($params['allowFree'] != $goods->allowFree()) {
            $goods->setAllowFree($params['allowFree']);
        }

        if ($params['allowPay'] != $goods->allowPay()) {
            $goods->setAllowPay($params['allowPay']);
        }

        if ($params['goodsUnitTitle'] != $goods->getUnitTitle()) {
            $goods->setUnitTitle($params['goodsUnitTitle']);
        }
    } else {
        $data = [
            'agent_id' => !empty($agent) ? $agent->getId() : 0,
            'name' => trim($params['goodsName']),
            'img' => trim($params['goodsImg']),
            'sync' => $params['syncAll'] ? 1 : 0,
            'price' => $params['allowPay'] ? intval(round($params['goodsPrice'] * 100)) : 0,
            'extra' => [
                'detailImg' => trim($params['detailImg']),
                'unitTitle' => trim($params['goodsUnitTitle']),
                'allowFree' => $params['allowFree'] ? 1 : 0,
                'allowPay' => $params['allowPay'] ? 1 : 0,
                'balance' => $params['allowFree'] ? intval($params['goodsBalance']) : 0,
            ],
        ];
        if (isset($params['goodsLaneID'])) {
            $data['extra']['lottery'] = [
                'size' => intval($params['goodsLaneID']),
            ];
        }

        if (isset($params['costPrice'])) {
            $data['extra']['costPrice'] = floatval($params['costPrice'] * 100);
        }

        $data['extra']['cw'] = empty($params['goodsCW']) ? 0 : 1;

        if (isset($params['discountPrice'])) {
            $data['extra']['discountPrice'] = floatval($params['discountPrice'] * 100);
        }

        $goods = Goods::create($data);
    }

    if (!empty($goods) && $goods->save()) {
        if ($params['syncAll']) {
            Job::goodsClone($goods->getId());
        }
        Util::itoast('商品保存成功！', '', 'success');
    }

    Util::itoast('商品保存失败！', '', 'error');

} elseif ($op == 'editAppendage') {

    $goods = Goods::get(request('id'));
    if (empty($goods)) {
        JSON::fail('找不到这个商品！');
    }

    $content = app()->fetchTemplate(
        'web/goods/appendage',
        [
            'goods' => Goods::format($goods),
            'appendage' => $goods->getAppendage(),
        ]
    );

    JSON::success([
        'title' => "附加信息",
        'content' => $content,
    ]);

} elseif ($op == 'saveAppendage') {

    $params = [];
    parse_str(request('params'), $params);

    $goods = Goods::get($params['goodsId']);
    if (empty($goods)) {
        JSON::fail('找不到这个商品！');
    }

    $data = [
        'mfrs' => trim($params['mfrs']),
        'tel' => trim($params['tel']),
        'lot' => trim($params['lot']),
        'spec' => trim($params ['spec']),
        'exp' => trim($params['exp']),
    ];

    $goods->setAppendage($data);
    $goods->save();

    JSON::success('保存成功！');

} elseif ($op == 'removeGoods') {

    $goods = Goods::get(request('id'));
    if ($goods) {
        if (InventoryGoods::exists(['goods_id' => $goods->getId()])) {
            $goods->delete();
            $goods->save();
        } else {
           $goods->destroy();
        }
        JSON::success('商品删除成功！');
    }

    JSON::fail('商品删除失败！');

} elseif ($op == 'viewGoodsStats') {

    $goods = Goods::get(request::int('id'));
    if (empty($goods)) {
        JSON::fail('找不到这个商品！');
    }

    $title = date('n月d日');
    $data = Stats::chartDataOfDay($goods, time(), "商品：{$goods->getName()}({$title})");

    $content = app()->fetchTemplate(
        'web/goods/stats',
        [
            'chartid' => Util::random(10),
            'title' => $title,
            'chart' => $data,
        ]
    );

    JSON::success(['z' => date('z'), 'content' => $content]);
}