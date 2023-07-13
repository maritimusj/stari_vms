<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$voucher = GoodsVoucher::get($id);
if ($voucher) {
    app()->showTemplate('web/goods_voucher/assign', [
        'voucher' => GoodsVoucher::format($voucher, true),
        'multi_mode' => settings('advs.assign.multi') ? 'true' : '',
        'assign_data' => json_encode($voucher->getExtraData('assigned', [])),
        'agent_url' => $this->createWebUrl('agent'),
        'group_url' => $this->createWebUrl('device', array('op' => 'group')),
        'tag_url' => $this->createWebUrl('tags'),
        'device_url' => $this->createWebUrl('device'),
        'save_url' => $this->createWebUrl('voucher', array('op' => 'saveAssignData')),
        'back_url' => $this->createWebUrl('voucher'),
    ]);
}

Response::itoast('找不到指定的提货码！', Util::url('voucher'), 'error');