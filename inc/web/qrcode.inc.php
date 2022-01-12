<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\advertisingModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

$tpl_data = [
    'op' => $op,
];

if ($op == 'default') {

    $qrcode = settings('misc.qrcode', []);
    if (empty($qrcode['url'])) {

        $url = Util::shortMobileUrl('qrcode');
        $qrcode = Util::createQrcodeFile('qrcode.' . time(), $url);

        updateSettings(
            'misc.qrcode',
            [
                'url' => $url,
                'qrcode' => $qrcode,
            ]
        );
    }

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

} elseif ($op == 'edit') {

    $id = request::int('id');
    if ($id) {
        $qrcode = Advertising::get($id, Advertising::ACTIVE_QRCODE);
        if (empty($qrcode)) {
            Util::itoast('找不到这个活码！', We7::referer(), 'error');
        }

        $tpl_data['id'] = $id;
        $tpl_data['data'] = Advertising::format($qrcode);
    }

} elseif ($op == 'save') {

    $title = request::trim('title');

    $extra = [
        'area' => request('area'),
        'sex' => request::int('gender'),
        'phoneos' => request::trim('phoneos'),
        'url' => request::trim('url'),
        'priority' => request::int('priority'),
    ];

    if (empty($title)) {
        Util::itoast('请填写名称！', We7::referer(), 'error');
    }

    if (empty($extra['url'])) {
        Util::itoast('请填写目标网址！', We7::referer(), 'error');
    }

    $id = request::int('id');
    if ($id) {
        /** @var advertisingModelObj $adv */
        $adv = Advertising::findOne(['type' => Advertising::ACTIVE_QRCODE, 'id' => $id]);
        if (empty($adv)) {
            Util::itoast('找不到这个活码！', $this->createWebUrl('qrcode'), 'error');
        }

        $adv->setTitle($title);
        foreach ($extra as $key => $val) {
            $adv->setExtraData($key, $val);
        }

        if ($adv->save()) {
            Util::itoast('保存成功！', $this->createWebUrl('qrcode'), 'success');
        }
    } else {
        $data = [
            'state' => Advertising::NORMAL,
            'type' => Advertising::ACTIVE_QRCODE,
            'title' => $title,
            'extra' => serialize($extra),
        ];

        $adv = Advertising::create($data);
        if ($adv) {
            Util::itoast('创建成功！', $this->createWebUrl('qrcode'), 'success');
        }
    }

    Util::itoast('操作失败，请联系管理员！', $this->createWebUrl('qrcode'), 'error');

} elseif ($op == 'remove') {

    $id = request::int('id');
    if ($id) {
        if (Advertising::remove($id, Advertising::ACTIVE_QRCODE)) {
            Util::itoast('删除成功！', $this->createWebUrl('qrcode'), 'success');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('qrcode'), 'error');

} elseif ($op == 'ban') {

    $id = request::int('id');
    if ($id) {
        $qrcode = Advertising::get($id, Advertising::ACTIVE_QRCODE);
        if (empty($qrcode)) {
            Util::itoast('找不到这个活码！', $this->createWebUrl('qrcode'), 'error');
        }
        $qrcode->setState($qrcode->getState() == Advertising::NORMAL ? Advertising::BANNED : Advertising::NORMAL);
        if ($qrcode->save()) {
            Util::itoast('成功！', $this->createWebUrl('qrcode'), 'success');
        }
    }

    Util::itoast('失败！', $this->createWebUrl('qrcode'), 'error');
}

app()->showTemplate('web/qrcode/default', $tpl_data);
