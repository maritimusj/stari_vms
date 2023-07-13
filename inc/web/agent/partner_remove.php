<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('agentid');
$partner_id = Request::int('partnerid');

$agent = Agent::get($agent_id);
if (empty($agent)) {
    Response::toast('找不到这个代理商！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

$res = DBUtil::transactionDo(
    function () use ($agent, $partner_id) {

        foreach (m('agent_msg')->where(We7::uniacid(['agent_id' => $partner_id]))->findAll() as $msg) {
            $msg->destroy();
        }

        if ($agent->removePartner($partner_id)) {
            return true;
        }

        return err('fail');
    }
);

if (is_error($res)) {
    Response::toast('合伙人删除失败！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

Response::toast('合伙人删除成功！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'success');