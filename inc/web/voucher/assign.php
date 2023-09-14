<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\GoodsVoucher;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$voucher = GoodsVoucher::get($id);
if ($voucher) {
    Response::showTemplate('web/goods_voucher/assign', [
        'voucher' => GoodsVoucher::format($voucher, true),
        'multi_mode' => settings('advs.assign.multi') ? 'true' : '',
        'assign_data' => json_encode($voucher->getExtraData('assigned', [])),
        'agent_url' => Util::url('agent'),
        'group_url' => Util::url('device', array('op' => 'group')),
        'tag_url' => Util::url('tags'),
        'device_url' => Util::url('device'),
        'save_url' => Util::url('voucher', array('op' => 'saveAssignData')),
        'back_url' => Util::url('voucher'),
    ]);
}

Response::toast('找不到指定的提货码！', Util::url('voucher'), 'error');