<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Request::extraAjaxJsonData();

$op = Request::op('default');
$payload = request('data');

if ($op == 'log') {
    app()->log($payload['level'] == 'print' ? L_INFO : L_DEBUG, 'serv', $payload['data']);
}
