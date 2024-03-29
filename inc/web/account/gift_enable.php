<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\business\FlashEgg;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

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

JSON::fail('找不到这个活动！');
