<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$res = Util::transactionDo(function () {

    $id = request::int('id');
    $name = request::str('name');
    $qr_codes = request::array('qrcodes');

    $data = [
        'title' => request::str('title', $name),
        'descr' => request::str('descr'),
        'qrcode' => request::str('qrcode'),
        'clr' => request::str('clr') ?: Util::randColor(),
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
    //是否关联了问卷任务
    $questionnaire_attached = null;
    if ($id) {
        $account = Account::get($id);
        if (empty($account)) {
            return err('找不到这个任务！');
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

            updateSettings('moscale.fan.key', request::trim('moscaleMachineKey'));
            updateSettings(
                'moscale.fan.label',
                array_map(function ($e) {
                    return intval($e);
                }, explode(',', request::trim('moscaleLabel')))
            );
            updateSettings('moscale.fan.region', [
                'province' => request::int('province_code'),
                'city' => request::int('city_code'),
                'area' => request::int('area_code'),
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
                ],
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
                'region' => [
                    'code' => [
                        'province' => request::trim('province'),
                        'city' => request::trim('city'),
                        'area' => request::trim('area'),
                    ],
                ],
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
        } elseif ($account->isMengMo()) {
            $data['name'] = Account::MENGMO_NAME;
            $data['img'] = Account::MENGMO_HEAD_IMG;
            $data['url'] = Util::murl('mengmo');
            $account->set('config', [
                'type' => Account::MENGMO,
                'app_no' => request::trim('app_no'),
                'scene' => request::trim('scene'),
            ]);
        } elseif ($account->isYiDao()) {
            $data['name'] = Account::YIDAO_NAME;
            $data['img'] = Account::YIDAO_HEAD_IMG;
            $data['url'] = Util::murl('yidao');
            $account->set('config', [
                'type' => Account::YIDAO,
                'appid' => request::trim('appid'),
                'app_secret' => request::trim('app_secret'),
                'device_key' => request::trim('device_key'),
                'scene' => request::trim('scene'),
            ]);
        } elseif ($account->isWeiSure()) {
            $data['name'] = Account::WEISURE_NAME;
            $data['img'] = Account::WEISURE_HEAD_IMG;
            $data['url'] = Util::murl('weisure');
            $data['total'] = 1;
            $config = [
                'type' => Account::WEISURE,
                'companyId' => request::trim('companyId'),
                'wtagid' => request::trim('wtagid'),
                'h5url' => request::trim('h5url'),
            ];
            if ($config['h5url']) {
                $parsed_url = parse_url($config['h5url']);
                parse_str($parsed_url['query'], $parsed_query);
                $parsed_url['query'] = $parsed_query;
                $config['parsed_h5url'] = $parsed_url;
            }
            $account->set('config', $config);
        } elseif ($account->isCloudFI()) {
            $data['name'] = Account::CloudFI_NAME;
            $data['img'] = Account::CloudFI_HEAD_IMG;
            $data['url'] = Util::murl('cloudfi');
            $config = [
                'type' => Account::CloudFI,
                'key' => request::trim('key'),
                'channel' => request::trim('channel'),
                'scene' => request::trim('scene'),
                'area' => request::trim('area'),
            ];
            $account->set('config', $config);
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
            $key_name = 'get'.ucfirst(toCamelCase($key));
            if ($val != $account->$key_name()) {
                $set_name = 'set'.ucfirst(toCamelCase($key));
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
            Account::YIDAO_NAME,
            Account::WEISURE_NAME,
            Account::TASK_NAME,
        ])) {
            return err('名称 "'.$name.'" 是系统保留名称，无法使用！');
        }

        if (Account::findOneFromName($name)) {
            return err('任务帐号已经存在！');
        }

        $uid = Account::makeUID($name);
        if (Account::findOneFromUID($uid)) {
            return err('任务UID已经存在！');
        }

        $data['uid'] = $uid;
        $data['name'] = $name;
        $data['type'] = request::int('type');
        $data['title'] = request::str('title');
        $data['img'] = request::trim('img');
        $data['url'] = Account::createUrl($uid, ['from' => 'account']);

        $account = Account::create($data);
        if (empty($account)) {
            return err('创建任务失败！');
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
                $xid = sha1($qr.Util::random(8));
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

        if (request::isset('questionnaire')) {
            $questionnaire_uid = request::trim('questionnaire');
            if ($questionnaire_uid) {
                $questionnaire = Account::findOneFromUID($questionnaire_uid);
                if ($questionnaire) {
                    $account->setConfig('questionnaire', [
                        'uid' => $questionnaire->getUid(),
                        'url' => Account::createUrl($questionnaire->getUid(), ['tid' => $account->getUid()]),
                    ]);
                }
            }

            if (isset($questionnaire)) {
                $questionnaire_attached = true;
            } else {
                if ($account->getConfig('questionnaire')) {
                    $questionnaire_attached = false;
                    $account->setConfig('questionnaire');
                }
            }
        }

        if ($account->isVideo()) {
            $account->set('config', [
                'type' => Account::VIDEO,
                'video' => [
                    'duration' => request::int('duration', 1),
                    'exclusive' => request::int('exclusive'),
                ],
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
        } elseif ($account->isTask()) {
            $account->set('config', [
                'type' => Account::TASK,
                'url' => request::trim('task_url'),
                'qrcode' => request::trim('task_qrcode'),
                'images' => request::array('task_images'),
                'desc' => request::str('task_desc'),
            ]);
        } elseif ($account->isQuestionnaire()) {
            $questions = json_decode(request::str('questionsJSON', '', true), true);
            $account->set('config', [
                'type' => Account::QUESTIONNAIRE,
                'questions' => $questions,
                'score' => request::int('score'),
            ]);

            $qrcode_url = Util::createQrcodeFile("question.{$account->getUid()}", $account->getUrl());
            if (is_error($qrcode_url)) {
                return err('二维码生成失败！');
            }
            $account->setQrcode($qrcode_url);
            $account->save();

        } elseif ($account->isFlashEgg()) {
            $res = FlashEgg::createOrUpdate($account, $GLOBALS['_GPC']);
            if (is_error($res)) {
                return $res;
            }
        }

        $commission_data = [];

        $original_bonus_type = $account->getBonusType();

        if (App::isCommissionEnabled()) {
            if (request::isset('commission_money')) {
                $commission_data['money'] = request::float('commission_money', 0, 2) * 100;
            } elseif (request::str('bonus_type') == Account::COMMISSION) {
                $commission_data['money'] = request::float('amount', 0, 2) * 100;
            }
        }

        // 积分
        if (App::isBalanceEnabled()) {
            if (request::isset('balance')) {
                $commission_data['balance'] = request::int('balance');
            } elseif (request::str('bonus_type') == Account::BALANCE) {
                $commission_data['balance'] = request::int('amount');
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

        $message = '保存成功！';
        if ($commission_share_closed) {
            $message .= '注意：所有平台代理商关联已被移除！';
        }

        if (isset($questionnaire_attached)) {
            $message .= ($questionnaire_attached ? '注意：已关联问卷，请重新设置取货链接！' : '注意：已移除问卷，请重新设置取货链接！');
        }

        return ['message' => $message, 'id' => $account->getId()];
    }

    return err('操作失败！');
});

if (is_error($res)) {
    Util::itoast($res['message'], We7::referer(), 'error');
} else {
    $id = request::int('id', $res['id']);
    $back_url = $this->createWebUrl('account', ['op' => 'edit', 'id' => $id, 'from' => request::str('from')]);
    Util::itoast($res['message'], $back_url, 'success');
}