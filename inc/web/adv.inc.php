<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\advertisingModelObj;
use zovye\model\agentModelObj;

defined('IN_IA') or exit('Access Denied');

$media_data = [
    'image' => [
        'icon' => 'fa-image',
        'title' => '图片',
    ],
    'video' => [
        'icon' => 'fa-youtube-play',
        'title' => '视频',
    ],
    'audio' => [
        'icon' => 'fa-music',
        'title' => '音频',
    ],
    'srt' => [
        'icon' => 'fa-text-width',
        'title' => '字幕',
    ],
];

$wx_data = [
    'image' => [
        'title' => '图片',
    ],
    'mpnews' => [
        'title' => '图文',
    ],
    'text' => [
        'title' => '文本',
    ],
];

$type = request::int('type', Advertising::SCREEN);
$op = request::op('default');

$tpl_data = [
    'type' => $type,
    'op' => $op,
    'media_data' => $media_data,
    'wx_data' => $wx_data,
];

if ($op == 'default') {

    $tpl_data['navs'] = [
        [
            'type' => Advertising::SCREEN,
            'title' => '设备屏幕',
        ],
        [
            'type' => Advertising::SCREEN_NAV,
            'title' => '屏幕引导图',
        ],
        [
            'type' => Advertising::WELCOME_PAGE,
            'title' => '关注页面',
        ],
        [
            'type' => Advertising::GET_PAGE,
            'title' => '领取页面',
        ],
        [
            'type' => Advertising::REDIRECT_URL,
            'title' => '网址转跳',
        ],
        [
            'type' => Advertising::PUSH_MSG,
            'title' => '消息推送',
        ],
        [
            'type' => Advertising::LINK,
            'title' => '链接推广',
        ],
        [
            'type' => Advertising::GOODS,
            'title' => '商品推荐',
        ],
        [
            'type' => Advertising::QRCODE,
            'title' => '推广二维码',
        ],
        [
            'type' => Advertising::PASSWD,
            'title' => '口令',
        ],
        [
            'type' => Advertising::WX_APP_URL_CODE,
            'title' => '小程序识别码',
        ],
    ];

    $url_params = [];

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = Advertising::query(['type' => $type]);

    if (request::isset('status')) {
        $query->where(['state' => request::int('status')]);
        $url_params['state'] = request::int('status');
    } else {
        $query->where(['state <>' => Advertising::DELETED]);
    }

    if (request::isset('agentId')) {
        $filter_agentId = request::int('agentId');
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

    $keywords = request::trim('keywords', '', true);
    if ($keywords) {
        $query->where(['title LIKE' => "%$keywords%"]);
        $tpl_data['keywords'] = $keywords;
    }

    if ($type == Advertising::SCREEN) {
        $filter_media = request::trim('media');
        if (in_array($filter_media, ['image', 'video', 'audio', 'srt'])) {
            $len = strlen($filter_media);
            $query->where(['extra LIKE' => "%s:5:\"media\";s:$len:\"$filter_media\"%"]);

            $url_params['media'] = $filter_media;
            $tpl_data['filter_media'] = $filter_media;
        }
    } elseif ($type == Advertising::PUSH_MSG) {
        $filter_msg_type = request::trim('msgtype');
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

            }

            $list[] = $data;
        }
    }

    $tpl_data['advs'] = $list;

    $url_params['type'] = $type;
    $tpl_data['search_url'] = $this->createWebUrl('adv', $url_params);

} elseif ($op == 'refresh') {

    $id = request::int('id');

    if ($id > 0) {
        $adv = Advertising::get($id);
    }

    if (empty($adv)) {

        JSON::fail('找不到这个广告！');

    } elseif (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {

        $assign_data = $adv->settings('assigned', []);
        if ($assign_data) {
            if (Advertising::notifyAll([], $assign_data)) {
                JSON::success('已通知设备更新！');
            }
        }
    }

    JSON::fail('操作失败！');

} elseif ($op == 'assign') {

    $id = request::int('id');

    $res = null;

    if ($id > 0) {
        $res = Advertising::get($id, $type);
    }

    if (empty($res)) {
        Util::itoast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $type]), 'error');
    }

    $adv = [
        'id' => $res->getId(),
        'state' => intval($res->getState()),
        'agentId' => intval($res->getAgentId()),
        'type' => intval($res->getType()),
        'type_formatted' => Advertising::desc(intval($res->getType())),
        'title' => strval($res->getTitle()),
        'createtime_formatted' => date('Y-m-d H:i:s', $res->getCreatetime()),
    ];

    if ($res->getType() == Advertising::SCREEN) {
        $media = $res->getExtraData('media');
        $adv['media'] = "{$media_data[$media]['title']}";
        $adv['type_formatted'] .= "({$adv['media']})";
    }

    $assigned = $res->settings('assigned', []);
    $assigned = isEmptyArray($assigned) ? [] : $assigned;

    app()->showTemplate('web/adv/assign', [
        'adv' => $adv,
        'multi_mode' => settings('advs.assign.multi') ? 'true' : '',
        'assign_data' => json_encode($assigned),
        'agent_url' => $this->createWebUrl('agent'),
        'group_url' => $this->createWebUrl('device', array('op' => 'group')),
        'tag_url' => $this->createWebUrl('tags'),
        'device_url' => $this->createWebUrl('device'),
        'save_url' => $this->createWebUrl('adv', array('op' => 'saveAssignData')),
        'back_url' => $this->createWebUrl('adv', ['type' => $res->getType()]),
    ]);

} elseif ($op == 'saveAssignData') {

    $id = request::int('id');
    $data = request::is_string('data') ? json_decode(htmlspecialchars_decode(request::str('data')), true) : request('data');

    $adv = Advertising::get($id);
    if (empty($adv)) {
        JSON::fail('找不到这个广告，无法保存！');
    }

    $origin_data = $adv->settings('assigned', []);
    if ($adv->updateSettings('assigned', $data) && Advertising::update($adv)) {
        if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
            if (Advertising::notifyAll($origin_data, $data)) {
                JSON::success('设置已经保存成功，已通知设备更新！');
            } else {
                JSON::success('设置已经保存成功，通知设备失败！');
            }
        } else {
            JSON::success('设置已经保存成功！');
        }
    }

    JSON::fail('保存失败！');

} elseif ($op == 'add' || $op == 'edit') {

    $id = request::int('id');

    $tpl_data['id'] = $id;
    $tpl_data['media'] = request::trim('media');
    $tpl_data['from_type'] = request::trim('from_type', $type);

    if ($id > 0) {
        /** @var advertisingModelObj $adv */
        $adv = Advertising::query(['type' => $type, 'id' => $id])->findOne();
        if (empty($adv)) {
            Util::itoast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $type]), 'error');
        }

        $tpl_data['state'] = $adv->getState();
        $tpl_data['title'] = $adv->getTitle();

        if ($adv->getAgentId()) {
            $agent = Agent::get($adv->getAgentId());
            if (empty($agent)) {
                Util::itoast('找不到这个广告所属的代理商！', $this->createWebUrl('adv', ['type' => $type]), 'error');
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
            
            if ($type == Advertising::GET_PAGE) {
                $tpl_data['app_id'] = $adv->getExtraData('app_id');
                $tpl_data['app_path'] = $adv->getExtraData('app_path');
            }

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

        }
    }

} elseif ($op == 'save') {

    $id = request::int('id');

    $from_type = request::trim('from_type', $type);
    $from_op = request::str('from_op');
    $from_media = request::str('media');

    $adv = null;

    if ($id > 0) {
        $adv = Advertising::get($id);
        if (empty($adv)) {
            Util::itoast(
                '找不到指定的广告！',
                $this->createWebUrl(
                    'adv',
                    ['type' => $from_type, 'op' => $from_op, 'media' => $from_media]
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
                ['type' => $from_type, 'op' => $from_op, 'media' => $from_media]
            ),
            'error'
        );
    }

    Util::itoast($result['msg'], $this->createWebUrl('adv', ['type' => $from_type]), 'success');

} elseif ($op == 'remove') {

    $id = request::int('id');
    $type = request::int('type');
    $from_type = request::int('from_type');

    if ($id > 0 && $type > 0) {
        /** @var advertisingModelObj $adv */
        $adv = Advertising::query(['id' => $id, 'type' => $type])->findOne();
        if (empty($adv)) {
            Util::itoast('找不到这个广告！', $this->createWebUrl('adv', ['type' => $from_type]), 'error');
        }

        $assign_data = $adv->settings('assigned', []);

        if (Advertising::update($adv) && $adv->destroy()) {

            if ($adv->getType() == Advertising::SCREEN) {
                //通知设备更新屏幕广告
                Advertising::notifyAll($assign_data, []);
            }

            Util::itoast('删除成功！', $this->createWebUrl('adv', ['type' => $from_type]), 'success');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('adv', ['type' => $from_type]), 'error');

} elseif ($op == 'ban') {

    $id = request::int('id');
    if ($id > 0) {
        $adv = Advertising::get($id);
        if (empty($adv)) {
            JSON::fail('找不到这个广告！');
        }

        $state = $adv->getState() == Advertising::NORMAL ? Advertising::BANNED : Advertising::NORMAL;
        if ($adv->setState($state) && Advertising::update($adv)) {
            if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
                //通知设备更新屏幕广告
                $assign_data = $adv->settings('assigned', []);
                Advertising::notifyAll($assign_data, []);
            }

            JSON::success([
                'msg' => $adv->getState() == Advertising::NORMAL ? '已启用' : '已禁用',
                'state' => intval($adv->getState()),
            ]);
        }
    }

    JSON::fail('操作失败！');

} elseif ($op == 'wxmsg') {

    $id = request::int('id');

    $msg = [];

    /** @var advertisingModelObj $adv */
    $adv = Advertising::query(['type' => Advertising::PUSH_MSG, 'id' => $id])->findOne();
    if ($adv) {
        $msg = $adv->getExtraData('msg', []);
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

} elseif ($op == 'reviewpassed') {

    $id = request::int('id');
    $type = request::int('type');

    if (Advertising::pass($id, _W('username'))) {
        Util::itoast('广告已经通过审核！', $this->createWebUrl('adv', ['type' => $type]), 'success');
    }

    Util::itoast('审核操作失败！', $this->createWebUrl('adv', ['type' => $type]), 'error');

} elseif ($op == 'reviewrejected') {

    $id = request::int('id');
    $type = request::int('type');

    if (Advertising::reject($id)) {
        Util::itoast('广告已经被设置为拒绝通过！', $this->createWebUrl('adv', ['type' => $type]), 'success');
    }

    Util::itoast('审核操作失败！', $this->createWebUrl('adv', ['type' => $type]), 'error');

}

$filename = Advertising::$names[$type];
app()->showTemplate("web/adv/$filename", $tpl_data);
