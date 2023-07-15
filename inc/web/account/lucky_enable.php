<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$lucky = FlashEgg::getLucky($id);
if ($lucky) {
    $enabled = $lucky->isEnabled();
    $lucky->setEnabled(!$enabled);

    if ($lucky->save()) {
        JSON::success([
            'enabled' => $lucky->isEnabled(),
        ]);
    }
}

JSON::fail('找不到这个活动！');
