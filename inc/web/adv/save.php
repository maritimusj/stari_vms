<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$type = Request::int('type', Advertising::SCREEN);

$from_type = Request::trim('from_type', $type);
$from_media = Request::str('media');

$adv = null;

if ($id > 0) {
    $adv = Advertising::get($id);
    if (empty($adv)) {
        Response::itoast(
            '找不到指定的广告！',
            $this->createWebUrl(
                'adv',
                ['type' => $from_type, 'media' => $from_media]
            ),
            'error'
        );
    }
}

$result = Advertising::createOrUpdate(null, $adv, Request::all());
if (is_error($result)) {
    Response::itoast(
        $result['message'],
        $this->createWebUrl(
            'adv',
            ['type' => $from_type, 'op' => 'edit', 'id' => $id, 'media' => $from_media]
        ),
        'error'
    );
}

Response::itoast($result['msg'], $this->createWebUrl('adv', ['op' => 'edit', 'type' => $from_type, 'media' => $from_media, 'id' => $id]), 'success');