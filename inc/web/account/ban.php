<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $account = Account::get($id);
    if ($account) {
        if ($account->isBanned()) {
            $account->setState(Account::NORMAL);
        } else {
            $account->setState(Account::BANNED);
        }

        if ($account->save() && Account::updateAccountData()) {
            Response::toast("{$account->getTitle()}设置成功！", $this->createWebUrl('account'), 'success');
        }
    }
}

Response::toast('操作失败！', $this->createWebUrl('account'), 'error');