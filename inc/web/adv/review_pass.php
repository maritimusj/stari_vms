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
    Response::itoast('广告已经通过审核！', $this->createWebUrl('adv', ['type' => $type]), 'success');
}

Response::itoast('审核操作失败！', $this->createWebUrl('adv', ['type' => $type]), 'error');