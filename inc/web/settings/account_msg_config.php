<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\Helper;

defined('IN_IA') or exit('Access Denied');

$media = request('media') ?: [
    'type' => settings('misc.pushAccountMsg_type'),
    'val' => settings('misc.pushAccountMsg_val'),
];

$typename = Request::trim('typename');

$res = Helper::getWe7Material($typename, Request::int('page'), Request::int('pagesize'));

Response::templateJSON(
    'web/account/msg',
    $res['title'],
    [
        'typename' => $typename,
        'media' => $media,
        'list' => $res['list'],
    ]
);