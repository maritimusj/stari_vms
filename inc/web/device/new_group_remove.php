<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Device;
use zovye\domain\Group;
use zovye\model\deviceModelObj;
use zovye\util\Util;

$id = Request::int('id');
$group = Group::get($id);

if ($group && $group->destroy()) {
    $result = Device::query(['group_id' => $id])->findAll();

    /** @var deviceModelObj $entry */
    foreach ($result as $entry) {
        $entry->setGroupId(0);
        //更新广告
        $entry->updateScreenAdsData();
        //更新公众号
        $entry->updateAccountData();
    }

    Response::toast('删除成功！', Util::url('device', ['op' => 'new_group']), 'success');
}

Response::toast('删除失败！', Util::url('device', ['op' => 'new_group']), 'error');