<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::extraAjaxJsonData();

$op = request::op('default');
$payload = request('data');

if ($op == 'log') {
    app()->log($payload['level'] == 'print' ? L_INFO : L_DEBUG, 'serv', $payload['data']);
}
