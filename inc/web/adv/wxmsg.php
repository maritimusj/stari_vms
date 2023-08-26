<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advertisingModelObj;

$id = Request::int('id');

/** @var advertisingModelObj $ad */
$ad = Advertising::query(['type' => Advertising::PUSH_MSG, 'id' => $id])->findOne();
if ($ad) {
    $msg = $ad->getExtraData('msg', []);
} else {
    $msg = [];
}

$typename = Request::trim('typename');
$res = Helper::getWe7Material($typename, Request::int('page'), Request::int('pagesize'));

Response::templateJSON(
    'web/adv/msg',
    $res['title'],
    [
        'typename' => $typename,
        'media' => $msg,
        'list' => $res['list'],
    ]
);