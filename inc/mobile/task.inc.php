<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$op = request::op('default');

if ($op == 'default') {
    $device_shadow_id = request::str('device');
    if ($device_shadow_id) {
        $device = Device::findOne(['shadow_id' => $device_shadow_id]);
    }

    $user = Util::getCurrentUser();
    
    app()->taskPage($user, $device);

} elseif ($op == 'detail') {

    $account = Account::findOneFromUID(request::str('uid'));
    if (empty($account)) {
        JSON::fail('任务不存在！');
    }

    $data = $account->format();
    $config = $account->getConfig();

    $data['detail'] = [
        [
            'title' => '任务说明',
            'desc' => $entry->getConfig('desc', ''),
        ],               
    ];

    json::success($data);
}