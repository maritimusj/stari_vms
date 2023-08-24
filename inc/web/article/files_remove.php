<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $archive = m('files')->findOne(We7::uniacid(['id' => $id]));
    if ($archive && $archive->destroy()) {
        Response::toast('删除成功！', Util::url('article', ['op' => 'files']), 'sucess');
    }
}

Response::toast('删除失败！', Util::url('article', ['op' => 'files']), 'error');