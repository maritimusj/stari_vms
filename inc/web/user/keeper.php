<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$result = Util::transactionDo(function () use ($id) {
    $user = User::get($id);
    if (empty($user)) {
        return error(State::ERROR, '找不到这个用户！');
    }

    if (!$user->isKeeper()) {
        return error(State::ERROR, '用户不是运营人员！');
    }

    if (!$user->setKeeper(false)) {
        return error(State::ERROR, '取消身份失败！');
    }

    $keeper = $user->getKeeper();
    if ($keeper) {
        //清除原来的登录信息
        foreach (LoginData::keeper(['user_id' => $keeper->getId()])->findAll() as $entry) {
            $entry->destroy();
        }
        if (!$keeper->destroy()) {
            return error(State::ERROR, '删除数据失败！');
        }
    }

    return true;
});

if (is_error($result)) {
    Util::itoast($result['message'], $this->createWebUrl('user', ['principal' => 'keeper']), 'error');
}

Util::itoast('取消取消运营人员成功！', $this->createWebUrl('user', ['principal' => 'keeper']), 'success');