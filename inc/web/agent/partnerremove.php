<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$agent_id = request::int('agentid');
$partner_id = request::int('partnerid');

$agent = Agent::get($agent_id);
if (empty($agent)) {
    Util::itoast('找不到这个代理商！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

$res = Util::transactionDo(
    function () use ($agent, $partner_id) {

        foreach (m('agent_msg')->where(We7::uniacid(['agent_id' => $partner_id]))->findAll() as $msg) {
            $msg->destroy();
        }

        if ($agent->removePartner($partner_id)) {
            return true;
        }

        return error(State::ERROR, 'fail');
    }
);

if (is_error($res)) {
    Util::itoast('合伙人删除失败！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

Util::itoast('合伙人删除成功！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'success');