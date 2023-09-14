<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$title = Request::trim('title');
$url = Request::trim('url');
$version = Request::trim('version');

if ($url && $version) {
    if (m('version')->create(
        We7::uniacid(
            [
                'title' => $title,
                'url' => $url,
                'version' => $version,
            ]
        )
    )) {
        Response::toast('保存成功！', Util::url('upgrade'), 'success');
    }
}

Response::toast('保存失败！', Util::url('upgrade'), 'error');