<?php

namespace zovye;

$id = request::int('id');

$account = Account::get($id);
if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

$title = $account->getTitle();

$url = Util::murl('douyin', [
    'op' => 'get_openid',
    'id' => $account->getId(),
]);

$result = Util::createQrcodeFile("douyin.{$account->getId()}", DouYin::redirectToAuthorizeUrl($url, true));

if (is_error($result)) {
    JSON::fail('创建二维码文件失败！');
}

$content = app()->fetchTemplate('web/common/qrcode', [
    'title' => '请用抖音扫描二维码完成授权！',
    'url' => Util::toMedia($result),
]);

JSON::success([
    'title' => "$title",
    'content' => $content,
]);