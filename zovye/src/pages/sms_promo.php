<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\deviceModelObj;

$params = Util::getTemplateVar();

/** @var deviceModelObj $device */
$device = Device::get($params['device'], true);
if (empty($device)) {
    Response::alert('找不到这个设备！', 'error');
}

$tpl_data = [];
$api_url = Util::murl('promo', ['device' => $device->getImei()]);
$result_url = Util::murl('order', ['op' => 'result']);
$jquery_url = JS_JQUERY_URL;

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script>

    const zovye_fn = {
        api_url: "$api_url",
        result_url: "$result_url",
    }

    zovye_fn.send = function(mobile) {
        return $.getJSON(zovye_fn.api_url, {op: "sms", mobile});
    }

    zovye_fn.verify = function(mobile, code, num) {
        return $.getJSON(zovye_fn.api_url, {op: "verify", mobile, code, num});
    }

    zovye_fn.result = function(mobile, orderNO) {
        return $.getJSON(zovye_fn.result_url, {openid: mobile, orderNO});
    }

</script>
JSCODE;
$filename = Theme::getThemeFile($device, 'device');
$this->showTemplate($filename, ['tpl' => $tpl_data]);
