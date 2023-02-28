<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$type = Request::int('type');

if (Advertising::pass($id, _W('username'))) {
    Util::itoast('广告已经通过审核！', $this->createWebUrl('adv', ['type' => $type]), 'success');
}

Util::itoast('审核操作失败！', $this->createWebUrl('adv', ['type' => $type]), 'error');