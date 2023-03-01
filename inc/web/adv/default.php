<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advertisingModelObj;
use zovye\model\agentModelObj;

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
            Util::itoast('找不到这个代理商', $this->createWebUrl('adv', ['type' => $type]), 'error');
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

    /** @var advertisingModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'agentId' => intval($entry->getAgentId()),
            'type' => intval($entry->getType()),
            'state' => intval($entry->getState()),
            'type_formatted' => Advertising::desc(intval($entry->getType())),
            'title' => strval($entry->getTitle()),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'assigned' => !isEmptyArray($entry->settings('assigned', [])),
        ];

        $data['state_formatted'] = $entry->getState() == Advertising::NORMAL ? 'normal' : 'banned';

        if ($data['agentId']) {
            /** @var agentModelObj $x */
            $x = $entry->getOwner();
            if (empty($x)) {
                $entry->setState(Advertising::BANNED);
                $entry->save();
            } else {
                $data['agent'] = [
                    'id' => $x->getId(),
                    'name' => $x->getName(),
                    'avatar' => $x->getAvatar(),
                    'level' => $x->getAgentLevel(),
                ];
                $reviewResult = $entry->getReviewResult();
                $data['review'] = [
                    'result' => $reviewResult,
                    'title' => ReviewResult::desc($reviewResult),
                ];
            }
        }
        if ($entry->getType() == Advertising::SCREEN) {

            $media = $entry->getExtraData('media');
            if ($media == 'srt') {
                $data['text'] = urlencode($entry->getExtraData('text'));
            } else {
                $data['url'] = Util::toMedia($entry->getExtraData('url'));
            }

            $data['media'] = $media;
            $data['media_formatted'] = "{$media_data[$media]['title']}";
            $data['type_formatted'] .= "({$data['media']})";

        } elseif (in_array($entry->getType(), [advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

            $data['count'] = count($entry->getExtraData('images'));
            $data['link'] = $entry->getExtraData('link');

        } elseif ($type == Advertising::REDIRECT_URL) {

            $data['url'] = $entry->getExtraData('url');

        } elseif ($type == Advertising::PUSH_MSG) {

            $data['msg_type'] = $entry->getExtraData('msg.type');
            $data['msg_typename'] = $wx_data[$data['msg_type']]['title'];

        } elseif ($type == Advertising::LINK) {

            $data['url'] = $entry->getExtraData('url');

        } elseif ($type == Advertising::GOODS) {

            $data['image'] = $entry->getExtraData('image');
            $data['url'] = $entry->getExtraData('url');
            $data['price'] = $entry->getExtraData('price');
            $data['discount_price'] = $entry->getExtraData('discount_price');

        } elseif ($type == Advertising::QRCODE) {

            $data['text'] = $entry->getExtraData('text');
            $data['image'] = $entry->getExtraData('image');

        } elseif ($type == Advertising::PASSWD) {

            $data['code'] = $entry->getExtraData('code');
            $data['text'] = $entry->getExtraData('text');

        } elseif ($type == Advertising::WX_APP_URL_CODE) {

            $data['code'] = $entry->getExtraData('code');

        } elseif ($type == Advertising::SPONSOR) {

            $data['name'] = $entry->getExtraData('name', '');
            $data['num'] = $entry->getExtraData('num', 0);
            $data['title'] = PlaceHolder::replace($data['title'], [
                'num' => $data['num'],
            ]);
        }

        $list[] = $data;
    }
}

$tpl_data['advs'] = $list;

$url_params['type'] = $type;
$tpl_data['search_url'] = $this->createWebUrl('adv', $url_params);

$filename = Advertising::$names[$type];
app()->showTemplate("web/adv/$filename", $tpl_data);