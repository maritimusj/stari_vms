<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$raw = request('data');
$data = is_string($raw) ? json_decode(htmlspecialchars_decode($raw), true) : $raw;

$account = Account::get($id);
if ($account) {
    if ($account->useAccountQRCode()) {
        CtrlServ::appPublishAll($account->getAssignData(), $data);
    }
    if ($account->set('assigned', $data) && Account::updateAccountData()) {
        JSON::success('设置已经保存成功！');
    }
}

JSON::fail('保存失败！');