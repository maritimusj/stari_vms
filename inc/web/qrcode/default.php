<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\advertisingModelObj;

$qrcode = settings('misc.qrcode', []);
if (empty($qrcode['url'])) {

    $url = Util::shortMobileUrl('qrcode');
    $qrcode = Util::createQrcodeFile('qrcode.'.time(), $url);

    updateSettings(
        'misc.qrcode',
        [
            'url' => $url,
            'qrcode' => $qrcode,
        ]
    );
}

$tpl_data = [];

$tpl_data['config'] = $qrcode;

$page = max(1, request::int('page'));
$page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Advertising::query(['type' => Advertising::ACTIVE_QRCODE, 'state !=' => Advertising::DELETED]);

$total = $query->count();

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id desc');

$sex_title = [
    User::UNKNOWN => '不限',
    User::MALE => '男',
    User::FEMALE => '女',
];

$qr_codes = [];
/** @var  advertisingModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = Advertising::format($entry);
    $data['sex_formatted'] = $sex_title[$data['extra']['sex']];
    $data['area_formatted'] = implode(' ', $data['extra']['area']);
    $qr_codes[] = $data;
}

$tpl_data['qrcodes'] = $qr_codes;

app()->showTemplate('web/qrcode/default', $tpl_data);