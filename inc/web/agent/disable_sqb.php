<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$agent = Agent::get(request::int('id'));
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

if ($agent->updateSettings('agentData.pay.SQB', [])) {
    JSON::success('成功！');
}

JSON::fail('失败！');