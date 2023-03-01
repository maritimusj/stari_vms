<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advertisingModelObj;

$title = Request::trim('title');

$extra = [
    'area' => request('area'),
    'sex' => Request::int('gender'),
    'phoneos' => Request::trim('phoneos'),
    'url' => Request::trim('url'),
    'priority' => Request::int('priority'),
];

if (empty($title)) {
    Util::itoast('请填写名称！', We7::referer(), 'error');
}

if (empty($extra['url'])) {
    Util::itoast('请填写目标网址！', We7::referer(), 'error');
}

$id = Request::int('id');
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