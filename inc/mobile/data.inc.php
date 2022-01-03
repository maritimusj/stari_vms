<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$op = request::op('default');
if ($op == 'default') {
    app()->showTemplate('misc/data', [
        'api_url' => Util::murl('app', ['op' => 'data_view']),
    ]);
}