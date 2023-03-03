<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;

/** @var deviceModelObj $device */
$device = Util::getTemplateVar('device');

/** @var string $order_no */
$order_no = Util::getTemplateVar('order_no');

$tpl_data = Util::getTplData(
    [
        [
            'timeout' => App::getDeviceWaitTimeout(),
            'slides' => [],
        ],
    ]
);

if ($device) {
    //广告列表
    $ads = $device->getAds(Advertising::GET_PAGE);
    foreach ($ads as $adv) {
        if ($adv['extra']['images']) {
            foreach ($adv['extra']['images'] as $image) {
                if ($image) {
                    $tpl_data['slides'][] = [
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
    $tpl_data['redirect'] = [
        'fail' => $device->getRedirectUrl('fail')['url'],
        'success' => $device->getRedirectUrl()['url'],
    ];

    $agent = $device->getAgent();
    if ($agent) {
        $tpl_data['mobile'] = $agent->getMobile();
    }
}

$url_params = ['op' => 'result', 'orderNO' => $order_no];
if (Request::has('balance')) {
    $url_params['balance'] = 1;
}

$order_api_url = Util::murl('order', ['orderNO' => $order_no]);
$jquery_url = JS_JQUERY_URL;

$js_code = <<<CODE
<script src="$jquery_url"></script>
<script>
const zovye_fn = {};

zovye_fn.getResult = function() {
  return $.getJSON("$order_api_url", {op: 'result'});
}
</script>
CODE;

$tpl_data['js']['code'] = $js_code;

$file = Theme::getThemeFile($device, 'payresult');
app()->showTemplate($file, [
    'tpl' => $tpl_data,
    'url' => Util::murl('order', $url_params),
    'idcard_url' => Util::murl('idcard', ['orderNO' => $order_no]),
]);