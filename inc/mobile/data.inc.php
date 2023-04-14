<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');
if ($op == 'default') {
    app()->showTemplate('misc/data', [
        'api_url' => Util::murl('app', ['op' => 'data_vw']),
    ]);
}