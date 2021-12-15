<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$op = request::op('default');

if ($op == 'default') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $device_shadow_id = request::str('device');
    if ($device_shadow_id) {
        $device = Device::findOne(['shadow_id' => $device_shadow_id]);
    }

    app()->taskPage($user, $device ?? null);

} elseif ($op == 'detail') {

    $account = Account::findOneFromUID(request::str('uid'));
    if (empty($account)) {
        JSON::fail('任务不存在！');
    }

    $data = $account->format();

    $data['detail'] = [
        [
            'title' => '任务说明',
            'desc' => $account->getConfig('desc', ''),
        ]
    ];

    JSON::success($data);
}