<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$id = request::int('id');
if ($id) {
    $account = Account::get($id);
    if ($account) {
        if ($account->isBanned()) {
            $account->setState(Account::NORMAL);
        } else {
            $account->setState(Account::BANNED);
        }

        if ($account->save() && Account::updateAccountData()) {
            Util::itoast("{$account->getTitle()}设置成功！", $this->createWebUrl('account'), 'success');
        }
    }
}

Util::itoast('操作失败！', $this->createWebUrl('account'), 'error');