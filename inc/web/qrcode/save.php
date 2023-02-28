<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\advertisingModelObj;

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