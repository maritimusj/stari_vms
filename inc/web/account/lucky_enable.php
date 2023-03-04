<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = request::int('id');
if ($id > 0) {

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
}

JSON::fail('找不到这个活动！');
