<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\business\FlashEgg;
use zovye\util\DBUtil;

defined('IN_IA') or exit('Access Denied');

$result = DBUtil::transactionDo(function () {
    $id = Request::int('id');
    $gift = FlashEgg::getGift($id);
    if (empty($gift)) {
        return err('找不到这个活动！');
    }

    if (!$gift->destroy()) {
        return err('操作失败！');
    }

    return ['message' => '删除成功！'];
});

JSON::result($result);



