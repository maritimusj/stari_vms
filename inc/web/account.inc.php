<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use DateTimeImmutable;
use Exception;
use zovye\model\account_queryModelObj;
use zovye\model\accountModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\orderModelObj;

$op = request::op('default');

if ($op == 'default') {
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);


    $query = Account::query();

    $banned = request::bool('banned');
    if ($banned) {
        $query->where(['state' => Account::BANNED]);
    } else {
        $query->where(['state' => Account::NORMAL]);
    }

    if (request::isset('type')) {
        $type = request::int('type');
        if ($type == -1) {
            $all = Account::getAllEnabledThirdPartyPlatform();
            if ($all) {
                $query->where(['type' => $all]);
            } else {
                $query->where(['type' => -1]);
            }
        } else {
            if (empty($type)) {
                $query->where(['type' => [
                    Account::NORMAL,
                    Account::AUTH,
                ]]);
            } else {
                $query->where(['type' => request::int('type')]);
            }
        }
    } else {
        if (!App::isDouyinEnabled()) {
            $query->where(['type <>' => Account::DOUYIN]);
        }
        if (!App::isBalanceEnabled()) {
            $query->where(['type <>' => Account::TASK]);
        }        
    }

    if (request::has('agentId')) {
        $agent = Agent::get(request::int('agentId'));
        if ($agent) {
            $query->where(['agent_id' => $agent->getId()]);
        }
    }

    $keywords = request::trim('keywords', '', true);
    if ($keywords) {
        $query->whereOr([
            'name LIKE' => "%$keywords%",
            'title LIKE' => "%$keywords%",
            'descr LIKE' => "%$keywords%",
        ]);
    }

    $total = $query->count();

    $pager = We7::pagination($total, $page, $page_size);

    $accounts = [];
    if ($total > 0) {
        $query->page($page, $page_size);
        $query->orderBy('order_no DESC');

        /** @var accountModelObj $entry */
        foreach ($query->findAll() as $entry) {
            //过滤掉未启用的吸粉平台
            if ($entry->isThirdPartyPlatform() && $entry->isBanned()) {
                continue;
            }
            $data = [
                'id' => $entry->getId(),
                'type' => $entry->getType(),
                'banned' => $entry->isBanned(),
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
                'url' => $entry->getUrl(),
                'assigned' => !isEmptyArray($entry->get('assigned')),
                'is_third_party_platform' => $entry->isThirdPartyPlatform(),
            ];

            if ($entry->isAuth()) {
                $data['service'] = $entry->getServiceType();
                $data['verified'] = $entry->isVerified();
            } elseif ($entry->isDouyin()) {
                $data['openid'] = $entry->settings('config.openid', '');
            } elseif ($entry->isWxApp()) {
                $data['username'] = $entry->settings('config.username', '');
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
                if ($entry->getBonusType() == Account::COMMISSION) {
                    $data['commission'] = $entry->getCommissionPrice();
                }
            }

            if (App::isBalanceEnabled()) {
                if ($entry->getBonusType() == Account::BALANCE) {
                    $data['balance'] = $entry->getBalancePrice();
                }
            }
            
            $accounts[] = $data;
        }
    }

    app()->showTemplate('web/account/default', [
        'agent' => $agent ?? null,
        'accounts' => $accounts,
        'type' => $type ?? null,
        'banned' => $banned,
        'pager' => $pager,
        'keywords' => $keywords,
        'search_url' => $this->createWebUrl('account', ['banned' => $banned]),
    ]);

} elseif ($op == 'search') {

    $result = [];

    $query = Account::query();

    $keyword = request::trim('keyword', '', true);
    if ($keyword) {
        $query->whereOr([
            'name LIKE' => "%$keyword%",
            'title LIKE' => "%$keyword%",
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
            'order_limits' => max(0, request::int('orderlimits')),
            'order_no' => min(999, request::int('orderno')),
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
                $data['agent_id'] = $agent->getId();
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
                $data['url'] = Util::murl('jfb');
                $account->set('config', [
                    'type' => Account::JFB,
                    'url' => request::trim('apiURL'),
                    'auth' => request::bool('authUser'),
                    'scene' => request::trim('scene'),
                ]);
            } elseif ($account->isMoscale()) {
                $data['name'] = Account::MOSCALE_NAME;
                $data['img'] = Account::MOSCALE_HEAD_IMG;
                $data['url'] = Util::murl('moscale');
                $account->set('config', [
                    'type' => Account::MOSCALE,
                    'appid' => request::trim('appid'),
                    'appsecret' => request::trim('appsecret'),
                ]);
            } elseif ($account->isYunfenba()) {
                $data['name'] = Account::YUNFENBA_NAME;
                $data['img'] = Account::YUNFENBA_HEAD_IMG;
                $data['url'] = Util::murl('yunfenba');
                $account->set('config', [
                    'type' => Account::YUNFENBA,
                    'vendor' => [
                        'uid' => request::trim('vendorUID'),
                        'sid' => request::trim('vendorSubUID'),
                    ]
                ]);
            } elseif ($account->isAQiinfo()) {
                $data['name'] = Account::AQIINFO_NAME;
                $data['img'] = Account::AQIINFO_HEAD_IMG;
                $data['url'] = Util::murl('aqiinfo');
                $account->set('config', [
                    'type' => Account::AQIINFO,
                    'key' => request::trim('key'),
                    'secret' => request::trim('secret'),
                ]);
            } elseif ($account->isZJBao()) {
                $data['name'] = Account::ZJBAO_NAME;
                $data['img'] = Account::ZJBAO_HEAD_IMG;
                $data['url'] = Util::murl('zjbao');
                $account->set('config', [
                    'type' => Account::ZJBAO,
                    'key' => request::trim('key'),
                    'secret' => request::trim('secret'),
                ]);
            } elseif ($account->isMeiPa()) {
                $data['name'] = Account::MEIPA_NAME;
                $data['img'] = Account::MEIPA_HEAD_IMG;
                $data['url'] = Util::murl('meipa');
                $account->set('config', [
                    'type' => Account::MEIPA,
                    'apiid' => request::trim('apiid'),
                    'appkey' => request::trim('appkey'),
                ]);
            } elseif ($account->isKingFans()) {
                $data['name'] = Account::KINGFANS_NAME;
                $data['img'] = Account::KINGFANS_HEAD_IMG;
                $data['url'] = Util::murl('kingfans');
                $account->set('config', [
                    'type' => Account::KINGFANS,
                    'bid' => request::trim('bid'),
                    'key' => request::trim('key'),
                ]);
            } elseif ($account->isSNTO()) {
                $data['name'] = Account::SNTO_NAME;
                $data['img'] = Account::SNTO_HEAD_IMG;
                $data['url'] = Util::murl('snto');
                $account->set('config', [
                    'type' => Account::SNTO,
                    'id' => request::trim('app_id'),
                    'key' => request::trim('app_key'),
                    'channel' => request::trim('channel'),
                    'data' => $account->settings('config.data', []),
                ]);
            } elseif ($account->isYFB()) {
                $data['name'] = Account::YFB_NAME;
                $data['img'] = Account::YFB_HEAD_IMG;
                $data['url'] = Util::murl('yfb');
                $account->set('config', [
                    'type' => Account::YFB,
                    'id' => request::trim('app_id'),
                    'secret' => request::trim('app_secret'),
                    'key' => request::trim('key'),
                    'scene' => request::trim('scene'),
                ]);
            } elseif ($account->isWxWork()) {
                $data['name'] = Account::WxWORK_NAME;
                $data['img'] = Account::WxWORK_HEAD_IMG;
                $data['url'] = Util::murl('wxwork');
                $account->set('config', [
                    'type' => Account::WxWORK,
                    'key' => request::trim('key'),
                    'secret' => request::trim('secret'),
                ]);
            } elseif ($account->isYouFen()) {
                $data['name'] = Account::YOUFEN_NAME;
                $data['img'] = Account::YOUFEN_HEAD_IMG;
                $data['url'] = Util::murl('youfen');
                $account->set('config', [
                    'type' => Account::YOUFEN,
                    'app_number' => request::trim('app_number'),
                    'app_key' => request::trim('app_key'),
                    'followed_title' => request::trim('followed_title'),
                    'followed_description' => request::trim('followed_description'),
                ]);
            }
            elseif ($account->isMengMo()) {
                $data['name'] = Account::MENGMO_NAME;
                $data['img'] = Account::MENGMO_HEAD_IMG;
                $data['url'] = Util::murl('mengmo');
                $account->set('config', [
                    'type' => Account::MENGMO,
                    'app_no' => request::trim('app_no'),
                    'scene' => request::trim('scene'),
                ]);
            } elseif ($account->isWxApp()) {
                $data['img'] = request::trim('img');
                $account->set('config', [
                    'type' => Account::WXAPP,
                    'username' => request::trim('username'),
                    'path' => request::trim('path'),
                    'delay' => request::int('delay', 1),
                ]);
            } elseif ($account->isAuth()) {
                $data['img'] = request::trim('img');
                $timing = request::int('OpenTiming');
                if ($account->isSubscriptionAccount() || !$account->isVerified()) {
                    $timing = 1;
                }
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
                //如果网站更换域名后，需要更新url
                $data['url'] = Account::createUrl($account->getUid(), ['from' => 'account']);                
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
                //不再要求用户填写唯一的name
                do {
                    $name = Util::random(16, true);
                } while (Account::findOneFromName($name));

            } elseif (in_array($name, [
                Account::JFB_NAME,
                Account::MOSCALE_NAME,
                Account::YUNFENBA_NAME,
                Account::AQIINFO_NAME,
                Account::ZJBAO_NAME,
                Account::MEIPA_NAME,
                Account::KINGFANS_NAME,
                Account::SNTO_NAME,
                Account::YFB_NAME,
                Account::WxWORK_NAME,
                Account::YOUFEN_NAME,
                Account::MENGMO_NAME,
                Account::TASK_NAME,
            ])) {
                return err('名称 "' . $name . '" 是系统保留名称，无法使用！');
            }

            if (Account::findOneFromName($name)) {
                return err('公众号帐号已经存在！');
            }

            $uid = Account::makeUID($name);
            if (Account::findOneFromUID($uid)) {
                return err('公众号UID已经存在！');
            }

            $data['uid'] = $uid;
            $data['name'] = $name;
            $data['type'] = request::int('type');
            $data['title'] = request::str('title');
            $data['img'] = request::trim('img');
            $data['url'] = Account::createUrl($uid, ['from' => 'account']);

            $account = Account::create($data);
            if (empty($account)) {
                return err('创建公众号失败！');
            }
        }

        $account->setExtraData('update', [
            'time' => time(),
            'admin' => _W('username'),
        ]);

        //抖音吸粉总数永远为１
        if ($account->isDouyin()) {
            $account->setTotal(1);
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
                        'exclusive' => request::int('exclusive'),
                    ]
                ]);
            } elseif ($account->isDouyin()) {
                $account->set('config', [
                    'type' => Account::DOUYIN,
                    'url' => request::trim('url'),
                    'openid' => request::trim('openid'),
                ]);
            } elseif ($account->isWxApp()) {
                $account->set('config', [
                    'type' => Account::WXAPP,
                    'username' => request::trim('username'),
                    'path' => request::trim('path'),
                    'delay' => request::int('delay'),
                ]);
            }  elseif ($account->isTask()) {
                $account->set('config', [
                    'type' => Account::TASK,
                    'url' => request::trim('task_url'),
                    'qrcode' => request::trim('task_qrcode'),
                    'images' => request::array('task_images'),
                    'desc' => request::str('task_desc'),
                ]);
            }

            $commission_data = [];

            $original_bonus_type = $account->getBonusType();

            if (App::isCommissionEnabled()) {
                if (request::isset('commission_money')) {
                    $commission_data['money'] =  request::float('commission_money', 0, 2) * 100;
                } elseif (request::str('bonus_type') == Account::COMMISSION) {
                    $commission_data['money'] =  request::float('amount', 0, 2) * 100;
                }
            }
            
            // 积分
            if (App::isBalanceEnabled()) {
                if (request::isset('balance')) {
                    $commission_data['balance'] =  request::int('balance');
                } elseif (request::str('bonus_type') == Account::BALANCE) {
                    $commission_data['balance'] =  request::int('amount');
                }
            }

            //设置奖励
            $account->set('commission', $commission_data);
            
            //处理分配数据
            if ($account->getBonusType() != $original_bonus_type) {
                if ($original_bonus_type == Account::COMMISSION) {
                    //备份设置
                    $account->set('assigned_commission', $account->getAssignData());
                } else {
                  //备份设置
                  $account->setAssignData($account->get('assigned_commission', []));
                }
            }

            if ($account->getBonusType() == Account::BALANCE) {
                //分配到所有设备
                $account->setAssignData(['all' => 1]);
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

    if ($id) {
        $account = Account::get($id);
        if (empty($account)) {
            Util::itoast('公众号不存在！', $this->createWebUrl('account'), 'error');
        }

        $type = $account->getType();

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
            foreach ($qrcode_data as $entry) {
                $qr_codes[] = $entry['img'];
            }
        }

        $limits = $account->get('limits');

        $bonus_type = $account->getBonusType();
        if ($bonus_type == Account::COMMISSION) {
            $amount = number_format($account->getCommissionPrice() / 100, 2);
        } else {
            $amount = $account->getBalancePrice();
        }

        $config = $account->get('config');
    } else {
        $type = Account::NORMAL;
        $bonus_type = Account::COMMISSION;
    }

    app()->showTemplate('web/account/edit_' . $type, [
        'op' => $op,
        'type' => $type,
        'id' => $id,
        'account' => $account ?? null,
        'qrcodes' => $qr_codes ?? null,
        'limits' => $limits ?? null,
        'bonus_type' => $bonus_type,
        'amount' => $amount ?? 0,
        'balance' => $amount ?? 0,
        'agent_name' => $agent_name,
        'agent_mobile' => $agent_mobile,
        'agent_openid' => $agent_openid,
        'config' => $config,
    ]);

} elseif ($op == 'add') {

    $type = request::int('type', Account::NORMAL);
    app()->showTemplate('web/account/edit_' . $type, [
        'clr' => Util::randColor(),
        'op' => $op,
        'type' => $type,
    ]);

} elseif ($op == 'remove') {

    $id = request::int('id');
    if ($id) {
        $account = Account::get($id);
        if ($account) {
            if ($account->isThirdPartyPlatform()) {
                Util::itoast('删除失败！', $this->createWebUrl('account'), 'error');
            }
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
                $account->setState(Account::NORMAL);
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

    // if (App::isBalanceEnabled() && $account->getBonusType() == Account::BALANCE) {
    //     Util::itoast('积分奖励的公众号无法分配到指定设备！', $this->createWebUrl('account'), 'error');
    // }

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

    app()->showTemplate('web/account/assign', [
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
    $time_str = request::has('month') ? date('Y-') . request::int('month') . date('-01 00:00:00') : 'today';

    try {
        $month = new DateTime($time_str);
        $caption = $month->format('Y年n月');
        $data = Stats::chartDataOfMonth($acc, $month, "公众号：$title($caption)");
    } catch (Exception $e) {
    }

    $content = app()->fetchTemplate(
        'web/account/stats',
        [
            'chartid' => 'chart-' . Util::random(10),
            'chart' => $data ?? [],
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);

} elseif ($op == 'viewQueryLog') {

    $id = request::int('id');
    $acc = Account::get($id);

    if (empty($acc)) {
        JSON::fail('找不到这个公众号！');
    }

    $tpl_data = [
        'account' => $acc->profile(),
    ];

    $query = Account::logQuery($acc);

    if (request::has('device')) {
        $device_id = request::int('device');
        $device = Device::get($device_id);
        if (empty($device)) {
            Util::itoast('找不到这个设备！', '', 'error');
        }
        $tpl_data['device'] = $device->profile();
        $query->where(['device_id' => $device_id]);
    }

    if (request::has('user')) {
        $user_id = request::int('user');
        $user = User::get($user_id);
        if (empty($user)) {
            Util::itoast('找不到这个用户！', '', 'error');
        }
        $tpl_data['user'] = $user->profile();
        $query->where(['user_id' => $user_id]);
    }

    $total = $query->count();
    $list = [];

    if ($total > 0) {
        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        /** @var account_queryModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'request_id' => $entry->getRequestId(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            $acc = $entry->getAccount();
            if (!empty($acc)) {
                $data['account'] = $acc->profile();
            }

            $user = $entry->getUser();
            if ($user) {
                $data['user'] = $user->profile();
            }

            $device = $entry->getDevice();
            if ($device) {
                $data['device'] = $device->profile();
            }

            $data['request'] = $entry->getRequest();
            $data['result'] = $entry->getResult();
            $data['cb'] = $entry->getExtraData('cb');
            if ($data['cb']['serial']) {
                $log = BalanceLog::findOne(['s2' => $data['cb']['serial']]);
                if ($log) {
                    $data['balance'] = $log->getExtraData('bonus', 0);
                }
            }
            $last_cb = $entry->getExtraData('last_cb');
            if ($last_cb) {
                $data['last_cb'] = count($last_cb);
            }
            $data['createtime'] = $entry->getCreatetime();

            $list[] = $data;
        }
    }

    $tpl_data['list'] = $list;

    app()->showTemplate('web/account/log', $tpl_data);


} elseif ($op == 'viewFansCount') {

    $id = request::int('id');
    $acc = Account::get($id);

    if (empty($acc)) {
        JSON::fail('找不到这个公众号！');
    }

    $query = Order::query(['account' => $acc->getName()]);

    $num = (int)$query->get('count(DISTINCT `openid`)');

    JSON::success("{$acc->getTitle()}，净增粉丝总数：{$num}人");

} elseif ($op == 'douyinAuthorize') {

    $id = request::int('id');

    $account = Account::get($id);
    if (empty($account)) {
        JSON::fail('找不到这个公众号！');
    }

    $title = $account->getTitle();

    $url = Util::murl('douyin', [
        'op' => 'get_openid',
        'id' => $account->getId(),
    ]);

    $result = Util::createQrcodeFile("douyin.{$account->getId()}", DouYin::redirectToAuthorizeUrl($url, true));

    if (is_error($result)) {
        JSON::fail('创建二维码文件失败！');
    }

    $content = app()->fetchTemplate('web/common/qrcode', [
        'title' => '请用抖音扫描二维码完成授权！',
        'url' => Util::toMedia($result),
    ]);

    JSON::success([
        'title' => "$title",
        'content' => $content,
    ]);

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

    if (!$account->isAuth() || !$account->isServiceAccount()) {
        JSON::fail('只能是授权接入的服务号才能设置为屏幕二维码！');
    }

    $enable = $account->useAccountQRCode();
    if ($account->useAccountQRCode(!$enable)) {
        CtrlServ::appNotifyAll($account->getAssignData());
        JSON::success($enable ? '已取消成功！' : '已设置成功！');
    }

    JSON::fail('设置失败！');

} elseif ($op == 'commission_stats_view') {
    $account_id = request::int('id');
    $account = Account::get($account_id);

    if (empty($account)) {
        Util::itoast('找不到这个公众号或任务！', '', 'error');
    }

    app()->showTemplate('web/account/commission_stats_view', [
        'account' => $account,
    ]);

} elseif ($op == 'year_commission_statistics') {

    $account_id = request::int('id');
    $account = Account::get($account_id);

    if (empty($account)) {
        JSON::fail('找不到这个公众号或任务！');
    }

    $year_str = request::str('year');
    $month = request::int('month');

    $year = null;
    try {
        $year = new DateTimeImmutable(sprintf("%s-%02d-01", (new DateTime("$year_str-01-01"))->format('Y'), $month));
    } catch (Exception $e) {
        JSON::fail('时间格式不正确！');
    }

    if ($year->getTimestamp() > time()) {
        JSON::fail('时间不能超过当前时间！');
    }

    $result = [
        'title' => $year->format('Y年'),
        'list' => [],
        'summary' => [],
        'year' => [],
    ];

    $first_order = Order::getFirstOrderOf($account);
    if ($first_order) {
        try {
            $begin = new DateTime(date('Y-m-d H:i:s', $first_order['createtime']));
        } catch (Exception $e) {
            $begin = new DateTime();
        }
        $nextYear = new DateTime('first day of jan next year 00:00');
        while ($begin < $nextYear) {
            $result['year'][] = $begin->format('Y');
            $begin->modify('next year');
        }
        try {
            $order_date_obj = new DateTime(date('Y-m-01', $first_order['createtime']));
            $date = new DateTime("$year_str-$month-01 00:00");
            if ($date < $order_date_obj) {
                $result['title'] .= '*';
                JSON::success($result);
            }
        } catch (Exception $e) {
        }
    } else {
        $result['year'][] = (new DateTime())->format('Y');
        JSON::success($result);
    }

    $data = Statistics::accountYear($account, $year, $month);
    $result = array_merge($result, $data);

    JSON::success($result);
} elseif ($op == 'month_commission_statistics') {
    $account_id = request::int('id');
    $account = Account::get($account_id);

    if (empty($account)) {
        JSON::fail('找不到这个公众号或任务！');
    }

    $month_str = request::str('month');
    $day = request::int('day');

    $month = null;
    try {
        $month = new DateTimeImmutable($month_str);
    } catch (Exception $e) {
        JSON::fail('时间格式不正确！');
    }

    $result = [
        'title' => $month->format('Y年m月'),
        'list' => [],
        'summary' => [],
    ];

    $first_order = Order::getFirstOrderOf($account);
    if ($first_order) {
        try {
            $order_date_obj = new DateTime(date('Y-m-d', $first_order['createtime']));
            $date = new DateTime("$month_str-$day");
            if ($date < $order_date_obj) {
                $result['title'] .= '*';
                JSON::success($result);
            }
        } catch (Exception $e) {
        }
    } else {
        JSON::success($result);
    }

    $data = Statistics::accountMonth($account, $month, $day);
    $result = array_merge($result, $data);

    JSON::success($result);
}
