<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advertisingModelObj;

$id = Request::int('id');

/** @var advertisingModelObj $adv */
$adv = Advertising::query(['type' => Advertising::PUSH_MSG, 'id' => $id])->findOne();
if ($adv) {
    $msg = $adv->getExtraData('msg', []);
} else {
    $msg = [];
}

$typename = Request::trim('typename');
$res = Util::getWe7Material($typename, request('page'), request('pagesize'));

Response::templateJSON(
    'web/adv/msg',
    $res['title'],
    [
        'typename' => $typename,
        'media' => $msg,
        'list' => $res['list'],
    ]
);