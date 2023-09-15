<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\business\FlashEgg;
use zovye\domain\Account;
use zovye\domain\Agent;
use zovye\util\DBUtil;
use zovye\util\QRCodeUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$res = DBUtil::transactionDo(function () {

    $id = Request::int('id');
    $name = Request::str('name');
    $qr_codes = Request::array('qrcodes');

    $data = [
        'title' => Request::str('title', $name),
        'descr' => Request::str('descr'),
        'qrcode' => Request::str('qrcode'),
        'clr' => Request::str('clr') ?: Util::randColor(),
        'count' => max(0, Request::int('count')),
        'sccount' => max(0, Request::int('sccount')),
        'total' => max(0, Request::int('total')),
        'order_limits' => max(0, Request::int('orderlimits')),
        'order_no' => min(999, Request::int('orderno')),
        'group_name' => Request::str('groupname'),
        'scname' => Request::str('scname', Account::DAY),
        'shared' => Request::has('commission_share') ? 1 : 0,
    ];

    if (!Account::has($data['scname'])) {
        return err('领取频率只是每天/每周/每月！');
    }

    //这里的agentId是用户openid
    if (Request::isset('agentId')) {
        $openid = Request::str('agentId');
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
                'url' => Request::trim('apiURL'),
                'auth' => Request::bool('authUser'),
                'scene' => Request::trim('scene'),
            ]);
        } elseif ($account->isMoscale()) {
            $data['name'] = Account::MOSCALE_NAME;
            $data['img'] = Account::MOSCALE_HEAD_IMG;
            $data['url'] = Util::murl('moscale');
            $account->set('config', [
                'type' => Account::MOSCALE,
                'appid' => Request::trim('appid'),
                'appsecret' => Request::trim('appsecret'),
            ]);

            updateSettings('moscale.fan.key', Request::trim('moscaleMachineKey'));
            updateSettings(
                'moscale.fan.label',
                array_map(function ($e) {
                    return intval($e);
                }, explode(',', Request::trim('moscaleLabel')))
            );
            updateSettings('moscale.fan.region', [
                'province' => Request::int('province_code'),
                'city' => Request::int('city_code'),
                'area' => Request::int('area_code'),
            ]);
        } elseif ($account->isYunfenba()) {
            $data['name'] = Account::YUNFENBA_NAME;
            $data['img'] = Account::YUNFENBA_HEAD_IMG;
            $data['url'] = Util::murl('yunfenba');
            $account->set('config', [
                'type' => Account::YUNFENBA,
                'vendor' => [
                    'uid' => Request::trim('vendorUID'),
                    'sid' => Request::trim('vendorSubUID'),
                ],
            ]);
        } elseif ($account->isAQiinfo()) {
            $data['name'] = Account::AQIINFO_NAME;
            $data['img'] = Account::AQIINFO_HEAD_IMG;
            $data['url'] = Util::murl('aqiinfo');
            $account->set('config', [
                'type' => Account::AQIINFO,
                'key' => Request::trim('key'),
                'secret' => Request::trim('secret'),
            ]);
        } elseif ($account->isZJBao()) {
            $data['name'] = Account::ZJBAO_NAME;
            $data['img'] = Account::ZJBAO_HEAD_IMG;
            $data['url'] = Util::murl('zjbao');
            $account->set('config', [
                'type' => Account::ZJBAO,
                'key' => Request::trim('key'),
                'secret' => Request::trim('secret'),
            ]);
        } elseif ($account->isMeiPa()) {
            $data['name'] = Account::MEIPA_NAME;
            $data['img'] = Account::MEIPA_HEAD_IMG;
            $data['url'] = Util::murl('meipa');
            $account->set('config', [
                'type' => Account::MEIPA,
                'apiid' => Request::trim('apiid'),
                'appkey' => Request::trim('appkey'),
                'region' => [
                    'code' => [
                        'province' => Request::trim('province'),
                        'city' => Request::trim('city'),
                        'area' => Request::trim('area'),
                    ],
                ],
            ]);
        } elseif ($account->isKingFans()) {
            $data['name'] = Account::KINGFANS_NAME;
            $data['img'] = Account::KINGFANS_HEAD_IMG;
            $data['url'] = Util::murl('kingfans');
            $account->set('config', [
                'type' => Account::KINGFANS,
                'bid' => Request::trim('bid'),
                'key' => Request::trim('key'),
            ]);
        } elseif ($account->isSNTO()) {
            $data['name'] = Account::SNTO_NAME;
            $data['img'] = Account::SNTO_HEAD_IMG;
            $data['url'] = Util::murl('snto');
            $account->set('config', [
                'type' => Account::SNTO,
                'id' => Request::trim('app_id'),
                'key' => Request::trim('app_key'),
                'channel' => Request::trim('channel'),
                'data' => $account->settings('config.data', []),
            ]);
        } elseif ($account->isYFB()) {
            $data['name'] = Account::YFB_NAME;
            $data['img'] = Account::YFB_HEAD_IMG;
            $data['url'] = Util::murl('yfb');
            $account->set('config', [
                'type' => Account::YFB,
                'id' => Request::trim('app_id'),
                'secret' => Request::trim('app_secret'),
                'key' => Request::trim('key'),
                'scene' => Request::trim('scene'),
            ]);
        } elseif ($account->isWxWork()) {
            $data['name'] = Account::WxWORK_NAME;
            $data['img'] = Account::WxWORK_HEAD_IMG;
            $data['url'] = Util::murl('wxwork');
            $account->set('config', [
                'type' => Account::WxWORK,
                'key' => Request::trim('key'),
                'secret' => Request::trim('secret'),
            ]);
        } elseif ($account->isYouFen()) {
            $data['name'] = Account::YOUFEN_NAME;
            $data['img'] = Account::YOUFEN_HEAD_IMG;
            $data['url'] = Util::murl('youfen');
            $account->set('config', [
                'type' => Account::YOUFEN,
                'app_number' => Request::trim('app_number'),
                'app_key' => Request::trim('app_key'),
                'followed_title' => Request::trim('followed_title'),
                'followed_description' => Request::trim('followed_description'),
            ]);
        } elseif ($account->isMengMo()) {
            $data['name'] = Account::MENGMO_NAME;
            $data['img'] = Account::MENGMO_HEAD_IMG;
            $data['url'] = Util::murl('mengmo');
            $account->set('config', [
                'type' => Account::MENGMO,
                'app_no' => Request::trim('app_no'),
                'scene' => Request::trim('scene'),
            ]);
        } elseif ($account->isYiDao()) {
            $data['name'] = Account::YIDAO_NAME;
            $data['img'] = Account::YIDAO_HEAD_IMG;
            $data['url'] = Util::murl('yidao');
            $account->set('config', [
                'type' => Account::YIDAO,
                'appid' => Request::trim('appid'),
                'app_secret' => Request::trim('app_secret'),
                'device_key' => Request::trim('device_key'),
                'scene' => Request::trim('scene'),
            ]);
        } elseif ($account->isWeiSure()) {
            $data['name'] = Account::WEISURE_NAME;
            $data['img'] = Account::WEISURE_HEAD_IMG;
            $data['url'] = Util::murl('weisure');
            $data['total'] = 1;
            $config = [
                'type' => Account::WEISURE,
                'companyId' => Request::trim('companyId'),
                'wtagid' => Request::trim('wtagid'),
                'h5url' => Request::trim('h5url'),
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
                'key' => Request::trim('key'),
                'channel' => Request::trim('channel'),
                'scene' => Request::trim('scene'),
                'area' => Request::trim('area'),
            ];
            $account->set('config', $config);
        } elseif ($account->isWxApp()) {
            $data['img'] = Request::trim('img');
            $account->set('config', [
                'type' => Account::WXAPP,
                'username' => Request::trim('username'),
                'path' => Request::trim('path'),
                'delay' => Request::int('delay', 1),
            ]);
        } elseif ($account->isAuth()) {
            $data['img'] = Request::trim('img');
            $timing = Request::int('OpenTiming');
            if ($account->isSubscriptionAccount() || !$account->isVerified()) {
                $timing = 1;
            }
            $config = [
                'type' => Account::AUTH,
            ];
            if (Request::str('openMsgType') == 'text') {
                $config['open'] = [
                    'timing' => $timing,
                    'msg' => Request::str('openTextMsg'),
                ];
            } elseif (Request::str('openMsgType') == 'news') {
                $config['open'] = [
                    'timing' => $timing,
                    'news' => [
                        'title' => Request::trim('openNewsTitle'),
                        'desc' => Request::trim('openNewsDesc'),
                        'image' => Request::trim('openNewsImage'),
                    ],
                ];
            }
            $account->set('config', $config);
        } else {
            $data['img'] = Request::trim('img');
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
            return err('任务账号已经存在！');
        }

        $uid = Account::makeUID($name);
        if (Account::findOneFromUID($uid)) {
            return err('任务UID已经存在！');
        }

        $data['uid'] = $uid;
        $data['name'] = $name;
        $data['type'] = Request::int('type');
        $data['title'] = Request::str('title');
        $data['img'] = Request::trim('img');
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

        if (Request::has('limits')) {
            $arr = Request::array('limits');
            foreach ($limits as $name => &$v) {
                if (in_array($name, $arr)) {
                    $v = 1;
                }
            }
        }

        if (Request::has('area')) {
            $limits['area'] = Request::array('area');
        }

        $account->set('limits', $limits);

        if (Request::isset('questionnaire')) {
            $questionnaire_uid = Request::trim('questionnaire');
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
                    'duration' => Request::int('duration', 1),
                    'exclusive' => Request::int('exclusive'),
                ],
            ]);
        } elseif ($account->isDouyin()) {
            $account->set('config', [
                'type' => Account::DOUYIN,
                'url' => Request::trim('url'),
                'openid' => Request::trim('openid'),
            ]);
        } elseif ($account->isWxApp()) {
            $account->set('config', [
                'type' => Account::WXAPP,
                'username' => Request::trim('username'),
                'path' => Request::trim('path'),
                'delay' => Request::int('delay'),
            ]);
        } elseif ($account->isTask()) {
            $account->set('config', [
                'type' => Account::TASK,
                'url' => Request::trim('task_url'),
                'qrcode' => Request::trim('task_qrcode'),
                'images' => Request::array('task_images'),
                'desc' => Request::str('task_desc'),
            ]);
        } elseif ($account->isQuestionnaire()) {
            $questions = json_decode(Request::str('questionsJSON', '', true), true);
            $account->set('config', [
                'type' => Account::QUESTIONNAIRE,
                'questions' => $questions,
                'score' => Request::int('score'),
            ]);

            $qrcode_url = QRCodeUtil::createFile("question.{$account->getUid()}", $account->getUrl());
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
            if (Request::isset('commission_money')) {
                $commission_data['money'] = Request::float('commission_money', 0, 2) * 100;
            } elseif (Request::str('bonus_type') == Account::COMMISSION) {
                $commission_data['money'] = Request::float('amount', 0, 2) * 100;
            }
        }

        // 积分
        if (App::isBalanceEnabled()) {
            if (Request::isset('balance')) {
                $commission_data['balance'] = Request::int('balance');
            } elseif (Request::str('bonus_type') == Account::BALANCE) {
                $commission_data['balance'] = Request::int('amount');
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
    Response::toast($res['message'], We7::referer(), 'error');
} else {
    $id = Request::int('id', $res['id']);
    $back_url = Util::url('account', ['op' => 'edit', 'id' => $id, 'from' => Request::str('from')]);
    Response::toast($res['message'], $back_url, 'success');
}