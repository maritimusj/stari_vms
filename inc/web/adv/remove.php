<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\advertisingModelObj;

$id = request::int('id');
$type = request::int('type');
$from_type = request::int('from_type');

if ($id > 0 && $type > 0) {
    /** @var advertisingModelObj $adv */
    $adv = Advertising::query(['id' => $id, 'type' => $type])->findOne();
    if (empty($adv)) {
        Util::itoast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $from_type]), 'error');
    }

    $assign_data = $adv->settings('assigned', []);

    if (Advertising::update($adv) && $adv->destroy()) {

        if ($adv->getType() == Advertising::SCREEN) {
            //通知设备更新屏幕广告
            Advertising::notifyAll($assign_data, []);
        }

        Util::itoast('删除成功！', $this->createWebUrl('adv', ['type' => $from_type]), 'success');
    }
}

Util::itoast('删除失败！', $this->createWebUrl('adv', ['type' => $from_type]), 'error');