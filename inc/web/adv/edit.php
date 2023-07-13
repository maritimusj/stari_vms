<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advertisingModelObj;

$id = Request::int('id');
$type = Request::int('type', Advertising::SCREEN);

$tpl_data = [
    'op' => Request::op(),
    'type' => $type,
    'media_data' => Advertising::getMediaData(),
    'wx_data' => Advertising::getWxData(),
];

$tpl_data['navs'] = Advertising::getNavData();

$tpl_data['id'] = $id;
$tpl_data['media'] = Request::trim('media');
$tpl_data['from_type'] = Request::trim('from_type', $type);

if ($id > 0) {
    /** @var advertisingModelObj $adv */
    $adv = Advertising::query(['type' => $type, 'id' => $id])->findOne();
    if (empty($adv)) {
        Response::itoast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $type]), 'error');
    }

    $tpl_data['state'] = $adv->getState();
    $tpl_data['title'] = $adv->getTitle();

    if ($adv->getAgentId()) {
        $agent = Agent::get($adv->getAgentId());
        if (empty($agent)) {
            Response::itoast('找不到这个广告所属的代理商！', $this->createWebUrl('adv', ['type' => $type]), 'error');
        }
    }

    if ($type == Advertising::SCREEN) {
        $media = $adv->getExtraData('media');
        if ($media == 'srt') {

            $tpl_data['text'] = $adv->getExtraData('text');
            $tpl_data['size'] = $adv->getExtraData('size');
            $tpl_data['clr'] = $adv->getExtraData('clr');
            $tpl_data['background'] = $adv->getExtraData('background-clr');
            $tpl_data['speed'] = $adv->getExtraData('speed');

        } else {
            $tpl_data['url'] = $adv->getExtraData('url');
            if ($media == 'image') {
                $tpl_data['duration'] = $adv->getExtraData('duration', 10);
            }
        }

        $tpl_data['media'] = $media;
        $tpl_data['area'] = $adv->getExtraData('area', 0);

    } elseif ($type == Advertising::SCREEN_NAV) {

        $tpl_data['url'] = $adv->getExtraData('url');

    } elseif (in_array($type, [Advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

        $tpl_data['images'] = $adv->getExtraData('images');
        $tpl_data['link'] = $adv->getExtraData('link');

        $tpl_data['app_id'] = $adv->getExtraData('app_id');
        $tpl_data['app_path'] = $adv->getExtraData('app_path');

    } elseif ($type == Advertising::REDIRECT_URL) {

        $tpl_data['url'] = $adv->getExtraData('url', '');
        $tpl_data['delay'] = $adv->getExtraData('delay', 10);
        $tpl_data['when'] = $adv->getExtraData(
            'when',
            [
                'success' => 1,
                'fail' => 1,
            ]
        );

    } elseif ($type == Advertising::PUSH_MSG) {

        $tpl_data['delay'] = $adv->getExtraData('delay');
        $tpl_data['msg'] = $adv->getExtraData('msg');

    } elseif ($type == Advertising::GOODS) {

        $tpl_data['image'] = $adv->getExtraData('image');
        $tpl_data['url'] = $adv->getExtraData('url');
        $tpl_data['price'] = $adv->getExtraData('price');
        $tpl_data['discount_price'] = $adv->getExtraData('discount_price');
        $tpl_data['app_id'] = $adv->getExtraData('app_id');
        $tpl_data['app_path'] = $adv->getExtraData('app_path');

    } elseif ($type == Advertising::QRCODE) {

        $tpl_data['text'] = $adv->getExtraData('text');
        $tpl_data['image'] = $adv->getExtraData('image');

    } elseif ($type == Advertising::LINK) {

        $tpl_data['image'] = $adv->getExtraData('image');
        $tpl_data['app_id'] = $adv->getExtraData('app_id');
        $tpl_data['app_path'] = $adv->getExtraData('app_path');
        $tpl_data['url'] = $adv->getExtraData('url');

    } elseif ($type == Advertising::PASSWD) {

        $tpl_data['code'] = $adv->getExtraData('code');
        $tpl_data['text'] = $adv->getExtraData('text');

    } elseif ($type == Advertising::WX_APP_URL_CODE) {

        $tpl_data['code'] = $adv->getExtraData('code');

    } elseif ($type == Advertising::SPONSOR) {

        $tpl_data['name'] = $adv->getExtraData('name', '');
        $tpl_data['num'] = $adv->getExtraData('num', 0);
    }
}

$filename = Advertising::$names[$type];
app()->showTemplate("web/adv/$filename", $tpl_data);