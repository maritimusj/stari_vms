<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$type = request::int('type');

if (Advertising::reject($id)) {
    Util::itoast('广告已经被设置为拒绝通过！', $this->createWebUrl('adv', ['type' => $type]), 'success');
}

Util::itoast('审核操作失败！', $this->createWebUrl('adv', ['type' => $type]), 'error');