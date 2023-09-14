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
    $data = is_string(request('data')) ? json_decode(htmlspecialchars_decode(request('data')), true) : request(
        'data'
    );
    if ($voucher->setExtraData('assigned', $data) && $voucher->save()) {
        JSON::success('保存成功 ！');
    }
    JSON::success('保存失败 ！');
}

Response::toast('找不到指定的提货码！', Util::url('voucher'), 'error');