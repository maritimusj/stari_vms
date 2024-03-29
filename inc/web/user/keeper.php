<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\LoginData;
use zovye\domain\User;
use zovye\util\DBUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$result = DBUtil::transactionDo(function () use ($id) {
    $user = User::get($id);
    if (empty($user)) {
        return err('找不到这个用户！');
    }

    if (!$user->isKeeper()) {
        return err('用户不是运营人员！');
    }

    if (!$user->setKeeper(false)) {
        return err('取消身份失败！');
    }

    $keeper = $user->getKeeper();
    if ($keeper) {
        //清除原来的登录信息
        foreach (LoginData::keeper(['user_id' => $keeper->getId()])->findAll() as $entry) {
            $entry->destroy();
        }
        if (!$keeper->destroy()) {
            return err('删除数据失败！');
        }
    }

    return true;
});

if (is_error($result)) {
    Response::toast($result['message'], Util::url('user', ['principal' => 'keeper']), 'error');
}

Response::toast('取消取消运营人员成功！', Util::url('user', ['principal' => 'keeper']), 'success');