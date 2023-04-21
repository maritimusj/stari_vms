<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$content = app()->fetchTemplate(
    'web/home/chart',
    [
        'chartId' => Util::random(10),
        'data' => Util::cachedCall(30, function () {
            $n = Request::int('n', 10);

            return Stats::chartDataOfAccounts($n);
        }),
    ]
);

JSON::success(['content' => $content]);