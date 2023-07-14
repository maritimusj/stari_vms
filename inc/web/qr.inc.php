<?php
namespace zovye;

use ZipArchive;

$template_url = "https://xxx.com/app/index.php?i=2&c=entry&app=NULL&from=device&device={uid}&do=entry&m=zy_saas";

$list = [
    //加入需要创建二维码的网址
];

$zip = new ZipArchive();
$filename =  date('YmdHIs').'.zip';
$zip_filename = ATTACHMENT_ROOT . $filename;
$zip->open($zip_filename, ZipArchive::CREATE);

foreach($list as $uid) {
    $url = str_replace('{uid}', $uid, $template_url);
    QRCodeUtil::createFile("device.$uid", $url, function ($filename) use($uid, $zip) {
        QRCodeUtil::renderTxt($filename, $uid);
        $zip->addFile($filename, "$uid.png");
    });
}

$zip->close();

Response::redirect(Util::toMedia($filename));

