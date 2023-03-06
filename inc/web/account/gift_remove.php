<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$result = Util::transactionDo(function () {
    $id = request::int('id');
    if ($id > 0) {
        $gift = FlashEgg::getGift($id);
        if (empty($gift)) {
            return err('找不到这个活动！');
        }

        if (!$gift->destroy()) {
            return err('操作失败！');
        }
    }

    return ['message' => '删除成功！'];
});

JSON::result($result);


