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
            'desc' => html_entity_decode($account->getConfig('desc', '')) ,
        ],
    ];

    JSON::success($data);

} elseif ($op == 'submit') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $uid = request::str("uid");
    $account = Account::findOneFromUID($uid);
    if (empty($account)) {
        JSON::fail('找不到这个公任务！');
    }

    if ($account->getBonusType() != Account::BALANCE || $account->getBalancePrice() == 0) {
        JSON::fail('任务未设置积分奖励！');
    }

    $data = request::array('data');
    
    if (Task::createLog($user, $account, $data)) {
         JSON::success('提交成功！');
    }

    JSON::fail('保存记录失败！');
}