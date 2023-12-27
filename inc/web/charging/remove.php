<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\business\ChargingServ;
use zovye\domain\Device;
use zovye\domain\Group;
use zovye\model\deviceModelObj;
use zovye\util\DBUtil;
use zovye\util\Util;

$id = Request::int('id');

$result = DBUtil::transactionDo(function () use ($id) {
    $group = Group::get($id, Group::CHARGING);
    if (empty($group)) {
        return err('找不到指定的分组！');
    }

    $name = $group->getName();

    if ($group->destroy()) {
        $result = Device::query(['group_id' => $id])->findAll();

        /** @var deviceModelObj $device */
        foreach ($result as $device) {
            $device->setGroupId(0);
        }
    }

    return ChargingServ::removeGroup($name);
});

if (is_error($result)) {
    Response::toast($result['message'], Util::url('charging'), 'error');
}

Response::toast('已删除！', Util::url('charging'), 'success');
