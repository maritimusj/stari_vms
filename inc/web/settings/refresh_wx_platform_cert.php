<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\PaymentConfig;
use zovye\util\PayUtil;

defined('IN_IA') or exit('Access Denied');

$config = PaymentConfig::getDefaultByName(Pay::WX_V3);
if (!$config) {
    JSON::fail('请先设置其它必要参数，再请求更新微信平台证书！');
}

$res = PayUtil::getWxPlatformCertificate($config->toArray());

if (is_error($res)) {
    JSON::fail($res['message']);
}

$config->setExtraData('pem.cert', $res);
$config->save();

JSON::success('微信平台证书已更新！');