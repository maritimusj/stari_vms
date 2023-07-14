<?php

namespace zovye;

use zovye\model\agentModelObj;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_gsp_user')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_gsp_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `uid` varchar(64) NOT NULL,
  `val_type` varchar(16) NOT NULL DEFAULT 'percent',
  `val` int(11) NOT NULL DEFAULT '0',
  `order_types` varchar(6) NOT NULL DEFAULT '',
  `createtime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    Migrate::execSQL($sql);

    //更新原来的设置
    $query = Agent::query();

    /** @var agentModelObj $agent */
    foreach ($query->findAll() as $agent) {
        $mode = $agent->getGSPMode();
        if ($mode == GSP::REL) {
            $data = $agent->settings('agentData.gsp.rel', []);
            if ($data) {
                $data['level1'] = intval(round(floatval($data['level1']) * 100));
                $data['level2'] = intval(round(floatval($data['level2']) * 100));
                $data['level3'] = intval(round(floatval($data['level3']) * 100));
                $agent->updateSettings('agentData.gsp.rel', $data);
            }
        } elseif ($mode == GSP::FREE) {
            $gsp_users = $agent->settings('agentData.gsp.users', []);
            foreach ($gsp_users as &$data) {
                $data['percent'] = intval(round(floatval($data['percent']) * 100));
                $data['amount'] = intval(round(floatval($data['amount']) * 100));
            }
            $agent->updateSettings('agentData.gsp.users', $gsp_users);
        }
    }
}
