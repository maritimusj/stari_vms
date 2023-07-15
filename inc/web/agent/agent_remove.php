<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$res = DBUtil::transactionDo(function () {
    $user_id = Request::int('id');

    $agent = Agent::get($user_id);
    if ($agent) {
        return Agent::remove($agent);
    }

    return err('找不到这个代理商！');
});

$from = Request::trim('from') ?: 'agent';

if (!is_error($res)) {
    Response::toast('已取消用户代理身份！', $this->createWebUrl($from), 'success');
}

Response::toast(empty($res['message']) ? '操作失败！' : $res['message'], $this->createWebUrl($from), 'error');