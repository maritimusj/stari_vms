<?php
namespace zovye;

use zovye\model\agentModelObj;

//更新数据
$query = Agent::query();

/** @var agentModelObj $agent */
foreach ($query->findAll() as $agent) {
    Principal::update($agent);
}
