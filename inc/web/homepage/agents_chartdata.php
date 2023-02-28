<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$content = app()->fetchTemplate(
    'web/home/chart',
    [
        'chartid' => Util::random(10),
        'data' => Util::cachedCall(30, function () {
            $n = Request::int('n', 10);

            return Stats::chartDataOfAgents($n);
        }),
    ]
);

JSON::success(['content' => $content]);