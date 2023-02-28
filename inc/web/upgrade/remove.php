<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
if ($id) {
    $v = m('version')->findOne(We7::uniacid(['id' => $id]));
    if ($v && $v->destroy()) {
        exit('ok');
    }
}

exit('fail');
