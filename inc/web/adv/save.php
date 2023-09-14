<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Advertising;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$type = Request::int('type', Advertising::SCREEN);

$from_type = Request::trim('from_type', $type);
$from_media = Request::str('media');

$ad = null;

if ($id > 0) {
    $ad = Advertising::get($id);
    if (empty($ad)) {
        Response::toast(
            '找不到指定的广告！',
            Util::url(
                'adv',
                ['type' => $from_type, 'media' => $from_media]
            ),
            'error'
        );
    }
}

$result = Advertising::createOrUpdate(null, $ad, Request::all());
if (is_error($result)) {
    Response::toast(
        $result['message'],
        Util::url(
            'adv',
            ['type' => $from_type, 'op' => 'edit', 'id' => $id, 'media' => $from_media]
        ),
        'error'
    );
}

Response::toast($result['msg'], Util::url('adv', ['op' => 'edit', 'type' => $from_type, 'media' => $from_media, 'id' => $id]), 'success');