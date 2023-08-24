<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$type = Request::int('type');

if (Advertising::pass($id, _W('username'))) {
    Response::toast('广告已经通过审核！', Util::url('adv', ['type' => $type]), 'success');
}

Response::toast('审核操作失败！', Util::url('adv', ['type' => $type]), 'error');