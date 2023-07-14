<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$account = Account::get($id);
if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

$title = $account->getTitle();

$url = Util::murl('douyin', [
    'op' => 'get_openid',
    'id' => $account->getId(),
]);

$result = QRCodeUtil::createFile("douyin.{$account->getId()}", DouYin::redirectToAuthorizeUrl($url, true));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

Response::templateJSON('web/common/qrcode',
    $title,
    [
    'title' => '请用抖音扫描二维码完成授权！',
    'url' => Util::toMedia($result),
]);