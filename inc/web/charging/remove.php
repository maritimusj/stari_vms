<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;

$id = Request::int('id');

$result = DBUtil::transactionDo(function () use ($id) {
    $group = Group::get($id, Group::CHARGING);
    if (empty($group)) {
        return err('找不到指定的分组！');
    }

    $name = $group->getName();

    if ($group->destroy()) {
        $result = Device::query(['group_id' => $id])->findAll();

        /** @var deviceModelObj $entry */
        foreach ($result as $entry) {
            $entry->setGroupId(0);
        }
    }

    return ChargingServ::removeGroup($name);
});

if (is_error($result)) {
    Response::itoast($result['message'], Util::url('charging'), 'error');
}

Response::itoast('已删除！', Util::url('charging'), 'success');
