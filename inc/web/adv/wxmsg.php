<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\advertisingModelObj;

$id = request::int('id');

/** @var advertisingModelObj $adv */
$adv = Advertising::query(['type' => Advertising::PUSH_MSG, 'id' => $id])->findOne();
if ($adv) {
    $msg = $adv->getExtraData('msg', []);
} else {
    $msg = [];
}

$typename = request::trim('typename');
$res = Util::getWe7Material($typename, request('page'), request('pagesize'));

$content = app()->fetchTemplate(
    'web/adv/msg',
    [
        'typename' => $typename,
        'media' => $msg,
        'list' => $res['list'],
    ]
);

JSON::success([
    'title' => $res['title'],
    'content' => $content,
]);