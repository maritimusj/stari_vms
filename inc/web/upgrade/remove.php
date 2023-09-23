<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Apk;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $v = Apk::get($id);
    if ($v && $v->destroy()) {
        exit('ok');
    }
}

exit('fail');
