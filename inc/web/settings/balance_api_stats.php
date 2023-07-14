<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

Response::templateJSON('web/common/stats',
    '接口积分统计',
    [
    'chartId' => Util::random(10),
    'title' => '',
    'chart' => Stats::getBalanceApiStats(),
]);