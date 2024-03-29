<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;
use zovye\domain\Goods;
use zovye\util\DBUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$result = DBUtil::transactionDo(function() {
    $id = Request::int('id');

    $account = Account::get($id);

    if ($account) {
        if ($account->isThirdPartyPlatform()) {
            return err('无法删除这个任务！');
        }

        if ($account->isFlashEgg()) {
            $goods = $account->getGoods();
            if ($goods) {
                if (!Goods::safeDelete($goods)) {
                    return err('删除关联商品失败！');
                }
            }
        }

        $title = $account->getTitle();
        $account->destroy();
        Account::updateAccountData();

        return ['title' => $title];
    }

    return err('找不到这个任务！');
});

if (is_error($result)) {
    Response::toast('删除失败！', Util::url('account', ['type' => Request::int('from')]), 'error');
}

Response::toast("删除任务{$result['title']}成功！", Util::url('account', ['type' => Request::int('from')] ), 'success');