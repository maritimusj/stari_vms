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

    $url_params = ['op' => 'result', 'orderNO' => $order_no];
    if (request::has('balance')) {
        $url_params['balance'] = 1;
    }

    $file = Theme::getThemeFile($device,'payresult');
    app()->showTemplate($file, [
        'tpl' => $tpl,
        'url' => Util::murl('order', $url_params),
        'idcard_url' => Util::murl('idcard', ['orderNO' => $order_no]),
    ]);

} elseif ($op == 'SQB') {

    if (request::trim('is_success') == 'T' && request::str('status') == 'SUCCESS') {
        Util::resultAlert('支付成功！');
    }

    Util::resultAlert(request::trim('error_message', '支付失败！'), 'error');
    
} elseif ($op == 'notify') {

    Log::debug('payresult', [
        'from' => $_GET['from'],
        'raw' => request::raw(),
    ]);

    $res = Pay::notify($_GET['from'], request::raw());

    exit($res);
}

