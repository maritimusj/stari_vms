<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\accountModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\orderModelObj;

$op = request::op('default');

if ($op == 'default') {
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);
    $banned = request::bool('banned');

    $query = Account::query();
    if ($banned) {
        $query->where(['state' => Account::BANNED]);
    } else {
        $query->where([
            'state' => [Account::NORMAL, Account::VIDEO, Account::AUTH],
        ]);
    }

    if (request::has('agentId')) {
        $agent = Agent::get(request::int('agentId'));
        if ($agent) {
            $query->where(['agent_id' => $agent->getId()]);
        }
    }

    $keywords = trim(urldecode(request::str('keywords')));
    if ($keywords) {
        $query->whereOr([
            'name LIKE' => "%{$keywords}%",
            'title LIEK' => "%{$keywords}%",
        ]);
    }

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    if ($page > $total_page) {
        $page = 1;
    }

    $pager = We7::pagination($total, $page, $page_size);

    $accounts = [];
    if ($total > 0) {
        $query->page($page, $page_size);
        $query->orderBy('order_no DESC');

        /** @var accountModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'state' => $entry->getState(),
                'agentId' => $entry->getAgentId(),
                'uid' => $entry->getUid(),
                'clr' => $entry->getClr(),
                'orderno' => $entry->getOrderNo(),
                'groupname' => $entry->getGroupName(),
                'name' => $entry->getName(),
                'title' => $entry->getTitle(),
                'descr' => $entry->getDescription(),
                'img' => $entry->getImg(),
                'qrcode' => $entry->getQrcode(),
                'scname' => $entry->getScname(),
                'count' => $entry->getCount(),
                'sccount' => $entry->getSccount(),
                'total' => $entry->getTotal(),
                'orderlimits' => $entry->getOrderLimits(),
                'banned' => $entry->isBanned(),
                'url' => $entry->getUrl(),
                'assigned' => !isEmptyArray($entry->get('assigned')),                
            ];

            if ($entry->isAuth()) {
                $data['service'] = $entry->getServiceType();
            }

            if (App::useAccountQRCode()) {
                $data['useAccountQRCode'] = $entry->useAccountQRCode();
            }

            //关注多个二维码
            $qrcode_data = $entry->get('qrcodesData', []);
            if ($qrcode_data) {
                $data['more_url'] = [];
                foreach ($qrcode_data as $x) {
                    $data['more_url'][] = $x['url'];
                }
            }

            if ($data['agentId']) {
                $agent_x = Agent::get($data['agentId']);
                if ($agent_x) {
                    $data['agent'] = [
                        'id' => $agent_x->getId(),
                        'name' => $agent_x->getName(),
                        'avatar' => $agent_x->getAvatar(),
                        'level' => $agent_x->getAgentLevel(),
                    ];
                }
            }

            if (App::isCommissionEnabled()) {
                $data['commission'] = $entry->get('commission', []);
            }

            //总订单数
            $data['orders'] = Order::query(['account' => $entry->getName()])->count();

            $accounts[] = $data;
        }
    }

    //特殊吸粉
    $one_res = [
        Account::JFB => App::isJfbEnabled(),
        Account::MOSCALE => App::isMoscaleEnabled(),
        Account::YUNFENBA => App::isYunfenbaEnabled(),
        Account::AQIINFO => App::isAQiinfoEnabled(),
    ];

    foreach ($one_res as $index => $enabled) {
        if ($enabled) {
            $t_res = Account::query(['state' => $index])->findOne();
            if ($t_res) {
                $one_res[$index] = [
                    'id' => $t_res->getId(),
                    'orderno' => $t_res->getOrderNo(),
                    'name' => $t_res->getName(),
                    'url' => $t_res->getUrl(),
                    'img' => $t_res->getImg(),
                    'assigned' => !isEmptyArray($t_res->get('assigned')),
                ];
            } else {
                Util::logToFile('account', "特殊吸粉{$index}已开启，但查找公众号资料失败！");
            }
        }
        if (!is_array($one_res[$index])) {
            unset($one_res[$index]);
        }
    }

    app()->showTemplate('web/account/default', [
        'agent' => isset($agent) ? $agent : null,
        'accounts' => $accounts,
        'banned' => $banned,
        'pager' => $pager,
        'keywords' => $keywords,
        'search_url' => $this->createWebUrl('account', ['banned' => $banned]),
        'one_res' => $one_res
    ]);

} elseif ($op == 'search') {

    $result = [];
    
    $query = Account::query();

    $keyword = trim(urldecode(request::str('keyword')));
    if ($keyword) {
        $query->whereOr([
            'name LIKE' => "%{$keyword}%",
            'title LIKE' => "%{$keyword}%",
        ]);
    }

    $query->limit(100);

    /** @var accountModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $result[] = [
            'id' => $entry->getId(),
            'name' => $entry->getName(),
            'title' => $entry->getTitle(),
        ];
    }

    JSON::success($result);

} elseif ($op == 'save') {

    $res = Util::transactionDo(function () {

        $id = request::int('id');
        $name = request::str('name');
        $qr_codes = request::array('qrcodes');

        $data = [
            'title' => request::str('title', $name),
            'descr' => request::str('descr'),
            'qrcode' => request::str('qrcode'),
            'clr' => request::str('clr') ?: 'gray',
            'count' => max(0, request::int('count')),
            'sccount' => max(0, request::int('sccount')),
            'total' => max(0, request::int('total')),
            'balance_deduct_num' => max(0, request::int('balanceDeductNum')),
            'order_limits' => max(0, request::int('orderlimits')),
            'order_no' => request::int('orderno'),
            'group_name' => request::str('groupname'),
            'scname' => request::str('scname', Schema::DAY),
            'shared' => request::has('commission_share') ? 1 : 0,
        ];

        if (!Schema::has($data['scname'])) {
            return err('领取频率只是每天/每周/每月！');
        }

        //这里的agentId是用户openid
        if (request::isset('agentId')) {
            $openid = request::str('agentId');
            if (empty($openid)) {
                $data['agent_id'] = 0;
            } else {
                $agent = Agent::get($openid, true);
                if (empty($agent)) {
                    return err('找不到这个代理商！');
                }
                $data['agent_id'] = intval($agent->getId());
            }
        }

        //是否退出推广
        $commission_share_closed = false;

        if ($id) {
            $account = Account::get($id);
            if (empty($account)) {
                return err('找不到这个公众号！');
            }
            //特殊吸粉
            if ($account->isJFB()) {
                $data['name'] = Account::JFB_NAME;
                $data['img'] = Account::JFB_HEAD_IMG;
                $account->set('config', [
                    'type' => Account::JFB,
                    'url' => request::trim('apiURL'),
                    'appno' => request::trim('appNO'),
                    'scene' => request::trim('scene'),
                ]);
            } elseif ($account->isMoscale()) {
                $data['name'] = Account::MOSCALE_NAME;
                $data['img'] = Account::MOSCALE_HEAD_IMG;
                $account->set('config', [
                    'type' => Account::MOSCALE,
                    'appid' => request::trim('appid'),
                    'appsecret' => request::trim('appsecret'),
                ]);
            } elseif ($account->isYunfenba()) {
                $data['name'] = Account::YUNFENBA_NAME;
                $data['img'] = Account::YUNFENBA_HEAD_IMG;
                $account->set('config', [
                    'type' => Account::YUNFENBA,
                    'vendor' => [
                        'uid' => request::trim('vendorUID'),
                    ]
                ]);
            } elseif ($account->isAQiinfo()) {
                $data['name'] = Account::AQIINFO_NAME;
                $data['img'] = Account::AQIINFO_HEAD_IMG;
                $account->set('config', [
                    'type' => Account::AQIINFO,
                    'key' => request::trim('key'),
                    'secret' => request::trim('secret'),
                ]);
            } elseif ($account->isAuth()) {
                $timing = $account->getServiceType() == 2 ? request::int('OpenTiming') : 1;
                $config = [
                    'type' => Account::AUTH,
                ];
                if (request::str('openMsgType') == 'text') {
                    $config['open'] = [
                        'timing' => $timing,
                        'msg' => request::str('openTextMsg'),
                    ];
                } elseif (request::str('openMsgType') == 'news') {
                    $config['open'] = [
                        'timing' => $timing,
                        'news' => [
                            'title' => request::trim('openNewsTitle'),
                            'desc' => request::trim('openNewsDesc'),
                            'image' => request::trim('openNewsImage'),
                        ],
                    ];
                }
                $account->set('config', $config);
            } else {
                $data['img'] = request::trim('img');
            }

            foreach ($data as $key => $val) {
                $key_name = 'get' . ucfirst(toCamelCase($key));
                if ($val != $account->$key_name()) {
                    $set_name = 'set' . ucfirst(toCamelCase($key));
                    $account->$set_name($val);
                }
            }

            if ($account->getShared() && empty($data['shared'])) {
                $commission_share_closed = true;
            }

        } else {
            if (empty($name)) {
                return err('帐号不能为空！');
            } elseif (in_array($name, [
                Account::JFB_NAME,
                Account::MOSCALE_NAME,
                Account::YUNFENBA_NAME,
                Account::AQIINFO_NAME,
            ])) {
                return err('名称 "' . $name . '" 是系统保留名称，无法使用！');
            }

            if (Account::findOne(['name' => $name])) {
                return err('公众号帐号已经存在！');
            }

            $uid = Account::makeUID($name);
            if (Account::findOne(['uid' => $uid])) {
                return err('公众号UID已经存在！');
            }

            $data['uid'] = $uid;
            $data['name'] = $name;
            $data['state'] = request::int('type');
            $data['title'] = request::str('title');
            $data['img'] = request::trim('img');
            $data['url'] = Account::createUrl($uid, ['from' => 'account']);

            $account = Account::create($data);
            if (empty($account)) {
                return err('创建公众号失败！');
            }
        }

        if ($account->save() && Account::updateAccountData()) {
            //处理多个关注二维码
            if ($qr_codes) {
                $qrcode_data = [];
                foreach ($qr_codes as $qr) {
                    $xid = sha1($qr . Util::random(8));
                    $url = Account::createUrl($account->getUid(), ['xid' => $xid]);
                    $qrcode_data[$xid] = [
                        'img' => $qr,
                        'xid' => $xid,
                        'url' => $url,
                    ];
                }
                $account->set('qrcodesData', $qrcode_data);
            } else {
                $account->remove('qrcodesData');
            }

            //保存用户限制数据
            $limits = [
                'male' => 0,
                'female' => 0,
                'unknown_sex' => 0,
                'ios' => 0,
                'android' => 0,
            ];

            if (request::has('limits')) {
                $arr = request::array('limits');
                foreach ($limits as $name => &$v) {
                    if (in_array($name, $arr)) {
                        $v = 1;
                    }
                }
            }

            $account->set('limits', $limits);

            if ($account->isVideo()) {
                $account->set('config', [
                    'type' => Account::VIDEO,
                    'video' => [
                        'duration' => request::int('duration', 1),
                    ]
                ]);
            }

            if (App::isCommissionEnabled()) {
                $account->set(
                    'commission',
                    [
                        'money' => request::float('commission_money', 0, 2) * 100,
                    ]
                );
            }
            //退出佣金推广后,删除所有代理商分配
            if ($commission_share_closed) {
                Account::removeAllAgents($account);
            }

            return [
                'message' => $commission_share_closed ? '保存成功！注意：所有平台代理商关联已被移除！' : '保存成功！',
                'commissionShareClosed' => $commission_share_closed,
            ];
        }

        return err('操作失败！');
    });

    if (is_error($res)) {
        Util::itoast($res['message'], We7::referer(), 'error');
    } else {
        $back_url = request::has('id') ? $this->createWebUrl('account', ['op' => 'edit', 'id' => request::int('id')]) : $this->createWebUrl('account');
        if ($res['commissionShareClosed']) {
            Util::message($res['message'], $back_url, 'success');
        } else {
            Util::itoast($res['message'], $back_url, 'success');
        }
    }

} elseif ($op == 'edit') {

    $id = request::int('id');

    $agent_name = '';
    $agent_mobile = '';
    $agent_openid = '';
    $config = [];

    $type = Account::NORMAL;

    if ($id) {
        $account = Account::get($id);
        if (empty($account)) {
            Util::itoast('公众号不存在！', $this->createWebUrl('account'), 'error');
        }

        $type = $account->getState();

        if ($account->getAgentId()) {
            $agent = Agent::get($account->getAgentId());
            if ($agent) {
                $agent_name = $agent->getNickname();
                $agent_mobile = $agent->getMobile();
                $agent_openid = $agent->getOpenid();
            }
        }

        $qr_codes = [];
        $qrcode_data = $account->get('qrcodesData', []);
        if ($qrcode_data && is_array($qrcode_data)) {
            foreach ($qrcode_data as $xid => $entry) {
                $qr_codes[] = $entry['img'];
            }
        }

        $limits = $account->get('limits');
        $commission = $account->get('commission', []);
        $config = $account->get('config');
    }

    app()->showTemplate('web/account/edit', [
        'op' => $op,
        'type' => $type,
        'id' => $id,
        'account' => isset($account) ? $account : null,
        'qrcodes' => isset($qr_codes) ? $qr_codes : null,
        'limits' => isset($limits) ? $limits : null,
        'commission' => isset($commission) ? $commission : null,
        'agent_name' => $agent_name,
        'agent_mobile' => $agent_mobile,
        'agent_openid' => $agent_openid,
        'config' => $config,
    ]);

} elseif ($op == 'add') {
    app()->showTemplate('web/account/edit', [
        'clr' => Util::randColor(),
        'op' => $op,
        'type' => request::int('type', Account::NORMAL),
    ]);
} elseif ($op == 'remove') {

    $id = request::int('id');
    if ($id) {
        $account = Account::get($id);
        if ($account) {
            $title = $account->getTitle();
            $account->destroy();
            Account::updateAccountData();
            Util::itoast("删除公众号{$title}成功！", $this->createWebUrl('account'), 'success');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('account'), 'error');

} elseif ($op == 'ban') {

    $id = request::int('id');
    if ($id) {
        $account = Account::get($id);
        if ($account) {
            if ($account->isBanned()) {
                if ($account->isSpecial() || $account->isAuth() || $account->isVideo()) {
                    $account->setState($account->getType());
                } else {
                    $account->setState(Account::NORMAL);
                }
            } else {
                $account->setState(Account::BANNED);
            }

            if ($account->save() && Account::updateAccountData()) {
                Util::itoast("{$account->getTitle()}设置成功！", $this->createWebUrl('account'), 'success');
            }
        }
    }

    Util::itoast('操作失败！', $this->createWebUrl('account'), 'error');

} elseif ($op == 'assign') {

    $commission_enabled = App::isCommissionEnabled();

    $id = request::int('id');
    $account = Account::get($id);
    if (empty($account)) {
        Util::itoast('这个公众号不存在！', $this->createWebUrl('account'), 'error');
    }

    $data = [
        'id' => $account->getId(),
        'agentId' => $account->getAgentId(),
        'uid' => $account->getUid(),
        'clr' => $account->getClr(),
        'name' => $account->getName(),
        'title' => $account->getTitle(),
        'descr' => $account->getDescription(),
        'img' => $account->getImg(),
        'qrcode' => $account->getQrcode(),
    ];


    if ($data['agentId']) {
        $agent = Agent::get($data['agentId']);
        if ($agent) {
            $data['agent'] = [
                'name' => $agent->getName(),
                'avatar' => $agent->getAvatar(),
            ];
        }
    }

    $assigned = $account->settings('assigned', []);
    $assigned = isEmptyArray($assigned) ? [] : $assigned;

    app()->showTemplate('web/account/assign_v', [
        'id' => $id,
        'commission_enabled' => $commission_enabled,
        'account' => $data,
        'assign_data' => json_encode($assigned),
    ]);

} elseif ($op == 'getAssigned') {

    $result = [
        'types' => [],
        'cities' => [],
        'agents' => [],
        'devices' => [],
        'tags' => [],
    ];

    $id = request::int('id');
    $account = Account::get($id);
    if ($account) {
        $data = $account->get('assigned', []);

        if ($data['types'] && is_array($data['types'])) {
            $result['types'] = $data['types'];
        }
        if ($data['cities'] && is_array($data['cities'])) {
            $result['cities'] = $data['cities'];
        }

        if ($data['agents'] && is_array($data['agents'])) {
            foreach ($data['agents'] as $id) {
                $agent = Agent::get($id);
                if ($agent) {
                    $result['agents'][] = [
                        'id' => $agent->getId(),
                        'nickname' => $agent->getName(),
                        'avatar' => $agent->getAvatar(),
                        'total' => $agent->getDeviceCount(),
                    ];
                }
            }
        }

        if ($data['devices'] && is_array($data['devices'])) {
            foreach ($data['devices'] as $id) {
                $device = Device::get($id);
                if ($device) {
                    $result['devices'][] = [
                        'id' => $device->getId(),
                        'name' => $device->getName(),
                    ];
                }
            }
        }
        if ($data['tags'] && is_array($data['tags'])) {
            $result['tags'] = $data['tags'];
        }
    }

    JSON::success($result);

} elseif ($op == 'saveAssignData') {

    $id = request::int('id');
    $raw = request('data');
    $data = is_string($raw) ? json_decode(htmlspecialchars_decode($raw), true) : $raw;

    $account = Account::get($id);
    if ($account) {
        if ($account->useAccountQRCode()) {
            CtrlServ::appNotifyAll($account->getAssignData(), $data);
        }
        if ($account->set('assigned', $data) && Account::updateAccountData()) {
            JSON::success('设置已经保存成功！');
        }
    }

    JSON::fail('保存失败！');

} elseif ($op == 'viewStats') {

    $id = request::int('id');

    $acc = Account::get($id);
    if (empty($acc)) {
        JSON::fail('找不到这个公众号！');
    }

    $title = $acc->getTitle();

    $time = request::has('month') ? date('Y-') . request::int('month') . date('-01 00:00:00') : 'today';

    $caption = date('Y年n月', strtotime($time));
    $data = Stats::chartDataOfMonth($acc, $time, "公众号：{$title}({$caption})");

    $content = app()->fetchTemplate(
        'web/account/stats',
        [
            'chartid' => 'chart-' . Util::random(10),
            'chart' => $data,
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);

} elseif ($op == 'viewHistoryStats') {

    $id = request::int('id');

    $acc = Account::get($id);
    if (empty($acc)) {
        JSON::fail('找不到这个公众号！');
    }

    $title = $acc->getTitle();

    $content = app()->fetchTemplate(
        'web/account/stats_history',
        [
            'id' => $id,
            'title' => $title,
            'm_all' => Stats::months($acc),
        ]
    );

    JSON::success(['title' => "<b>{$title}</b>的出货统计", 'content' => $content]);

} elseif ($op == 'repairMonthStats') {

    $account = Account::get(request::int('id'));
    if (empty($account)) {
        JSON::fail('找不到这个公众号！');
    }

    $month = strtotime(request::str('month'));
    if (empty($month)) {
        $month = time();
    }

    if (Stats::repairMonthData($account, $month)) {
        JSON::success('修复完成！');
    }

    JSON::success('修复失败！');

} elseif ($op == 'viewFansCount') {

    $id = request::int('id');
    $acc = Account::get($id);

    if (empty($acc)) {
        JSON::fail('找不到这个公众号！');
    }

    $query = Order::query(['account' => $acc->getName()]);

    $num = (int)$query->get('count(DISTINCT `openid`)');

    JSON::success("{$acc->getTitle()}，净增粉丝总数：{$num}人");

} elseif ($op == 'platform_stat') {

    //平台 统计
    $date_limit = request::array('datelimit');
    if ($date_limit['start']) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'] . ' 00:00:00');
    } else {
        $s_date = new DateTime('00:00:00');
    }

    if ($date_limit['end']) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'] . ' 00:00:00');
        $e_date->modify('next day');
    } else {
        $e_date = new DateTime('next day 00:00:00');
    }

    $condition = [
        'createtime >=' => $s_date->getTimestamp(),
        'createtime <' => $e_date->getTimestamp(),
    ];

    $data = [];
    $total = [
        'order_fee' => 0,
        'comm_fee' => 0,
        'total_fee' => 0,
    ];

    //订单分成
    $query = Order::query($condition);

    /** @var orderModelObj $item */
    foreach ($query->findAll() as $item) {
        if ($item->getExtraData('pay') && !$item->getExtraData('refund')) {
            $val = abs($item->getExtraData('pay')['fee']);
            $create_date = date('Y-m-d', $item->getCreatetime());
            if (!isset($data[$create_date])) {
                $data[$create_date]['order_fee'] = 0;
                $data[$create_date]['comm_fee'] = 0;
                $data[$create_date]['total_fee'] = 0;
            }
            $data[$create_date]['order_fee'] += $val;
            $data[$create_date]['total_fee'] += $val;
            $total['order_fee'] += $val;
            $total['total_fee'] += $val;
        }
    }

    //提现分成
    $cond = array_merge($condition, ['src' => CommissionBalance::FEE]);
    $commission_query = CommissionBalance::query($cond);

    /** @var commission_balanceModelObj $item */
    foreach ($commission_query->findAll() as $item) {
        $val = abs($item->getXVal());
        $create_date = date('Y-m-d', $item->getCreatetime());
        if (!isset($data[$create_date])) {
            $data[$create_date]['order_fee'] = 0;
            $data[$create_date]['comm_fee'] = 0;
            $data[$create_date]['total_fee'] = 0;
        }
        $data[$create_date]['comm_fee'] += $val;
        $data[$create_date]['total_fee'] += $val;
        $total['comm_fee'] += $val;
        $total['total_fee'] += $val;
    }

    krsort($data);

    $tpl_data = [];
    $tpl_data['data'] = $data;
    $tpl_data['total'] = $total;

    $tpl_data['s_date'] = $s_date->format('Y-m-d');
    $e_date->modify('-1 day');
    $tpl_data['e_date'] = $e_date->format('Y-m-d');

    app()->showTemplate('web/account/platform_stat', $tpl_data);

} elseif ($op == 'accountAuthorize') {

    $url = WxPlatform::getPreAuthUrl();
    if (empty($url)) {
        JSON::fail('暂时无法获取授权转跳网址！');
    }

    $content = app()->fetchTemplate(
        'web/account/authorize',
        [
            'url' => $url,
        ]
    );

    JSON::success(['title' => "公众号接入授权", 'content' => $content]);

} elseif ($op == 'useAccountQRCode') {

    if (!App::useAccountQRCode()) {
        JSON::fail('未启用这个功能！');
    }

    $account = Account::get(request::int('id'));
    if (empty($account)) {
        JSON::fail('找不到这个公众号！');
    }

    if (!$account->isAuth() || $account->getServiceType() !== 2) {
        JSON::fail('只能是授权接入的服务号才能设置为屏幕二维码！');
    }

    $enable = $account->useAccountQRCode();
    if ($account->useAccountQRCode(!$enable)) {
        CtrlServ::appNotifyAll($account->getAssignData());
        JSON::success($enable ? '已取消成功！' : '已设置成功！');
    }

    JSON::fail('设置失败！');
}
