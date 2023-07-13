<?php
namespace zovye;

use ZipArchive;

$url_tmpl = "https://xxx.com/app/index.php?i=2&c=entry&app=NULL&from=device&device={uid}&do=entry&m=zy_saas";

$list = [

];

$zip = new ZipArchive();
$filename =  date('YmdHIs').'.zip';
$zip_filename = ATTACHMENT_ROOT . $filename;
$zip->open($zip_filename, ZipArchive::CREATE);

foreach($list as $uid) {
    $url = str_replace("{uid}", $uid, $url_tmpl);
    Util::createQrcodeFile("device.$uid", $url, function ($filename) use($uid, $zip) {
        Util::renderTxt($filename, $uid);
        $zip->addFile($filename, "$uid.png");
    });
}

$zip->close();

Response::redirect(Util::toMedia($filename));

