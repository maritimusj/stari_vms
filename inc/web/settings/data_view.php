<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data['navs'] = Util::getSettingsNavs();
$tpl_data['navs']['data_view'] = '数据大屏';

$goods = [
    'g1' => '商品一',
    'g2' => '商品二',
    'g3' => '商品三',
    'g4' => '商品四',
    'g5' => '商品五',
    'g6' => '商品六',
    'g7' => '商品七',
    'g8' => '商品八',
    'g9' => '商品九',
    'g10' => '商品十',
];

$provinces = Util::getProvinceList();

$tpl_data['goods'] = $goods;
$tpl_data['provinces'] = $provinces;

$keys = [
    'title',
    'total_sale_init',
    'total_sale_freq',
    'total_sale_section1',
    'total_sale_section2',
    'today_sale_init',
    'today_sale_freq',
    'today_sale_section1',
    'today_sale_section2',
    'total_order_init',
    'total_order_freq',
    'total_order_section1',
    'total_order_section2',
    'today_order_init',
    'today_order_freq',
    'today_order_section1',
    'today_order_section2',
    'user_man',
    'user_woman',
    'income_wx',
    'income_ali',
];

$keys = array_merge($keys, array_keys($goods), array_keys($provinces));

$values = [];
$diff = [];

$res = m('data_view')->findAll();

foreach ($res as $item) {
    if (in_array($item->getK(), $keys)) {
        $values[$item->getK()] = $item->getV();
        $diff[] = $item->getK();
    }
}

$left_keys = array_diff($keys, $diff);
/** @var string $key */
foreach ($left_keys as $key) {
    $values[$key] = '';
}

$tpl_data = array_merge($tpl_data, $values);

$dm = Util::murl('app', ['op' => 'data_view']);

$tpl_data['dm'] = $dm;

$tpl_data['op'] = 'data_view';
$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/data_view', $tpl_data);