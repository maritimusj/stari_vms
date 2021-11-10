<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {
    $order_no = request::str('orderNO');
    $tpl = Util::getTplData(
        [
            [
                'timeout' => App::deviceWaitTimeout(),
                'slides' => [],
            ],
        ]
    );

    $device = Device::get(request::int('deviceid'));
    if ($device) {
        //广告列表
        $advs = $device->getAdvs(Advertising::GET_PAGE);
        foreach ($advs as $adv) {
            if ($adv['extra']['images']) {
                foreach ($adv['extra']['images'] as $image) {
                    if ($image) {
                        $tpl['slides'][] = [
                            'id' => intval($adv['id']),
                            'name' => strval($adv['name']),
                            'image' => strval(Util::toMedia($image)),
                            'url' => strval($adv['extra']['link']),
                        ];
                    }
                }
            }
        }

        //失败转跳
        $tpl['redirect'] = [
            'fail' => $device->getRedirectUrl('fail')['url'],
            'success' => $device->getRedirectUrl()['url'],
        ];

        $agent = $device->getAgent();
        if ($agent) {
            $tpl['mobile'] = $agent->getMobile();
        }
    }

    app()->showTemplate(Theme::file('payresult'), [
        'tpl' => $tpl,
        'url' => Util::murl('order', ['op' => 'result', 'orderNO' => $order_no]),
        'idcard_url' => Util::murl('idcard', ['orderNO' => $order_no]),
    ]);

} elseif ($op == 'SQB') {

    if (request::trim('is_success') == 'T') {
        Util::resultAlert('支付成功！');
    }

    Util::resultAlert(request::trim('error_message'), 'error');
    
} elseif ($op == 'notify') {

    Util::logToFile('payresult', [
        'from' => $_GET['from'],
        'raw' => request::raw(),
    ]);

    if ($_GET['from'] == 'channel') {
        $res = Pay::notifyChannel(request::json());
    } else {
        $res = Pay::notify($_GET['from'], request::raw()); 
    }

    exit($res);
}

