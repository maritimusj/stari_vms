<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$from = request::trim('from') ?: 'user';
$user_id = request::int('id');

if ($user_id) {
    $res = Util::transactionDo(function () use ($user_id) {
        $user = User::get($user_id);
        if (empty($user)) {
            return error(State::ERROR, '找不到这个用户！');
        }
        if (!$user->isPartner()) {
            return error(State::ERROR, '用户不是任何代理商的合伙人！');
        }

        $agent = $user->getPartnerAgent();
        if ($agent) {
            if ($agent->removePartner($user)) {
                return ['message' => '成功！'];
            }
        }

        return error(State::ERROR, '失败！');
    });

    if (!is_error($res)) {
        Util::itoast('已取消用户代理身份！', $this->createWebUrl($from), 'success');
    }
}

Util::itoast(empty($res['message']) ? '操作失败！' : $res['message'], $this->createWebUrl($from), 'error');