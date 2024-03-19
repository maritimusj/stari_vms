<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\CloudFIAccount;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\util\TemplateUtil;

if (Request::op() == 'get') {
    $user = Session::getCurrentUser();
    if (empty($user)) {
        Response::alert('请使用微信扫打开链接！');
    }

    $ticket_data = $user->getLastActiveData('ticket', []);
    if (empty($ticket_data)) {
        Response::alert('请重新扫描设备二维码！');
    }

    $account =Account::findOneFromType(Account::CloudFI);
    if (empty($account)) {
        Response::alert('找不到这个公众号！');
    }

    $device = Device::get($ticket_data['deviceId']);
    if (empty($device)) {
        Response::alert('找不到这个设备！');
    }

    $tpl_data = TemplateUtil::getTplData([
        $user,
        $account,
        $device,
        [
            'timeout' => App::getDeviceWaitTimeout(),
            'user.ticket' => $ticket_data['id'],
        ],
        'misc' => [
            'wx_app.username' => settings('agentWxapp.username', ''),
        ],
    ]);

    //领取页面
    Response::getPage($tpl_data);
}

Log::debug('cloudFI', [
    'raw' => Request::raw(),
]);

if (App::isCloudFIEnabled()) {
    CloudFIAccount::cb(Request::json());
}

exit(REQUEST_ID);