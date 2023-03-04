<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

$id = request::int('id');
if ($id > 0) {
    $log = FlashEgg::getGiftLog($id);
    if ($log) {
        $log->setStatus(1);
        if ($log->save()) {
            JSON::success('已发货！');
        }
    }
}

JSON::fail('找不到这个活动！');