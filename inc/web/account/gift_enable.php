<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = request::int('id');
if ($id > 0) {

    $gift = FlashEgg::getGift($id);
    if ($gift) {

        $enabled = $gift->isEnabled();
        $gift->setEnabled(!$enabled);

        if ($gift->save()) {
            JSON::success([
                'enabled' => $gift->isEnabled(),
            ]);
        }
    }
}

JSON::fail('找不到这个活动！');
