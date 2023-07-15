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

if ($id > 0) {
    $tpl_data['id'] = $id;
    $tpl_data['media'] = Request::trim('media');
    $tpl_data['from_type'] = Request::trim('from_type', $type);

    /** @var advertisingModelObj $ad */
    $ad = Advertising::query(['type' => $type, 'id' => $id])->findOne();
    if (empty($ad)) {
        Response::toast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $type]), 'error');
    }

    $tpl_data['state'] = $ad->getState();
    $tpl_data['title'] = $ad->getTitle();

    if ($ad->getAgentId()) {
        $agent = Agent::get($ad->getAgentId());
        if (empty($agent)) {
            Response::toast('找不到这个广告所属的代理商！', $this->createWebUrl('adv', ['type' => $type]), 'error');
        }
    }

    if ($type == Advertising::SCREEN) {
        $media = $ad->getExtraData('media');
        if ($media == 'srt') {

            $tpl_data['text'] = $ad->getExtraData('text');
            $tpl_data['size'] = $ad->getExtraData('size');
            $tpl_data['clr'] = $ad->getExtraData('clr');
            $tpl_data['background'] = $ad->getExtraData('background-clr');
            $tpl_data['speed'] = $ad->getExtraData('speed');

        } else {
            $tpl_data['url'] = $ad->getExtraData('url');
            if ($media == 'image') {
                $tpl_data['duration'] = $ad->getExtraData('duration', 10);
            }
        }

        $tpl_data['media'] = $media;
        $tpl_data['area'] = $ad->getExtraData('area', 0);

    } elseif ($type == Advertising::SCREEN_NAV) {

        $tpl_data['url'] = $ad->getExtraData('url');

    } elseif (in_array($type, [Advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

        $tpl_data['images'] = $ad->getExtraData('images');
        $tpl_data['link'] = $ad->getExtraData('link');

        $tpl_data['app_id'] = $ad->getExtraData('app_id');
        $tpl_data['app_path'] = $ad->getExtraData('app_path');

    } elseif ($type == Advertising::REDIRECT_URL) {

        $tpl_data['url'] = $ad->getExtraData('url', '');
        $tpl_data['delay'] = $ad->getExtraData('delay', 10);
        $tpl_data['when'] = $ad->getExtraData(
            'when',
            [
                'success' => 1,
                'fail' => 1,
            ]
        );

    } elseif ($type == Advertising::PUSH_MSG) {

        $tpl_data['delay'] = $ad->getExtraData('delay');
        $tpl_data['msg'] = $ad->getExtraData('msg');

    } elseif ($type == Advertising::GOODS) {

        $tpl_data['image'] = $ad->getExtraData('image');
        $tpl_data['url'] = $ad->getExtraData('url');
        $tpl_data['price'] = $ad->getExtraData('price');
        $tpl_data['discount_price'] = $ad->getExtraData('discount_price');
        $tpl_data['app_id'] = $ad->getExtraData('app_id');
        $tpl_data['app_path'] = $ad->getExtraData('app_path');

    } elseif ($type == Advertising::QRCODE) {

        $tpl_data['text'] = $ad->getExtraData('text');
        $tpl_data['image'] = $ad->getExtraData('image');

    } elseif ($type == Advertising::LINK) {

        $tpl_data['image'] = $ad->getExtraData('image');
        $tpl_data['app_id'] = $ad->getExtraData('app_id');
        $tpl_data['app_path'] = $ad->getExtraData('app_path');
        $tpl_data['url'] = $ad->getExtraData('url');

    } elseif ($type == Advertising::PASSWD) {

        $tpl_data['code'] = $ad->getExtraData('code');
        $tpl_data['text'] = $ad->getExtraData('text');

    } elseif ($type == Advertising::WX_APP_URL_CODE) {

        $tpl_data['code'] = $ad->getExtraData('code');

    } elseif ($type == Advertising::SPONSOR) {

        $tpl_data['name'] = $ad->getExtraData('name', '');
        $tpl_data['num'] = $ad->getExtraData('num', 0);
    }
}

$filename = Advertising::$names[$type];
Response::showTemplate("web/adv/$filename", $tpl_data);