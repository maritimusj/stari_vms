<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$media = request('media') ?: [
    'type' => settings('misc.pushAccountMsg_type'),
    'val' => settings('misc.pushAccountMsg_val'),
];

$typename = Request::trim('typename');

$res = Util::getWe7Material($typename, request('page'), request('pagesize'));

$content = app()->fetchTemplate(
    'web/account/msg',
    [
        'typename' => $typename,
        'media' => $media,
        'list' => $res['list'],
    ]
);

JSON::success([
    'title' => $res['title'],
    'content' => $content,
]);