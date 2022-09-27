<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if ($op == 'default') {
    app()->showTemplate('misc/data', [
        'api_url' => Util::murl('app', ['op' => 'data_view']),
    ]);
}