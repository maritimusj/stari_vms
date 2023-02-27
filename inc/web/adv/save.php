<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$type = request::int('type', Advertising::SCREEN);

$from_type = request::trim('from_type', $type);
$from_media = request::str('media');

$adv = null;

if ($id > 0) {
    $adv = Advertising::get($id);
    if (empty($adv)) {
        Util::itoast(
            '找不到指定的广告！',
            $this->createWebUrl(
                'adv',
                ['type' => $from_type, 'media' => $from_media]
            ),
            'error'
        );
    }
}

$result = Advertising::createOrUpdate(null, $adv, request::all());
if (is_error($result)) {
    Util::itoast(
        $result['message'],
        $this->createWebUrl(
            'adv',
            ['type' => $from_type, 'op' => 'edit', 'id' => $id, 'media' => $from_media]
        ),
        'error'
    );
}

Util::itoast($result['msg'], $this->createWebUrl('adv', ['op' => 'edit', 'type' => $adv->getType(), 'id' => $id]), 'success');