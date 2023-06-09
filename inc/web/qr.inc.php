<?php
namespace zovye;

use ZipArchive;

$url_tmpl = "https://saas.jspercent.com/app/index.php?i=2&c=entry&app=NULL&from=device&device={uid}&do=entry&m=zy_saas";

$list = [
"863650067311587",
"863650067312874",
"863650067312890",
"863650067313245",
"863650067311066",
"863650067311496",
"862408069636266",
"863650067310969",
"863650067536589",
"863650067536159",
"863650067311108",
"863650067537355",
"863650067303378",
"863650067303519",
"863650067312080",
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

Util::redirect(Util::toMedia($filename));

