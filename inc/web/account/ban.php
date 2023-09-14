<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$account = Account::get($id);

if ($account) {
    if ($account->isBanned()) {
        $account->setState(Account::NORMAL);
    } else {
        $account->setState(Account::BANNED);
    }

    if ($account->save() && Account::updateAccountData()) {
        Response::toast("{$account->getTitle()}设置成功！", Util::url('account', ['type' => Request::int('from')]), 'success');
    }
}

Response::toast('操作失败！', Util::url('account', ['type' => Request::int('from')]), 'error');