<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\util\CacheUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

Response::templateJSON(
    'web/home/chart',
    '',
    [
        'chartId' => Util::random(10),
        'data' => CacheUtil::cachedCall(30, function () {
            $n = Request::int('n', 10);

            return Stats::chartDataOfAccounts($n);
        }),
    ]
);