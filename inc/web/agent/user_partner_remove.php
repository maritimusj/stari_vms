<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\User;
use zovye\util\DBUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$from = Request::trim('from') ?: 'user';
$user_id = Request::int('id');

$res = DBUtil::transactionDo(function () use ($user_id) {
    $user = User::get($user_id);
    if (empty($user)) {
        return err('找不到这个用户！');
    }
    if (!$user->isPartner()) {
        return err('用户不是任何代理商的合伙人！');
    }

    $agent = $user->getPartnerAgent();
    if ($agent) {
        if ($agent->removePartner($user)) {
            return ['message' => '成功！'];
        }
    }

    return err('失败！');
});

if (!is_error($res)) {
    Response::toast('已取消用户代理身份！', Util::url($from), 'success');
}

Response::toast(empty($res['message']) ? '操作失败！' : $res['message'], Util::url($from), 'error');