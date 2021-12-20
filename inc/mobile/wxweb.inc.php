<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxWebApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\request;
use zovye\Util;

Util::extraAjaxJsonData();

$op = request::op('default');

router::exec($op, [
    'login' => '\zovye\api\wxweb\api::login',
    'advs' => '\zovye\api\wxweb\api::advs',
    'accounts' => '\zovye\api\wxweb\api::accounts',
    'goods' => '\zovye\api\wxweb\api::goods',
    'get' => '\zovye\api\wxweb\api::get',
    'orderStatus' => '\zovye\api\wxweb\api::orderStatus',
]);