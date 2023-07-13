<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advertisingModelObj;

$id = Request::int('id');
$type = Request::int('type');
$from_type = Request::int('from_type');

if ($id > 0 && $type > 0) {
    /** @var advertisingModelObj $adv */
    $adv = Advertising::query(['id' => $id, 'type' => $type])->findOne();
    if (empty($adv)) {
        Response::toast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $from_type]), 'error');
    }

    $assign_data = $adv->settings('assigned', []);

    if (Advertising::update($adv) && $adv->destroy()) {

        if ($adv->getType() == Advertising::SCREEN) {
            //通知设备更新屏幕广告
            Advertising::notifyAll($assign_data, []);
        }

        Response::toast('删除成功！', $this->createWebUrl('adv', ['type' => $from_type]), 'success');
    }
}

Response::toast('删除失败！', $this->createWebUrl('adv', ['type' => $from_type]), 'error');