<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Advertising;
use zovye\domain\Device;
use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

/**
 * 更多关注页面.
 *
 * @param array $params
 */
$params = TemplateUtil::getTemplateVar();
$tpl = is_array($params) ? $params : [];

if ($tpl['device']['id']) {
    $device = Device::get($tpl['device']['id']);
    if ($device) {
        $tpl['slides'] = [];
        $ads = $device->getAds(Advertising::WELCOME_PAGE);
        foreach ($ads as $adv) {
            if ($adv['extra']['images']) {
                foreach ($adv['extra']['images'] as $image) {
                    if ($image) {
                        $tpl['slides'][] = [
                            'id' => intval($adv['id']),
                            'name' => strval($adv['name']),
                            'image' => strval(Util::toMedia($image)),
                            'link' => strval($adv['extra']['link']),
                        ];
                    }
                }
            }
        }
    }
}

$js_sdk = Util::jssdk();

$api_url = Util::murl('util', ['op' => 'accounts', 'id' => $tpl['device']['shadowId']]);

$we7_util_url = JS_WE7UTIL_URL;
$jquery_url = JS_JQUERY_URL;

$tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>
$js_sdk
<script>
</script>
JSCODE;

Response::showTemplate('accounts', ['tpl' => $tpl, 'url' => $api_url], true);