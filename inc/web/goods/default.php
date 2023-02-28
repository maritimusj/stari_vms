<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$params = [
    'page' => request::int('page'),
    'pagesize' => request::int('pagesize', 20),
];

$keywords = request::trim('keywords', '', true);
if (!empty($keywords)) {
    $params['keywords'] = $keywords;
    $tpl_data['s_keywords'] = $keywords;
}

$w = request::str('w', 'all');
if ($w == 'pay') {
    $params[] = Goods::AllowPay;
}
if ($w == 'free') {
    $params[] = Goods::AllowFree;
}
if ($w == 'exchange') {
    $params[] = Goods::AllowBalance;
}
if ($w == 'mall') {
    $params[] = Goods::AllowDelivery;
}

$tpl_data['w'] = $w;
$tpl_data['types'] = request::array('types', []);

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

$tpl_data['navs'] = [
    'all' => '全部',
    'free' => '免费',
    'pay' => '支付',
];

if (App::isBalanceEnabled()) {
    $tpl_data['navs']['exchange'] = '积分';
    $tpl_data['navs']['mall'] = '商城';
}

app()->showTemplate('web/goods/default', $tpl_data);