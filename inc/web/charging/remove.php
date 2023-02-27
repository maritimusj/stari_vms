<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\deviceModelObj;

$id = request::int('id');

$result = Util::transactionDo(function () use ($id) {
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
    Util::itoast($result['message'], Util::url('charging'), 'error');
}

Util::itoast('已删除！', Util::url('charging'), 'success');
