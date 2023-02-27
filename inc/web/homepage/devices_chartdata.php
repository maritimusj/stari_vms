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
            $n = request::int('n', 10);

            return Stats::chartDataOfDevices($n);
        }),
    ]
);

JSON::success(['content' => $content]);