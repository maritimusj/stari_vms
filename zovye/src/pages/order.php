<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

/** @var string $user */
$user = TemplateUtil::getTemplateVar();

$api_url = Util::murl('order', ['user' => $user]);
$jquery_url = JS_JQUERY_URL;

$js_code = <<<CODE
<script src="$jquery_url"></script>
<script>
const zovye_fn = {};

zovye_fn.get_list = function(way, page, pagesize) {
  return new Promise((resolve) => {
     $.getJSON("$api_url", {op: 'list', way, page, pagesize}).then(function(res) {
        resolve(res);
     });
  });
}

zovye_fn.get_free_list = function(page, pagesize) {
    return zovye_fn.get_list('free', page, pagesize);
}

zovye_fn.get_fee_list = function(page, pagesize) {
    return zovye_fn.get_list('pay', page, pagesize);
}

zovye_fn.get_order_detail =function(orderNO) {
    return new Promise((resolve, reject) => {
        $.getJSON("$api_url", {op: 'detail', orderNO}).then(function(res) {
            if (res && res.status) {
                resolve(res);
            } else {
                reject(res && res.data.msg ? res.data.msg : '请求失败！');
            }
        });
    });
}
</script>
CODE;

$tpl_data = TemplateUtil::getTplData();
$tpl_data['js']['code'] = $js_code;
Response::showTemplate('order', ['tpl' => $tpl_data], true);