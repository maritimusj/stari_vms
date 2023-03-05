<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (!App::isFlashEggEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$code = Request::str('code');
if (empty($code)) {
    Util::resultAlert('请求的中奖数据校验失败！', 'error');
}

$str = base64_decode($code);
if (empty($code)) {
    Util::resultAlert('请求的中奖数据校验失败！', 'error');
}

list($id, $serial, $secret) = explode(':', $str);

if (empty($id) || empty($serial) || empty($secret) || hash_hmac('sha256', "$id.$serial", App::secret()) !== $secret) {
    Util::resultAlert('请求的中奖数据校验失败！', 'error');
}

$lucky = FlashEgg::getLucky($id);
if (empty($lucky)) {
    Util::resultAlert('对不起，找不到这个抽奖活动！', 'error');
}

if (!$lucky->isEnabled()) {
    Util::resultAlert('对不起，这个抽奖活动已停用！', 'error');
}

Util::resultAlert($lucky->getName() . ':' . $serial);