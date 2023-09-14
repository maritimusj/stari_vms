<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Advertising;
use zovye\domain\Agent;
use zovye\model\advertisingModelObj;
use zovye\model\agentModelObj;
use zovye\util\PlaceHolder;
use zovye\util\Util;

$type = Request::int('type', Advertising::SCREEN);

$media_data = Advertising::getMediaData();
$wx_data = Advertising::getWxData();

$tpl_data = [
    'type' => $type,
    'op' => 'default',
    'media_data' => $media_data,
    'wx_data' => $wx_data,
];

$tpl_data['navs'] = Advertising::getNavData();

if (App::isSponsorAdEnabled()) {
    $tpl_data['navs'][] = [
        'type' => Advertising::SPONSOR,
        'title' => '赞助商轮播文字',
    ];
}

$url_params = [];

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Advertising::query(['type' => $type]);

if (Request::isset('status')) {
    $query->where(['state' => Request::int('status')]);
    $url_params['state'] = Request::int('status');
} else {
    $query->where(['state <>' => Advertising::DELETED]);
}

if (Request::isset('agentId')) {
    $filter_agentId = Request::int('agentId');
    if ($filter_agentId > 0) {
        $filter_agent = Agent::get($filter_agentId);
        if (empty($filter_agent)) {
            Response::toast('找不到这个代理商', Util::url('adv', ['type' => $type]), 'error');
        }
        $tpl_data['filter_agent'] = $filter_agent;
    }

    $query->where(['agent_id' => $filter_agentId]);

    $url_params['agentId'] = $filter_agentId;
    $tpl_data['filter_agentId'] = $filter_agentId;
}

$keywords = Request::trim('keywords', '', true);
if ($keywords) {
    $query->where(['title LIKE' => "%$keywords%"]);
    $tpl_data['keywords'] = $keywords;
}

if ($type == Advertising::SCREEN) {
    $filter_media = Request::trim('media');
    if (in_array($filter_media, ['image', 'video', 'audio', 'srt'])) {
        $len = strlen($filter_media);
        $query->where(['extra LIKE' => "%s:5:\"media\";s:$len:\"$filter_media\"%"]);

        $url_params['media'] = $filter_media;
        $tpl_data['filter_media'] = $filter_media;
    }
} elseif ($type == Advertising::PUSH_MSG) {
    $filter_msg_type = Request::trim('msgtype');
    if (in_array($filter_msg_type, ['image', 'mpnews', 'text'])) {
        $query->where(['extra REGEXP' => "s:4:\"type\";s:.+:\"$filter_msg_type\""]);

        $url_params['msgtype'] = $filter_msg_type;
        $tpl_data['filter_msgtype'] = $filter_msg_type;
    }
}

$total = $query->count();

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$list = [];
if ($total > 0) {
    $query->page($page, $page_size);
    $query->orderBy('createtime DESC');

    /** @var advertisingModelObj $ad */
    foreach ($query->findAll() as $ad) {
        $data = [
            'id' => $ad->getId(),
            'agentId' => intval($ad->getAgentId()),
            'type' => intval($ad->getType()),
            'state' => intval($ad->getState()),
            'type_formatted' => Advertising::desc(intval($ad->getType())),
            'title' => strval($ad->getTitle()),
            'createtime_formatted' => date('Y-m-d H:i:s', $ad->getCreatetime()),
            'assigned' => !isEmptyArray($ad->settings('assigned', [])),
        ];

        $data['state_formatted'] = $ad->getState() == Advertising::NORMAL ? 'normal' : 'banned';

        if ($data['agentId']) {
            /** @var agentModelObj $x */
            $x = $ad->getOwner();
            if (empty($x)) {
                $ad->setState(Advertising::BANNED);
                $ad->save();
            } else {
                $data['agent'] = [
                    'id' => $x->getId(),
                    'name' => $x->getName(),
                    'avatar' => $x->getAvatar(),
                    'level' => $x->getAgentLevel(),
                ];
                $reviewResult = $ad->getReviewResult();
                $data['review'] = [
                    'result' => $reviewResult,
                    'title' => ReviewResult::desc($reviewResult),
                ];
            }
        }
        if ($ad->getType() == Advertising::SCREEN) {

            $media = $ad->getExtraData('media');
            if ($media == 'srt') {
                $data['text'] = urlencode($ad->getExtraData('text'));
            } else {
                $data['url'] = Util::toMedia($ad->getExtraData('url'));
            }

            $data['media'] = $media;
            $data['media_formatted'] = "{$media_data[$media]['title']}";
            $data['type_formatted'] .= "({$data['media']})";

        } elseif (in_array($ad->getType(), [advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

            $data['count'] = count($ad->getExtraData('images'));
            $data['link'] = $ad->getExtraData('link');

        } elseif ($type == Advertising::REDIRECT_URL) {

            $data['url'] = $ad->getExtraData('url');

        } elseif ($type == Advertising::PUSH_MSG) {

            $data['msg_type'] = $ad->getExtraData('msg.type');
            $data['msg_typename'] = $wx_data[$data['msg_type']]['title'];

        } elseif ($type == Advertising::LINK) {

            $data['url'] = $ad->getExtraData('url');

        } elseif ($type == Advertising::GOODS) {

            $data['image'] = $ad->getExtraData('image');
            $data['url'] = $ad->getExtraData('url');
            $data['price'] = $ad->getExtraData('price');
            $data['discount_price'] = $ad->getExtraData('discount_price');

        } elseif ($type == Advertising::QRCODE) {

            $data['text'] = $ad->getExtraData('text');
            $data['image'] = $ad->getExtraData('image');

        } elseif ($type == Advertising::PASSWD) {

            $data['code'] = $ad->getExtraData('code');
            $data['text'] = $ad->getExtraData('text');

        } elseif ($type == Advertising::WX_APP_URL_CODE) {

            $data['code'] = $ad->getExtraData('code');

        } elseif ($type == Advertising::SPONSOR) {

            $data['name'] = $ad->getExtraData('name', '');
            $data['num'] = $ad->getExtraData('num', 0);
            $data['title'] = PlaceHolder::replace($data['title'], [
                'num' => $data['num'],
            ]);
        }

        $list[] = $data;
    }
}

$tpl_data['advs'] = $list;

$url_params['type'] = $type;
$tpl_data['search_url'] = Util::url('adv', $url_params);

$filename = Advertising::$names[$type];
Response::showTemplate("web/adv/$filename", $tpl_data);