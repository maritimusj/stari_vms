<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    if (Advertising::remove($id, Advertising::ACTIVE_QRCODE)) {
        Response::toast('删除成功！', Util::url('qrcode'), 'success');
    }
}

Response::toast('删除失败！', Util::url('qrcode'), 'error');