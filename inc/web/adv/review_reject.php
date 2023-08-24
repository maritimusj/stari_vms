<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$type = Request::int('type');

if (Advertising::reject($id)) {
    Response::toast('广告已经被设置为拒绝通过！', Util::url('adv', ['type' => $type]), 'success');
}

Response::toast('审核操作失败！', Util::url('adv', ['type' => $type]), 'error');