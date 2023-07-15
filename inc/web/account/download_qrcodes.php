<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$lucky = FlashEgg::getLucky($id);

if (empty($lucky)) {
    Response::alert('找不到这个抽奖活动！');
}

$num = Request::int('num', 10);

//简单的二维码导出功能
use ZipArchive;

$attach_prefix = ATTACHMENT_ROOT;

$zip = new ZipArchive();

$zip_file_name = 'zovye/'.date('YmdHis').'.zip';
$zip->open($attach_prefix.$zip_file_name, ZipArchive::CREATE);   //打开压缩包

for ($i = 0; $i < $num; $i++) {
    $serial = sprintf('%s%04d', TIMESTAMP, $i + 1);
    $secret = hash_hmac('sha256', "$id.$serial", App::secret());

    $qrcode_url = Util::murl('account', ['op' => 'lucky', 'code' => base64_encode("$id:$serial:$secret")]);

    $qrcode_file = QRCodeUtil::createFile("lucky.$serial", $qrcode_url, function ($filename) use ($serial) {
        QRCodeUtil::renderTxt($filename, $serial);
    });

    if (is_error($qrcode_file)) {
        JSON::fail('生成二难码文件失败！');
    }

    $zip->addFile($attach_prefix.$qrcode_file, basename($qrcode_file));
}

$zip->close();

JSON::success([
    'url' => Util::toMedia($zip_file_name),
]);