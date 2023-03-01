<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$from = Request::trim('from') ?: 'agent';
$user_id = Request::int('id');

if ($user_id) {
    $res = Util::transactionDo(
        function () use ($user_id) {
            $agent = Agent::get($user_id);
            if ($agent) {
                return Agent::remove($agent);
            }

            return err('找不到这个代理商！');
        }
    );

    if (!is_error($res)) {
        Util::itoast('已取消用户代理身份！', $this->createWebUrl($from), 'success');
    }
}

Util::itoast(empty($res['message']) ? '操作失败！' : $res['message'], $this->createWebUrl($from), 'error');